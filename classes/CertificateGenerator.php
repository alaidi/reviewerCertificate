<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/CertificateGenerator.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateGenerator
 *
 * @brief Freeze + render logic for reviewer certificates.
 *
 *   resolveSnapshotData() — value resolution only; NO TemplateManager calls.
 *   freeze()              — idempotent write to DB; NO TemplateManager calls.
 *   renderFromCertificate() — the only place that touches Smarty/TemplateManager.
 *
 * This separation lets a CLI backfill call freeze() hundreds of times
 * without a web-request / render context.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\template\TemplateManager;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\facades\Locale;

class CertificateGenerator
{
    // -----------------------------------------------------------------------
    // Partition constants
    // -----------------------------------------------------------------------

    /**
     * Keys whose resolved values are the same for every certificate that uses
     * the same template at the same point in time.  These are content-addressed
     * (deduped) in reviewer_certificate_snapshots.
     *
     * Any change to this list must be reflected in tests and documentation.
     */
    public static function sharedKeys(): array
    {
        return array_merge(
            [
                'editorTitle',
                'editorName',
                'certificateBody',
                'journalNameText',
                'headingText',
                'subheadingText',
                'presentedToText',
                'completedOnText',
                'dateLabelText',
            ],
            ReviewerCertificatePlugin::elementToggleKeys(),
            [
                'accentColor',
                'textColor',
                'editorNameColor',
                'journalNameColor',
                'editorNameFontSize',
                'journalNameFontSize',
                'signatureSize',
                'logoSize',
                'enableQrCode',
                'qrSize',
                'qrOffsetX',
                'qrOffsetY',
                'contentOffsetY',
                'layout',
            ]
        );
    }

