<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificateGatewayPlugin.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerCertificateGatewayPlugin
 *
 * @brief Gateway handler for generating and displaying reviewer certificates.
 *        URLs:
 *          /gateway/plugin/ReviewerCertificateGatewayPlugin/generate?reviewId=X
 *          /gateway/plugin/ReviewerCertificateGatewayPlugin/pdf?reviewId=X
 */

namespace APP\plugins\generic\reviewerCertificate;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\plugins\GatewayPlugin;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class ReviewerCertificateGatewayPlugin extends GatewayPlugin
{
    protected ReviewerCertificatePlugin $_parentPlugin;

    public function __construct(ReviewerCertificatePlugin $parentPlugin)
    {
        $this->_parentPlugin = $parentPlugin;
        parent::__construct();
    }

    public function getName(): string { return 'ReviewerCertificateGatewayPlugin'; }
    public function getHideManagement(): bool { return true; }
    public function getDisplayName(): string { return __('plugins.generic.reviewerCertificate.displayName'); }
    public function getDescription(): string { return __('plugins.generic.reviewerCertificate.description'); }
    public function getPluginPath(): string { return $this->_parentPlugin->getPluginPath(); }
    public function getEnabled(): bool { return $this->_parentPlugin->getEnabled(); }

    /**
     * Dispatch gateway requests.
     */
    public function fetch($args, $request): bool
    {
        if (!$this->_parentPlugin->getEnabled()) {
            return false;
        }

        $context = $request->getContext();
        if (!$context) {
            return false;
        }

        $user = $request->getUser();
        if (!$user) {
            $request->redirect(null, 'login');
            return true;
        }

        $op = array_shift($args);

        if (!in_array($op, ['generate', 'pdf', 'list'])) {
            return false;
        }

        // Central page: list every certificate the logged-in reviewer has earned
        if ($op === 'list') {
            return $this->_listCertificates($request, $context, $user);
        }

        $reviewId = (int) $request->getUserVar('reviewId');
        if (!$reviewId) {
            return false;
        }

        $reviewAssignment = Repo::reviewAssignment()->get($reviewId);
        if (!$reviewAssignment) {
            return false;
        }

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        if (!$submission || (int) $submission->getData('contextId') !== (int) $context->getId()) {
            return false;
        }

        // Access control: the reviewer themselves OR a manager/editor
        $isReviewer   = ((int) $reviewAssignment->getReviewerId() === (int) $user->getId());
        $isPrivileged = $this->_userHasPrivilegedRole($user->getId(), $context->getId());

        if (!$isReviewer && !$isPrivileged) {
            return false;
        }

        // The review must be completed
        if (!$reviewAssignment->getDateCompleted()) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('errorMessage', __('plugins.generic.reviewerCertificate.error.notCompleted'));
            $templateMgr->display($this->_parentPlugin->getTemplateResource('error.tpl'));
            return true;
        }

        if ($op === 'pdf') {
            return $this->_generatePdfDownload($request, $reviewAssignment, $context);
        }

        // ── generate (HTML view) ────────────────────────────────────────────

        $locale     = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl      = in_array(substr($locale, 0, 2), $rtlLocales);

        $reviewer        = Repo::user()->get($reviewAssignment->getReviewerId());
        $publication     = $submission->getCurrentPublication();
        $submissionTitle = $publication->getLocalizedTitle();
        $reviewerName    = $reviewer->getFullName();
        $reviewerAffiliation = $reviewer->getLocalizedAffiliation();
        $journalName     = $context->getLocalizedName();

        $dateCompleted    = $this->_formatDate($reviewAssignment->getDateCompleted(), $locale, $this->_parentPlugin->getSetting($context->getId(), 'dateFormat') ?? 'long', $this->_parentPlugin->getSetting($context->getId(), 'dateLocale') ?? '');
        $rawAcknowledged  = $reviewAssignment->getDateAcknowledged();
        $dateAcknowledged = $rawAcknowledged ? $this->_formatDate($rawAcknowledged, $locale, $this->_parentPlugin->getSetting($context->getId(), 'dateFormat') ?? 'long', $this->_parentPlugin->getSetting($context->getId(), 'dateLocale') ?? '') : $dateCompleted;

        $contextId          = $context->getId();
        $plugin             = $this->_parentPlugin;

