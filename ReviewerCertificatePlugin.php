<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReviewerCertificatePlugin
 *
 * @brief Plugin to generate appreciation certificates for reviewers
 *        who have completed their review assignments.
 */

namespace APP\plugins\generic\reviewerCertificate;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class ReviewerCertificatePlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        $this->addLocaleData();

        require_once __DIR__ . '/classes/ReviewerCertificateDAO.php';
        require_once __DIR__ . '/classes/ReviewerCertificateTemplateDAO.php';
        \PKP\db\DAORegistry::registerDAO('ReviewerCertificateDAO', new \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateDAO());
        \PKP\db\DAORegistry::registerDAO('ReviewerCertificateTemplateDAO', new \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO());

        if ($this->getEnabled($mainContextId)) {
            // Some OJS installs do not autoload sibling plugin classes;
            // load them explicitly so the plugin is self-contained.
            if (!class_exists(ReviewerCertificateGatewayPlugin::class, false)) {
                require_once __DIR__ . '/ReviewerCertificateGatewayPlugin.php';
            }

            PluginRegistry::register(
                'gateways',
                new ReviewerCertificateGatewayPlugin($this),
                $this->getPluginPath()
            );

            Hook::add('ThankReviewerForm::thankReviewer', $this->sendCertificateEmail(...));
            Hook::add('Templates::Reviewer::Review::Step3', $this->addCertificateLinkToReviewStep(...));
            Hook::add('TemplateManager::display', $this->addCertificatesMenuItem(...));
        }

        return true;
    }

    /**
     * Hook callback: generate the certificate file, save it, and email the reviewer.
     *
     * @param array  $args  [$submission, $reviewAssignment, $mailable]
     */
    public function sendCertificateEmail(string $hookName, array $args): bool
    {
        [$submission, $reviewAssignment] = $args;

        $request = Application::get()->getRequest();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!$reviewer) {
            return false;
        }

        // Generate and save the static certificate HTML; get its direct URL.
        // This always runs so the certificate stays available on the reviewer
        // dashboard and the My Certificates page even when email is disabled.
        $savedUrl = $this->generateAndSaveCertificate($request, $reviewAssignment, $context);

        // Optional: skip the notification email entirely (certificate is still
        // generated/saved above). Defaults to sending when never configured.
        $sendEmail = $this->getSetting($context->getId(), 'sendEmail');
        if ($sendEmail !== null && $sendEmail !== '' && (string) $sendEmail !== '1') {
            return false;
        }

        // Fall back to the live gateway URL (requires login) if saving failed
        $certificateUrl = $savedUrl ?: $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'generate'],
            ['reviewId' => $reviewAssignment->getId()]
        );

        // Build email
        $fromEmail = $context->getData('contactEmail') ?: '';
        $fromName = $context->getData('contactName') ?: $context->getLocalizedData('name');

        $locale = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl = in_array(substr($locale, 0, 2), $rtlLocales);
        $dir = $isRtl ? 'rtl' : 'ltr';
        $align = $isRtl ? 'right' : 'left';

        $label = __('plugins.generic.reviewerCertificate.email.certificateLabel');
        $notice = __('plugins.generic.reviewerCertificate.email.certificateNotice');
        $subject = __('plugins.generic.reviewerCertificate.email.certificateSubject');

        // Central page listing all of the reviewer's certificates
        $listUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'list']
        );
        $listLabel = __('plugins.generic.reviewerCertificate.certificate.viewAllLink');

        $htmlBody =
            '<!DOCTYPE html>'
            . '<html dir="' . $dir . '">'
            . '<head><meta charset="UTF-8"></head>'
            . '<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;'
            . 'text-align:' . $align . ';direction:' . $dir . ';">'
            . '<p>' . htmlspecialchars($reviewer->getFullName()) . ',</p>'
            . '<p>' . $notice . '</p>'
            . '<p>'
            . '<a href="' . htmlspecialchars($certificateUrl) . '" '
            . 'style="display:inline-block;padding:10px 20px;background:#2d6a9f;color:#fff;'
            . 'text-decoration:none;border-radius:4px;font-weight:bold;">'
            . $label
            . '</a>'
            . '</p>'
            . '<p style="color:#888;font-size:12px;">' . htmlspecialchars($certificateUrl) . '</p>'
            . '<p style="margin-top:1.4rem;font-size:13px;">'
            . '<a href="' . htmlspecialchars($listUrl) . '" '
            . 'style="color:#2d6a9f;text-decoration:underline;">'
            . htmlspecialchars($listLabel)
            . '</a>'
            . '</p>'
            . '</body></html>';

        try {
            Mail::send(
                ['html' => $htmlBody],
                [],
                function ($message) use ($reviewer, $subject, $fromEmail, $fromName) {
                    $message->to($reviewer->getEmail(), $reviewer->getFullName());
                    $message->subject($subject);
                    if ($fromEmail) {
                        $message->from($fromEmail, $fromName);
                    }
                }
            );
        } catch (\Exception $e) {
            error_log('[ReviewerCertificate] Failed to send certificate email: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Render the certificate template to a string, write it to the user's
     * public files directory, and return the public URL.
     *
     * Returns null if the file could not be saved.
     * Public so the gateway plugin can call it to regenerate on-demand.
     */
    /**
     * Per-element visibility toggle keys. Defined here (always-loaded plugin
     * class) so the gateway and save paths can reference it without depending
     * on the lazily-required settings form. Each maps to a "show…" checkbox
     * and a {if $show…} guard in certificate.tpl.
     */
    public static function elementToggleKeys(): array
    {
        return [
            'showLogo',
            'showJournalName',
            'showDividers',
            'showHeading',
            'showSubheading',
            'showPresentedTo',
            'showReviewerName',
            'showBody',
            'showDateLine',
            'showSignatureSection',
        ];
    }

    /**
     * Localized free-text override fields. Each lets the admin replace a
     * fixed label/string on the certificate; left blank, the certificate
     * falls back to the built-in translation (or live data, for the journal
     * name). Multilingual, like editorName/editorTitle/certificateBody.
     */
    public static function textOverrideKeys(): array
    {
        return [
            'journalNameText',
            'headingText',
            'subheadingText',
            'presentedToText',
            'completedOnText',
            'dateLabelText',
        ];
    }

    public function generateAndSaveCertificate($request, $reviewAssignment, $context): ?string
    {
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!$submission || !$reviewer) {
            return null;
        }

        $contextId = $context->getId();
        $reviewId = $reviewAssignment->getId();

        // Locale + direction
        $locale = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl = in_array(substr($locale, 0, 2), $rtlLocales);

        // Core certificate data
        $publication = $submission->getCurrentPublication();
        $submissionTitle = $publication->getLocalizedTitle();
        $reviewerName = $reviewer->getFullName();
        $reviewerAffiliation = $reviewer->getLocalizedAffiliation();
        $journalName = $context->getLocalizedName();
        $dateCompleted = $this->_formatDate($reviewAssignment->getDateCompleted(), $locale, $this->getSetting($contextId, 'dateFormat') ?? 'long', $this->getSetting($contextId, 'dateLocale') ?? '');
        $rawAcknowledged = $reviewAssignment->getDateAcknowledged();
        $dateAcknowledged = $rawAcknowledged
            ? $this->_formatDate($rawAcknowledged, $locale, $this->getSetting($contextId, 'dateFormat') ?? 'long', $this->getSetting($contextId, 'dateLocale') ?? '')
            : $dateCompleted;

        // Plugin settings
        $editorName = $this->getLocalizedSetting($contextId, 'editorName', $locale, '');
        $editorTitle = $this->getLocalizedSetting($contextId, 'editorTitle', $locale, 'Editor-in-Chief');
        $editorNameFontSize = (int) ($this->getSetting($contextId, 'editorNameFontSize') ?: 12);
        $editorNameColor = $this->getSetting($contextId, 'editorNameColor') ?: '#222222';
        $journalNameFontSize = (int) ($this->getSetting($contextId, 'journalNameFontSize') ?: 12);
        $journalNameColor = $this->getSetting($contextId, 'journalNameColor') ?: '#7a6030';
        $signatureSize = (int) ($this->getSetting($contextId, 'signatureSize') ?: 70);
        $logoSize = (int) ($this->getSetting($contextId, 'logoSize') ?: 70);
        $accentColor = $this->getSetting($contextId, 'accentColor') ?: '#b8975a';
        $enableQrCode = (bool) ($this->getSetting($contextId, 'enableQrCode') ?? true);
        $qrSize = (int) ($this->getSetting($contextId, 'qrSize') ?: 68);
        $qrOffsetX = (int) ($this->getSetting($contextId, 'qrOffsetX') ?: 0);
        $qrOffsetY = (int) ($this->getSetting($contextId, 'qrOffsetY') ?: 0);
        $signatureUrl = $this->getSetting($contextId, 'signatureUrl') ?? '';
        $customLogoUrl = $this->getSetting($contextId, 'customLogoUrl') ?? '';
        $backgroundImageUrl = $this->getSetting($contextId, 'backgroundImageUrl') ?? '';

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#b8975a';
        }

        $textColor = $this->getSetting($contextId, 'textColor') ?: '#1a1a2e';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) {
            $textColor = '#1a1a2e';
        }

        // Global vertical shift for all certificate text (− up / + down)
        $contentOffsetY = max(-400, min(400, (int) ($this->getSetting($contextId, 'contentOffsetY') ?: 0)));

        // Per-element visibility (default visible when never configured)
        $elementToggles = [];
        foreach (self::elementToggleKeys() as $toggle) {
            $stored = $this->getSetting($contextId, $toggle);
            $elementToggles[$toggle] = ($stored === null || $stored === '') ? true : ((string) $stored === '1');
        }

        // Localized text overrides (empty => template uses the default)
        $textOverrides = [];
        foreach (self::textOverrideKeys() as $key) {
            $textOverrides[$key] = $this->getLocalizedSetting($contextId, $key, $locale, '');
        }

        // Custom body text
        $certificateBodyRaw = $this->getLocalizedSetting($contextId, 'certificateBody', $locale, '');
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

        // Journal logo
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

        // The live gateway URL — used as the QR code target inside the saved file
        $gatewayUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'generate'],
            ['reviewId' => $reviewId]
        );

        // Render template to string
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'reviewerName' => $reviewerName,
            'reviewerAffiliation' => $reviewerAffiliation,
            'submissionTitle' => $submissionTitle,
            'journalName' => $journalName,
            'dateCompleted' => $dateCompleted,
            'dateAcknowledged' => $dateAcknowledged,
            'reviewId' => $reviewId,
            'editorName' => $editorName,
            'editorTitle' => $editorTitle,
            'editorNameFontSize' => $editorNameFontSize,
            'editorNameColor' => $editorNameColor,
            'journalNameFontSize' => $journalNameFontSize,
            'journalNameColor' => $journalNameColor,
            'signatureSize' => $signatureSize,
            'logoSize' => $logoSize,
            'accentColor' => $accentColor,
            'textColor' => $textColor,
            'enableQrCode' => $enableQrCode,
            'qrSize' => $qrSize,
            'qrOffsetX' => $qrOffsetX,
            'qrOffsetY' => $qrOffsetY,
            'certificateBodyHtml' => $certificateBodyHtml,
            'signatureUrl' => $signatureUrl,
            'logoUrl' => $logoUrl,
            'backgroundImageUrl' => $backgroundImageUrl,
            'contentOffsetY' => $contentOffsetY,
            'isRtl' => $isRtl,
            'currentLocale' => $locale,
            'certificateUrl' => $gatewayUrl,
        ]);
        $templateMgr->assign($elementToggles);
        $templateMgr->assign($textOverrides);

        try {
            $html = $templateMgr->fetch($this->getTemplateResource('certificate.tpl'));
        } catch (\Exception $e) {
            error_log('[ReviewerCertificate] Template render failed: ' . $e->getMessage());
            return null;
        }

        // Build user-specific directory: public/site/images/{username}/
        $safeUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reviewer->getUsername());
        $userDir = 'public/site/images/' . $safeUsername;

        // Ensure the directory exists
        if (!is_dir($userDir)) {
            if (!mkdir($userDir, 0755, true) && !is_dir($userDir)) {
                error_log('[ReviewerCertificate] Could not create user certificate directory: ' . $userDir);
                return null;
            }
        }

        $filename = 'reviewer_cert_' . $reviewId . '.html';
        $filePath = $userDir . '/' . $filename;

        if (file_put_contents($filePath, $html) === false) {
            error_log('[ReviewerCertificate] Could not write certificate file: ' . $filePath);
            return null;
        }

        $url = $request->getBaseUrl() . '/' . $filePath;

        // Remember the saved URL so other parts of the plugin can reference it
        $this->updateSetting($contextId, 'cert_saved_url_' . $reviewId, $url);

        return $url;
    }

    /**
     * Resolve a (possibly) multilingual plugin setting to a single string.
     *
     * Settings written through the settings form are stored as
     * [localeKey => value] arrays. This picks the value for $locale,
     * then the context's primary locale, then the first non-empty value,
     * and finally $default. Plain scalar values (legacy data written
     * before multilingual support) are returned as-is.
     *
     * @param ?int    $contextId Context ID
     * @param string  $name      Setting name
     * @param ?string $locale    Preferred locale (defaults to current UI locale)
     * @param string  $default   Value used when nothing else is available
     */
    public function getLocalizedSetting($contextId, string $name, ?string $locale = null, string $default = ''): string
    {
        $value = $this->getSetting($contextId, $name);
        $locale = $locale ?: Locale::getLocale();

        if (is_array($value)) {
            if (isset($value[$locale]) && $value[$locale] !== '') {
                return $value[$locale];
            }

            $context = Application::getContextDAO()->getById($contextId);
            $primary = $context ? $context->getPrimaryLocale() : null;
            if ($primary && isset($value[$primary]) && $value[$primary] !== '') {
                return $value[$primary];
            }

            foreach ($value as $localized) {
                if (is_string($localized) && $localized !== '') {
                    return $localized;
                }
            }

            return $default;
        }

        return ($value !== null && $value !== '') ? (string) $value : $default;
    }

    /**
     * Whether the active UI locale is written right-to-left.
     */
    private function _isRtlLocale(): bool
    {
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        return in_array(substr(Locale::getLocale(), 0, 2), $rtlLocales);
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
            'd-m-Y' => 'd-m-Y',
            'd/m/Y' => 'd/m/Y',
            'm/d/Y' => 'm/d/Y',
            'Y-m-d' => 'Y-m-d',
            'Y/m/d' => 'Y/m/d',
            'd.m.Y' => 'd.m.Y',
            'Y.m.d' => 'Y.m.d',
            'd F Y' => 'd F Y',
            'F d, Y' => 'F d, Y',
            'j F Y' => 'j F Y',
            'd M Y' => 'd M Y',
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
     * Template hook: inject a "Download Certificate" button on the reviewer's
     * completed review step page (Templates::Reviewer::Review::Step3).
     */
    public function addCertificateLinkToReviewStep(string $hookName, array $args): bool
    {
        /** @var \APP\template\TemplateManager $templateMgr */
        [$templateMgr, &$output] = $args;

        $reviewAssignment = $templateMgr->getTemplateVars('reviewAssignment');
        if (!$reviewAssignment || !$reviewAssignment->getDateCompleted()) {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return false;
        }

        $contextId = $context->getId();
        $reviewId = $reviewAssignment->getId();

        // Prefer the saved static file URL (no login required); fall back to gateway
        $certUrl = $this->getSetting($contextId, 'cert_saved_url_' . $reviewId);
        if (!$certUrl) {
            $certUrl = $request->getDispatcher()->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                null,
                'gateway',
                'plugin',
                ['ReviewerCertificateGatewayPlugin', 'generate'],
                ['reviewId' => $reviewId]
            );
        }

        $label = __('plugins.generic.reviewerCertificate.certificate.downloadLink');

        // Central "all certificates" page for this reviewer
        $listUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'list']
        );
        $listLabel = __('plugins.generic.reviewerCertificate.certificate.viewAllLink');

        $output .= '<div style="margin:1.2rem 0 0;padding:.85rem 1rem;'
            . 'background:#f0f6fb;border:1px solid #c5d9ec;border-radius:5px;display:inline-block;">'
            . '<a href="' . htmlspecialchars($certUrl) . '" target="_blank" '
            . 'style="display:inline-flex;align-items:center;gap:.45rem;padding:7px 18px;'
            . 'background:#2d6a9f;color:#fff;text-decoration:none;border-radius:4px;'
            . 'font-size:13px;font-family:Arial,sans-serif;font-weight:bold;">'
            . '&#127941; ' . htmlspecialchars($label)
            . '</a>'
            . '<a href="' . htmlspecialchars($listUrl) . '" target="_blank" '
            . 'style="display:inline-block;margin-' . ($this->_isRtlLocale() ? 'right' : 'left') . ':.6rem;'
            . 'font-size:13px;font-family:Arial,sans-serif;color:#2d6a9f;text-decoration:underline;">'
            . htmlspecialchars($listLabel)
            . '</a>'
            . '</div>';

        return false;
    }

    /**
     * Hook callback (TemplateManager::display): add a "My Certificates" entry
     * to the reviewer's backend side-navigation, under "Review Assignments".
     *
     * @param array  $args  [$templateMgr, &$template, &$output]
     */
    public function addCertificatesMenuItem(string $hookName, array $args): bool
    {
        /** @var \APP\template\TemplateManager $templateMgr */
        $templateMgr = $args[0];

        // Only backend pages that render the side navigation have a 'menu' state
        $menu = $templateMgr->getState('menu');
        if (!is_array($menu) || empty($menu)) {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user) {
            return false;
        }

        // Show the link only to users who hold the reviewer role here
        if (!$this->_userIsReviewer((int) $user->getId(), (int) $context->getId())) {
            return false;
        }

        $listUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'list']
        );

        $item = [
            'name' => __('plugins.generic.reviewerCertificate.list.title'),
            'url' => $listUrl,
            'isCurrent' => false,
        ];

        // Prefer nesting under the existing "Review Assignments" group;
        // otherwise add a standalone top-level item.
        if (isset($menu['reviewAssignments']['submenu']) && is_array($menu['reviewAssignments']['submenu'])) {
            $menu['reviewAssignments']['submenu']['reviewerCertificates'] = $item;
        } else {
            $item['icon'] = 'ReviewAssignments';
            $menu['reviewerCertificates'] = $item;
        }

        $templateMgr->setState(['menu' => $menu]);

        return false;
    }

    /**
     * Whether the user holds the reviewer role in the given context.
     */
    private function _userIsReviewer(int $userId, int $contextId): bool
    {
        $userGroups = UserGroup::withUserIds([$userId])
            ->withContextIds([$contextId])
            ->get();

        foreach ($userGroups as $userGroup) {
            if ((int) $userGroup->roleId === Role::ROLE_ID_REVIEWER) {
                return true;
            }
        }

        return false;
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.reviewerCertificate.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.reviewerCertificate.description');
    }

    /**
     * Add a Settings action button in the plugin list.
     */
    public function getActions($request, $verb): array
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url(
                            $request,
                            null,
                            null,
                            'manage',
                            null,
                            ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']
                        ),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
    }

    /**
     * Handle settings modal requests.
     */
    public function manage($args, $request): JSONMessage
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                if (!class_exists(ReviewerCertificateSettingsForm::class, false)) {
                    require_once __DIR__ . '/ReviewerCertificateSettingsForm.php';
                }
                $form = new ReviewerCertificateSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Provide the install migration that creates the plugin's tables.
     * OJS runs this automatically when the plugin is installed.
     */
    public function getInstallMigration()
    {
        require_once __DIR__ . '/classes/migration/ReviewerCertificateInstallMigration.php';
        return new \APP\plugins\generic\reviewerCertificate\classes\migration\ReviewerCertificateInstallMigration();
    }
}