    /**
     * Keys that are specific to each individual certificate and must be stored
     * on the cert row itself (not deduped).
     */
    public static function perCertKeys(): array
    {
        return [
            'reviewerName',
            'reviewerAffiliation',
            'submissionTitle',
            'dateCompleted',
            'dateAcknowledged',
            'signatureUrl',
            'logoUrl',
            'backgroundImageUrl',
            'reviewId',
            'journalName',
            'currentLocale',
        ];
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Resolve ALL template variables and partition them into shared vs perCert.
     *
     * CRITICAL: This method MUST NOT call TemplateManager or render anything.
     * It only reads settings from the DB, resolves values, and returns arrays.
     *
     * @param mixed $plugin           ReviewerCertificatePlugin instance
     * @param mixed $request          PKP Request object
     * @param mixed $reviewAssignment PKP ReviewAssignment
     * @param mixed $context          Journal/context object
     * @param mixed $template         ReviewerCertificateTemplate|null
     *
     * @return array{shared: array, perCert: array}
     */
    public function resolveSnapshotData($plugin, $request, $reviewAssignment, $context, $template): array
    {
        $contextId = (int) $context->getId();
        $reviewId = (int) $reviewAssignment->getId();

        // ------------------------------------------------------------------
        // Load all template-scoped settings into a lookup map so individual
        // getter calls below can use it.
        // ------------------------------------------------------------------
        $templateId = $template ? (int) $template->getTemplateId() : null;
        $templateSettings = []; // locale → settingName → value

        if ($templateId !== null) {
            /** @var ReviewerCertificateTemplateDAO $templateDao */
            $templateDao = \PKP\db\DAORegistry::getDAO('ReviewerCertificateTemplateDAO');
            $rows = $templateDao->getSettings($templateId);
            foreach ($rows as $row) {
                $locale = (string) ($row['locale'] ?? '');
                $name = (string) ($row['setting_name'] ?? '');
                $value = $row['setting_value'] ?? null;
                $templateSettings[$locale][$name] = $value;
            }
        }

        // Helper: get a template setting (no locale) — falls back to plugin.
        $getSetting = function (string $name, $default = null) use (
            $templateId,
            $templateSettings,
            $plugin,
            $contextId
        ) {
            if ($templateId !== null) {
                // Non-localized: stored with locale = ''
                if (isset($templateSettings[''][$name])) {
                    $v = $templateSettings[''][$name];
                    if ($v !== null && $v !== '') {
                        return $v;
                    }
                }
            }
            $v = $plugin->getSetting($contextId, $name);
            return ($v !== null && $v !== '') ? $v : $default;
        };

        // Helper: get a localized template setting — falls back to plugin.
        $getLocalizedSetting = function (
            string $name,
            ?string $locale = null,
            string $default = ''
        ) use (
            $templateId,
            $templateSettings,
            $plugin,
            $contextId,
            $context
        ) {
            $locale = $locale ?: Locale::getLocale();

            if ($templateId !== null) {
                // Try exact locale
                if (isset($templateSettings[$locale][$name]) && $templateSettings[$locale][$name] !== '') {
                    return (string) $templateSettings[$locale][$name];
                }
                // Try context primary locale
                $primary = $context->getPrimaryLocale();
                if ($primary && $primary !== $locale
                    && isset($templateSettings[$primary][$name])
                    && $templateSettings[$primary][$name] !== ''
                ) {
                    return (string) $templateSettings[$primary][$name];
                }
                // Try any locale
                foreach ($templateSettings as $localeKey => $names) {
                    if (isset($names[$name]) && $names[$name] !== '') {
                        return (string) $names[$name];
                    }
                }
                // Fall through to plugin setting below
            }

            return $plugin->getLocalizedSetting($contextId, $name, $locale, $default);
        };

        // ------------------------------------------------------------------
        // Locale / direction (needed for date formatting + per-cert isRtl)
        // ------------------------------------------------------------------
        $locale = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl = in_array(substr($locale, 0, 2), $rtlLocales);

        // ------------------------------------------------------------------
        // perCert — reviewer/submission-specific values
        // ------------------------------------------------------------------
        $submission = Repo::submission()->get((int) $reviewAssignment->getSubmissionId());
        $reviewer = Repo::user()->get((int) $reviewAssignment->getReviewerId());

        $publication = $submission ? $submission->getCurrentPublication() : null;
        $submissionTitle = $publication ? (string) $publication->getLocalizedTitle() : '';
        $reviewerName = $reviewer ? (string) $reviewer->getFullName() : '';
        $reviewerAffiliation = $reviewer ? (string) $reviewer->getLocalizedAffiliation() : '';

        $dateFormat = (string) ($getSetting('dateFormat', 'long'));
        $dateLocale = (string) ($getSetting('dateLocale', ''));
        $dateCompleted = $this->_formatDate(
            (string) ($reviewAssignment->getDateCompleted() ?? ''),
            $locale,
            $dateFormat,
            $dateLocale
        );
        $rawAcknowledged = $reviewAssignment->getDateAcknowledged();
        $dateAcknowledged = $rawAcknowledged
            ? $this->_formatDate((string) $rawAcknowledged, $locale, $dateFormat, $dateLocale)
            : $dateCompleted;

        // Signature URL
        $signatureUrl = (string) ($getSetting('signatureUrl') ?? '');

        // Logo URL
        $customLogoUrl = (string) ($getSetting('customLogoUrl') ?? '');
        $logoUrl = $customLogoUrl;
        if (!$logoUrl) {
            $logoData = $context->getLocalizedData('pageHeaderLogoImage');
            if ($logoData && !empty($logoData['uploadName'])) {
                $publicFileManager = new PublicFileManager();
                $logoUrl = $request->getBaseUrl() . '/'
                    . $publicFileManager->getContextFilesPath($contextId) . '/'
                    . $logoData['uploadName'];
            }
        }

        // Background image URL
        $backgroundImageUrl = (string) ($getSetting('backgroundImageUrl') ?? '');

        // Resolve the journal display name: prefer the admin journalNameText override
        // (stored in shared), else fall back to the live context name.  We freeze
        // this resolved value in perCert so a cert always has a non-blank journal
        // name even when no override is configured.
        $journalNameTextOverride = (string) ($getSetting('journalNameText', ''));
        $journalNameResolved = ($journalNameTextOverride !== '')
            ? $journalNameTextOverride
            : (string) $context->getLocalizedName();

        $perCert = [
            'reviewerName' => $reviewerName,
            'reviewerAffiliation' => $reviewerAffiliation,
            'submissionTitle' => $submissionTitle,
            'dateCompleted' => $dateCompleted,
            'dateAcknowledged' => $dateAcknowledged,
            'signatureUrl' => $signatureUrl,
            'logoUrl' => $logoUrl,
            'backgroundImageUrl' => $backgroundImageUrl,
            'reviewId' => $reviewId,
            // Freeze-time resolved journal name (override or live context name).
            // Stored in perCert because different certs may be issued under
            // different journal names / locales over time.
            'journalName' => $journalNameResolved,
            // Freeze-time locale — used at render time to derive isRtl and to
            // set the HTML lang attribute, so a cert frozen in Arabic always
            // renders RTL regardless of the server's current locale.
            'currentLocale' => $locale,
        ];

        // ------------------------------------------------------------------
        // shared — template/journal-level values (same for all certs using
        // this template at this point in time)
        // ------------------------------------------------------------------
        $editorName = $getLocalizedSetting('editorName', $locale, '');
        $editorTitle = $getLocalizedSetting('editorTitle', $locale, 'Editor-in-Chief');
        $certificateBody = $getLocalizedSetting('certificateBody', $locale, '');

        $editorNameFontSize = (int) ($getSetting('editorNameFontSize', 12));
        $editorNameColor = (string) ($getSetting('editorNameColor', '#222222'));
        $journalNameFontSize = (int) ($getSetting('journalNameFontSize', 12));
        $journalNameColor = (string) ($getSetting('journalNameColor', '#7a6030'));
        $signatureSize = (int) ($getSetting('signatureSize', 70));
        $logoSize = (int) ($getSetting('logoSize', 70));
        $accentColor = (string) ($getSetting('accentColor', '#b8975a'));
        $textColor = (string) ($getSetting('textColor', '#1a1a2e'));
        $enableQrCode = (bool) ($getSetting('enableQrCode', true));
        $qrSize = (int) ($getSetting('qrSize', 68));
        $qrOffsetX = (int) ($getSetting('qrOffsetX', 0));
        $qrOffsetY = (int) ($getSetting('qrOffsetY', 0));
        $contentOffsetY = max(-400, min(400, (int) ($getSetting('contentOffsetY', 0))));

        // Validate colors
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#b8975a';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) {
            $textColor = '#1a1a2e';
        }

        // layout (from template object if present, else plugin setting)
        $layout = $template ? (string) $template->getLayout() : (string) ($getSetting('layout', 'landscape'));

        // Per-element visibility toggles (default = true when never configured)
        $elementToggles = [];
        foreach (ReviewerCertificatePlugin::elementToggleKeys() as $toggle) {
            if ($templateId !== null && isset($templateSettings[''][$toggle])) {
                $stored = $templateSettings[''][$toggle];
            } else {
                $stored = $plugin->getSetting($contextId, $toggle);
            }
            $elementToggles[$toggle] = ($stored === null || $stored === '') ? true : ((string) $stored === '1');
        }

        // Localized text overrides
        $textOverrides = [];
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $textOverrides[$key] = $getLocalizedSetting($key, $locale, '');
        }