        // Live preview: when the settings-form preview iframe requests the
        // certificate it appends the in-progress form values plus rcPreview=1.
        // Only privileged users (manager/editor) may override, and only the
        // safe scalar style/layout fields — free text, HTML and file URLs are
        // never taken from the request to avoid injection. Nothing is saved.
        $previewMode = $isPrivileged && (int) $request->getUserVar('rcPreview') === 1;
        $ovNum = function (string $name, $stored) use ($previewMode, $request) {
            if (!$previewMode) {
                return $stored;
            }
            $v = $request->getUserVar($name);
            return ($v === null || $v === '') ? $stored : $v;
        };
        $ovColor = function (string $name, $stored) use ($previewMode, $request) {
            if (!$previewMode) {
                return $stored;
            }
            $v = $request->getUserVar($name);
            return ($v !== null && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v)) ? $v : $stored;
        };
        // Boolean override: a stored setting that is null/'' means "never
        // saved", which defaults to visible (true). The preview iframe sends
        // an explicit '1'/'0' for each toggle.
        $ovBool = function (string $name, $stored) use ($previewMode, $request) {
            if ($previewMode) {
                $v = $request->getUserVar($name);
                if ($v !== null && $v !== '') {
                    return (string) $v === '1';
                }
            }
            return ($stored === null || $stored === '') ? true : ((string) $stored === '1');
        };

        $editorName         = $plugin->getLocalizedSetting($contextId, 'editorName', $locale, '');
        $editorTitle        = $plugin->getLocalizedSetting($contextId, 'editorTitle', $locale, 'Editor-in-Chief');
        $editorNameFontSize = (int) ($ovNum('editorNameFontSize', $plugin->getSetting($contextId, 'editorNameFontSize')) ?: 12);
        $editorNameFontSize = max(8, min(72, $editorNameFontSize));
        $editorNameColor    = $ovColor('editorNameColor', $plugin->getSetting($contextId, 'editorNameColor')) ?: '#222222';
        $journalNameFontSize = (int) ($ovNum('journalNameFontSize', $plugin->getSetting($contextId, 'journalNameFontSize')) ?: 12);
        $journalNameFontSize = max(8, min(72, $journalNameFontSize));
        $journalNameColor   = $ovColor('journalNameColor', $plugin->getSetting($contextId, 'journalNameColor')) ?: '#7a6030';
        $signatureSize      = (int) ($ovNum('signatureSize', $plugin->getSetting($contextId, 'signatureSize')) ?: 70);
        $signatureSize      = max(20, min(300, $signatureSize));
        $logoSize           = (int) ($ovNum('logoSize', $plugin->getSetting($contextId, 'logoSize')) ?: 70);
        $logoSize           = max(20, min(300, $logoSize));

        // Signature-section layout (Editor-in-Chief + Date blocks).
        // Offsets let the admin nudge the blocks up/down/sideways; values
        // are clamped to keep the layout from being pushed off the page.
        $clampOffset = fn ($v, $min = -400, $max = 400) => max($min, min($max, (int) $v));
        $signatureSectionOffsetY  = $clampOffset($ovNum('signatureSectionOffsetY', $plugin->getSetting($contextId, 'signatureSectionOffsetY')) ?: 0);
        $signatureSectionPaddingTop = $clampOffset($ovNum('signatureSectionPaddingTop', $plugin->getSetting($contextId, 'signatureSectionPaddingTop')) ?: 0, 0, 400);
        $signatureSectionGap      = $clampOffset($ovNum('signatureSectionGap', $plugin->getSetting($contextId, 'signatureSectionGap')) ?: 80, 0, 400);
        $editorBlockOffsetX       = $clampOffset($ovNum('editorBlockOffsetX', $plugin->getSetting($contextId, 'editorBlockOffsetX')) ?: 0);
        $editorBlockOffsetY       = $clampOffset($ovNum('editorBlockOffsetY', $plugin->getSetting($contextId, 'editorBlockOffsetY')) ?: 0);
        $dateBlockOffsetX         = $clampOffset($ovNum('dateBlockOffsetX', $plugin->getSetting($contextId, 'dateBlockOffsetX')) ?: 0);
        $dateBlockOffsetY         = $clampOffset($ovNum('dateBlockOffsetY', $plugin->getSetting($contextId, 'dateBlockOffsetY')) ?: 0);

        // Global vertical shift for the whole text block (− up / + down)
        $contentOffsetY = $clampOffset($ovNum('contentOffsetY', $plugin->getSetting($contextId, 'contentOffsetY')) ?: 0);

        // Per-element visibility (default visible when never configured)
        $elementToggles = [];
        foreach (ReviewerCertificatePlugin::elementToggleKeys() as $toggle) {
            $elementToggles[$toggle] = $ovBool($toggle, $plugin->getSetting($contextId, $toggle));
        }

        // Localized text overrides (empty => template uses the default).
        // Resolved from stored settings only — never from the preview
        // request, since free text must not be taken from the URL.
        $textOverrides = [];
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $textOverrides[$key] = $plugin->getLocalizedSetting($contextId, $key, $locale, '');
        }

        $accentColor        = $ovColor('accentColor', $plugin->getSetting($contextId, 'accentColor')) ?: '#b8975a';
        $enableQrCode       = (bool) ($plugin->getSetting($contextId, 'enableQrCode') ?? true);
        $qrSize             = max(20, min(300, (int) ($ovNum('qrSize', $plugin->getSetting($contextId, 'qrSize')) ?: 68)));
        $qrOffsetX          = $clampOffset($ovNum('qrOffsetX', $plugin->getSetting($contextId, 'qrOffsetX')) ?: 0);
        $qrOffsetY          = $clampOffset($ovNum('qrOffsetY', $plugin->getSetting($contextId, 'qrOffsetY')) ?: 0);
        $signatureUrl       = $plugin->getSetting($contextId, 'signatureUrl') ?? '';
        $customLogoUrl      = $plugin->getSetting($contextId, 'customLogoUrl') ?? '';
        $backgroundImageUrl = $plugin->getSetting($contextId, 'backgroundImageUrl') ?? '';

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#b8975a';
        }

        $textColor = $ovColor('textColor', $plugin->getSetting($contextId, 'textColor')) ?: '#1a1a2e';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) {
            $textColor = '#1a1a2e';
        }

        $certificateBodyRaw  = $plugin->getLocalizedSetting($contextId, 'certificateBody', $locale, '');
        $certificateBodyHtml = $certificateBodyRaw
            ? str_replace(
                ['{journalName}', '{submissionTitle}'],
                [
                    '<em>' . htmlspecialchars($journalName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</em>',
                    '<em>' . htmlspecialchars($submissionTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</em>',
                ],
                $certificateBodyRaw
            )
            : null;

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

        // PDF download URL (only if wkhtmltopdf is available)
        $pdfUrl = '';
        if ($this->_getWkhtmltopdfPath($contextId)) {
            $pdfUrl = $request->getDispatcher()->url(
                $request, PKPApplication::ROUTE_PAGE, null,
                'gateway', 'plugin',
                ['ReviewerCertificateGatewayPlugin', 'pdf'],
                ['reviewId' => $reviewId]
            );
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'reviewerName'        => $reviewerName,
            'reviewerAffiliation' => $reviewerAffiliation,
            'submissionTitle'     => $submissionTitle,
            'journalName'         => $journalName,
            'dateCompleted'       => $dateCompleted,
            'dateAcknowledged'    => $dateAcknowledged,
            'reviewId'            => $reviewId,
            'editorName'          => $editorName,
            'editorTitle'         => $editorTitle,
            'editorNameFontSize'  => $editorNameFontSize,
            'editorNameColor'     => $editorNameColor,
            'journalNameFontSize' => $journalNameFontSize,
            'journalNameColor'    => $journalNameColor,
            'signatureSize'       => $signatureSize,
            'logoSize'            => $logoSize,
            'signatureSectionOffsetY'    => $signatureSectionOffsetY,
            'signatureSectionPaddingTop' => $signatureSectionPaddingTop,
            'signatureSectionGap'        => $signatureSectionGap,
            'editorBlockOffsetX'  => $editorBlockOffsetX,
            'editorBlockOffsetY'  => $editorBlockOffsetY,
            'dateBlockOffsetX'    => $dateBlockOffsetX,
            'dateBlockOffsetY'    => $dateBlockOffsetY,
            'contentOffsetY'      => $contentOffsetY,
            'accentColor'         => $accentColor,
            'textColor'           => $textColor,
            'enableQrCode'        => $enableQrCode,
            'qrSize'              => $qrSize,
            'qrOffsetX'           => $qrOffsetX,
            'qrOffsetY'           => $qrOffsetY,
            'certificateBodyHtml' => $certificateBodyHtml,
            'signatureUrl'        => $signatureUrl,
            'logoUrl'             => $logoUrl,
            'backgroundImageUrl'  => $backgroundImageUrl,
            'isRtl'               => $isRtl,
            'currentLocale'       => $locale,
            'certificateUrl'      => null,
            'pdfUrl'              => $pdfUrl,
        ]);

        $templateMgr->assign($elementToggles);
        $templateMgr->assign($textOverrides);

        $templateMgr->display($this->_parentPlugin->getTemplateResource('certificate.tpl'));
        return true;
    }

    // ── Reviewer certificate list ───────────────────────────────────────────

    /**
     * Render a page listing all completed-review certificates that belong to
     * the logged-in reviewer in the current journal, each with a view link
     * and (when available) a server-side PDF download link.
     */
    private function _listCertificates($request, $context, $user): bool
    {
        $contextId = $context->getId();
        $locale    = Locale::getLocale();
        $dateFormat = $this->_parentPlugin->getSetting($contextId, 'dateFormat') ?? 'long';
        $dateLocaleSetting = $this->_parentPlugin->getSetting($contextId, 'dateLocale') ?? '';

        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl      = in_array(substr($locale, 0, 2), $rtlLocales);

        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByReviewerIds([(int) $user->getId()])
            ->filterByCompleted(true)
            ->getMany();

        $dispatcher   = $request->getDispatcher();
        $pdfAvailable = (bool) $this->_getWkhtmltopdfPath($contextId);

        // Search term (matched against submission title, case-insensitive)
        $searchQuery = trim((string) $request->getUserVar('searchQuery'));

        $certificates = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            if (!$reviewAssignment->getDateCompleted()) {
                continue;
            }

            $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
            if (!$submission) {
                continue;
            }

            $submissionTitle = $submission->getCurrentPublication()->getLocalizedTitle();

            if ($searchQuery !== '' && mb_stripos($submissionTitle, $searchQuery) === false) {
                continue;
            }

            $reviewId = $reviewAssignment->getId();

            $certificates[] = [
                'reviewId'        => $reviewId,
                'submissionTitle' => $submissionTitle,
                'dateCompleted'   => $this->_formatDate($reviewAssignment->getDateCompleted(), $locale, $dateFormat, $dateLocaleSetting),
                'viewUrl'         => $dispatcher->url(
                    $request, PKPApplication::ROUTE_PAGE, null,
                    'gateway', 'plugin',
                    ['ReviewerCertificateGatewayPlugin', 'generate'],
                    ['reviewId' => $reviewId]
                ),
                'pdfUrl'          => $pdfAvailable ? $dispatcher->url(
                    $request, PKPApplication::ROUTE_PAGE, null,
                    'gateway', 'plugin',
                    ['ReviewerCertificateGatewayPlugin', 'pdf'],
                    ['reviewId' => $reviewId]
                ) : '',
            ];
        }

        // ── Pagination ──────────────────────────────────────────────────────
        $perPage    = 10;
        $totalCount = count($certificates);
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page       = (int) $request->getUserVar('page');
        if ($page < 1) {
            $page = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $pageCertificates = array_slice($certificates, ($page - 1) * $perPage, $perPage);

        // Base URL of this list page, used to build search/pagination links
        $listUrl = $dispatcher->url(
            $request, PKPApplication::ROUTE_PAGE, null,
            'gateway', 'plugin',
            ['ReviewerCertificateGatewayPlugin', 'list']
        );

        $rangeStart = $totalCount ? (($page - 1) * $perPage) + 1 : 0;
        $rangeEnd   = min($page * $perPage, $totalCount);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'reviewerName' => $user->getFullName(),
            'reviewerAffiliation' => $user->getLocalizedAffiliation(),
            'journalName'  => $context->getLocalizedName(),
            'certificates' => $pageCertificates,
            'isRtl'        => $isRtl,
            'currentLocale' => $locale,
            'searchQuery'  => $searchQuery,
            'listUrl'      => $listUrl,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'pageNumbers'  => range(1, $totalPages),
            'totalCount'   => $totalCount,
            'rangeStart'   => $rangeStart,
            'rangeEnd'     => $rangeEnd,
        ]);
        $templateMgr->display($this->_parentPlugin->getTemplateResource('certificates.tpl'));
        return true;
    }

    // ── PDF generation ──────────────────────────────────────────────────────

    private function _generatePdfDownload($request, $reviewAssignment, $context): bool
    {
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!$reviewer) {
            return false;
        }

        $contextId    = $context->getId();
        $reviewId     = $reviewAssignment->getId();
        $safeUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reviewer->getUsername());
        $htmlFile     = 'public/site/images/' . $safeUsername . '/reviewer_cert_' . $reviewId . '.html';

        // Always regenerate from the current template/settings so the PDF
        // reflects the latest design, colours and locale (the saved file is
        // otherwise frozen at thank-reviewer time).
        $this->_parentPlugin->generateAndSaveCertificate($request, $reviewAssignment, $context);

        if (!file_exists($htmlFile)) {
            error_log('[ReviewerCertificate] HTML source file not found for PDF: ' . $htmlFile);
            return false;
        }

        $wkhtmltopdf = $this->_getWkhtmltopdfPath($contextId);
        if (!$wkhtmltopdf) {
            return false;
        }

        $absoluteHtml = realpath($htmlFile);
        if (!$absoluteHtml) {
            return false;
        }

        $pdfPath = sys_get_temp_dir() . '/rc_cert_' . $reviewId . '_' . getmypid() . '.pdf';

        $cmd = escapeshellarg($wkhtmltopdf)
            . ' --page-width 297mm --page-height 210mm'
            . ' --margin-top 0mm --margin-bottom 0mm --margin-left 0mm --margin-right 0mm'
            . ' --zoom 1'
            . ' --print-media-type'
            . ' --background'
            . ' --images'
            . ' --disable-smart-shrinking'
            . ' --load-error-handling ignore'
            . ' ' . escapeshellarg($absoluteHtml)
            . ' ' . escapeshellarg($pdfPath)
            . ' 2>/dev/null';

        exec($cmd, $cmdOutput, $exitCode);

        if ($exitCode !== 0 || !file_exists($pdfPath) || filesize($pdfPath) === 0) {
            error_log('[ReviewerCertificate] wkhtmltopdf failed (exit ' . $exitCode . '): ' . implode("\n", $cmdOutput));
            @unlink($pdfPath);
            return false;
        }

        $pdfContent = file_get_contents($pdfPath);
        @unlink($pdfPath);

        // Output PDF
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="reviewer_certificate_' . $reviewId . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0');
        echo $pdfContent;
        return true;
    }

    /**
     * Locate the wkhtmltopdf binary.
     * Checks the plugin setting first, then common system paths.
     */
    private function _getWkhtmltopdfPath(int $contextId): string
    {
        // Admin-configured path takes priority
        $configured = $this->_parentPlugin->getSetting($contextId, 'wkhtmltopdfPath') ?? '';
        if ($configured && is_executable($configured)) {
            return $configured;
        }

        // Common installation paths
        foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/opt/homebrew/bin/wkhtmltopdf'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Last resort: ask the shell
        $which = trim((string) shell_exec('which wkhtmltopdf 2>/dev/null'));
        return ($which && is_executable($which)) ? $which : '';
    }

    /**
     * Format a date string using the selected format or locale-based IntlDateFormatter.
     */
    private function _formatDate(string $dateStr, string $locale, string $format = 'long', string $dateLocale = ''): string
    {
        $timestamp = strtotime($dateStr);
        if (!$timestamp) {
            return $dateStr;
        }

        $effectiveLocale = ($dateLocale !== '') ? $dateLocale : $locale;

        $intlMap = [
            'long'   => \IntlDateFormatter::LONG,
            'medium' => \IntlDateFormatter::MEDIUM,
            'short'  => \IntlDateFormatter::SHORT,
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
            'd-m-Y'  => 'd-m-Y',
            'd/m/Y'  => 'd/m/Y',
            'm/d/Y'  => 'm/d/Y',
            'Y-m-d'  => 'Y-m-d',
            'Y/m/d'  => 'Y/m/d',
            'd.m.Y'  => 'd.m.Y',
            'Y.m.d'  => 'Y.m.d',
            'd F Y'  => 'd F Y',
            'F d, Y' => 'F d, Y',
            'j F Y'  => 'j F Y',
            'd M Y'  => 'd M Y',
            'M d, Y' => 'M d, Y',
        ];

        if (isset($phpMap[$format])) {
            if (class_exists('\IntlDateFormatter') && in_array($format, ['d F Y', 'F d, Y', 'j F Y', 'd M Y', 'M d, Y'])) {
                $pattern = $format;
                $fmt = new \IntlDateFormatter(
                    $effectiveLocale,
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    null,
                    \IntlDateFormatter::GREGORIAN,
                    $pattern
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

    /**
     * Check whether the user holds a manager or site admin role in this context.
     */
    protected function _userHasPrivilegedRole(int $userId, int $contextId): bool
    {
        $userGroups = UserGroup::withUserIds([$userId])
            ->withContextIds([$contextId])
            ->get();

        foreach ($userGroups as $userGroup) {
            if (in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR])) {
                return true;
            }
        }

        return false;
    }
}