        $shared = array_merge(
            [
                'editorName' => $editorName,
                'editorTitle' => $editorTitle,
                'certificateBody' => $certificateBody,
                // numeric/style
                'accentColor' => $accentColor,
                'textColor' => $textColor,
                'editorNameColor' => $editorNameColor,
                'journalNameColor' => $journalNameColor,
                'editorNameFontSize' => $editorNameFontSize,
                'journalNameFontSize' => $journalNameFontSize,
                'signatureSize' => $signatureSize,
                'logoSize' => $logoSize,
                'enableQrCode' => $enableQrCode,
                'qrSize' => $qrSize,
                'qrOffsetX' => $qrOffsetX,
                'qrOffsetY' => $qrOffsetY,
                'contentOffsetY' => $contentOffsetY,
                'layout' => $layout,
            ],
            $elementToggles,
            $textOverrides
        );

        return ['shared' => $shared, 'perCert' => $perCert];
    }

    /**
     * Idempotent freeze: if a cert row already exists for this review, return it.
     * Otherwise resolve data, dedup shared snapshot, insert cert row, return it.
     *
     * CRITICAL: No TemplateManager or Smarty calls here.
     *
     */
    public function freeze($plugin, $request, $reviewAssignment, $context, $template): ReviewerCertificate
    {
        /** @var ReviewerCertificateDAO $certDao */
        $certDao = \PKP\db\DAORegistry::getDAO('ReviewerCertificateDAO');
        $reviewId = (int) $reviewAssignment->getId();

        $existing = $certDao->getByReviewId($reviewId);
        if ($existing) {
            return $existing;
        }

        $data = $this->resolveSnapshotData($plugin, $request, $reviewAssignment, $context, $template);
        $snapshotId = $certDao->findOrCreateContentSnapshot($data['shared']);

        $cert = $certDao->newDataObject();
        $cert->setReviewerId((int) $reviewAssignment->getReviewerId());
        $cert->setSubmissionId((int) $reviewAssignment->getSubmissionId());
        $cert->setReviewId($reviewId);
        $cert->setContextId((int) $context->getId());
        $cert->setTemplateId($template ? (int) $template->getTemplateId() : null);
        $cert->setSnapshotId($snapshotId);
        $cert->setSnapshot(json_encode($data['perCert'], JSON_UNESCAPED_UNICODE));
        $cert->setDateIssued(Core::getCurrentDate());
        $cert->setCertificateCode($this->_uniqueCode($certDao));
        $cert->setDownloadCount(0);
        $certDao->insertObject($cert);

        return $cert;
    }

    /**
     * Render a certificate from its frozen snapshot to an HTML string.
     *
     * This is the ONLY method that may touch TemplateManager/Smarty.
     *
     * @param mixed $plugin ReviewerCertificatePlugin
     * @param mixed $request PKP Request
     *
     * @return string Rendered HTML
     */
    public function renderFromCertificate($plugin, $request, ReviewerCertificate $cert): string
    {
        /** @var ReviewerCertificateDAO $certDao */
        $certDao = \PKP\db\DAORegistry::getDAO('ReviewerCertificateDAO');

        // Load shared snapshot JSON
        $sharedJson = $certDao->getContentSnapshot((int) $cert->getSnapshotId());
        $shared = $sharedJson ? (array) json_decode($sharedJson, true) : [];

        // Load per-cert snapshot JSON (stored on the cert row)
        $perCert = $cert->getSnapshot()
            ? (array) json_decode($cert->getSnapshot(), true)
            : [];

        // Merge: perCert keys win over shared if there is any overlap
        $merged = array_merge($shared, $perCert);

        // Normalize asset URLs: convert absolute localhost URLs to relative paths
        // so saved HTML is portable.
        foreach (['signatureUrl', 'logoUrl', 'backgroundImageUrl'] as $urlKey) {
            if (!empty($merged[$urlKey])) {
                $merged[$urlKey] = $this->_normalizeAssetUrl(
                    (string) $merged[$urlKey],
                    $request
                );
            }
        }

        // Compute certificateBodyHtml from raw certificateBody stored in snapshot
        $certificateBodyRaw = (string) ($merged['certificateBody'] ?? '');
        // Use the frozen resolved journal name (perCert); fall back to the
        // journalNameText admin override for rows frozen before this fix.
        $journalName = (string) ($merged['journalName'] ?? $merged['journalNameText'] ?? '');
        $submissionTitle = (string) ($merged['submissionTitle'] ?? '');
        $certificateBodyHtml = $certificateBodyRaw
            ? str_replace(
                ['{journalName}', '{submissionTitle}'],
                [
                    '<em>' . htmlspecialchars($journalName !== '' ? $journalName : '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</em>',
                    '<em>' . htmlspecialchars($submissionTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</em>',
                ],
                $certificateBodyRaw
            )
            : null;

        // Gateway URL for QR code (uses the cert code for a permanent link)
        $reviewId = (int) ($merged['reviewId'] ?? $cert->getReviewId());
        $gatewayUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'generate'],
            ['reviewId' => $reviewId]
        );

        // RTL detection: use the freeze-time locale stored in perCert so a cert
        // frozen in Arabic always renders RTL regardless of the server's current
        // locale.  Fall back to Locale::getLocale() only for rows frozen before
        // this fix (which lack the currentLocale key).
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $locale = (isset($perCert['currentLocale']) && $perCert['currentLocale'] !== '')
            ? (string) $perCert['currentLocale']
            : Locale::getLocale();
        $isRtl = in_array(substr($locale, 0, 2), $rtlLocales);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            // perCert
            'reviewerName' => $merged['reviewerName'] ?? '',
            'reviewerAffiliation' => $merged['reviewerAffiliation'] ?? '',
            'submissionTitle' => $submissionTitle,
            'dateCompleted' => $merged['dateCompleted'] ?? '',
            'dateAcknowledged' => $merged['dateAcknowledged'] ?? '',
            'signatureUrl' => $merged['signatureUrl'] ?? '',
            'logoUrl' => $merged['logoUrl'] ?? '',
            'backgroundImageUrl' => $merged['backgroundImageUrl'] ?? '',
            'reviewId' => $reviewId,
            // shared
            'editorName' => $merged['editorName'] ?? '',
            'editorTitle' => $merged['editorTitle'] ?? '',
            'editorNameFontSize' => (int) ($merged['editorNameFontSize'] ?? 12),
            'editorNameColor' => $merged['editorNameColor'] ?? '#222222',
            'journalNameFontSize' => (int) ($merged['journalNameFontSize'] ?? 12),
            'journalNameColor' => $merged['journalNameColor'] ?? '#7a6030',
            'signatureSize' => (int) ($merged['signatureSize'] ?? 70),
            'logoSize' => (int) ($merged['logoSize'] ?? 70),
            'accentColor' => $merged['accentColor'] ?? '#b8975a',
            'textColor' => $merged['textColor'] ?? '#1a1a2e',
            'enableQrCode' => (bool) ($merged['enableQrCode'] ?? true),
            'qrSize' => (int) ($merged['qrSize'] ?? 68),
            'qrOffsetX' => (int) ($merged['qrOffsetX'] ?? 0),
            'qrOffsetY' => (int) ($merged['qrOffsetY'] ?? 0),
            'contentOffsetY' => (int) ($merged['contentOffsetY'] ?? 0),
            'certificateBodyHtml' => $certificateBodyHtml,
            // QR / meta
            'certificateUrl' => $gatewayUrl,
            'isRtl' => $isRtl,
            'currentLocale' => $locale,
            // Frozen resolved journal name (override or live context name at freeze
            // time).  The template uses $journalNameText as the primary display
            // value and falls back to $journalName — we set both so either path
            // yields the correct name.
            'journalName' => $journalName,
        ]);

        // Assign element toggle booleans
        $elementToggles = [];
        foreach (ReviewerCertificatePlugin::elementToggleKeys() as $toggle) {
            $elementToggles[$toggle] = isset($merged[$toggle]) ? (bool) $merged[$toggle] : true;
        }
        $templateMgr->assign($elementToggles);

        // Assign text overrides
        $textOverrides = [];
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $textOverrides[$key] = $merged[$key] ?? '';
        }
        $templateMgr->assign($textOverrides);

        return $templateMgr->fetch($plugin->getTemplateResource('certificate.tpl'));
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Generate a unique certificate code by looping until we find one that
     * does not exist in the database.
     */
    private function _uniqueCode($certDao): string
    {
        do {
            $code = ReviewerCertificate::generateCode();
        } while ($certDao->getByCertificateCode($code) !== null);
        return $code;
    }

    /**
     * Normalize an absolute URL that points to localhost / the current base URL
     * into a site-relative path.  This makes saved HTML files portable when the
     * domain changes (e.g., dev → production).
     */
    private function _normalizeAssetUrl(string $url, $request): string
    {
        if ($url === '') {
            return '';
        }
        $baseUrl = $request->getBaseUrl();
        if ($baseUrl && strpos($url, $baseUrl) === 0) {
            return substr($url, strlen($baseUrl));
        }
        // Also strip localhost-style absolute URLs
        if (preg_match('#^https?://localhost(?::\d+)?(/[^\s]*)#', $url, $m)) {
            return $m[1];
        }
        return $url;
    }

    /**
     * Format a date string using IntlDateFormatter (if available) or PHP date().
     * Mirrors ReviewerCertificatePlugin::_formatDate().
     */
    private function _formatDate(string $dateStr, string $locale, string $format = 'long', string $dateLocale = ''): string
    {
        if ($dateStr === '') {
            return '';
        }
        $timestamp = strtotime($dateStr);
        if (!$timestamp) {
            return $dateStr;
        }

        $effectiveLocale = ($dateLocale !== '') ? $dateLocale : $locale;

        $intlMap = [
            'long' => \IntlDateFormatter::LONG,
            'medium' => \IntlDateFormatter::MEDIUM,
            'short' => \IntlDateFormatter::SHORT,
        ];

        if (isset($intlMap[$format])) {
            if (class_exists('\IntlDateFormatter')) {
                $fmt = new \IntlDateFormatter(
                    $effectiveLocale,
                    $intlMap[$format],
                    \IntlDateFormatter::NONE,
                    null,
                    \IntlDateFormatter::GREGORIAN
                );
                $result = $fmt->format($timestamp);
                if ($result !== false) {
                    return $result;
                }
            }
            return date('F d, Y', $timestamp);
        }

        $phpMap = [
            'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'Y/m/d',
            'd.m.Y', 'Y.m.d', 'd F Y', 'F d, Y', 'j F Y', 'd M Y', 'M d, Y',
        ];

        if (in_array($format, $phpMap, true)) {
            if (class_exists('\IntlDateFormatter') && in_array($format, ['d F Y', 'F d, Y', 'j F Y', 'd M Y', 'M d, Y'], true)) {
                $fmt = new \IntlDateFormatter(
                    $effectiveLocale,
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    null,
                    \IntlDateFormatter::GREGORIAN,
                    $format
                );
                $result = $fmt->format($timestamp);
                if ($result !== false) {
                    return $result;
                }
            }
            return date($format, $timestamp);
        }

        return date('F d, Y', $timestamp);
    }
}
