<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificatePlugin.php
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

class ReviewerCertificatePlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        $this->addLocaleData();

        if ($this->getEnabled($mainContextId)) {
            PluginRegistry::register(
                'gateways',
                new ReviewerCertificateGatewayPlugin($this),
                $this->getPluginPath()
            );

            Hook::add('ThankReviewerForm::thankReviewer', $this->sendCertificateEmail(...));
            Hook::add('Templates::Reviewer::Review::Step3', $this->addCertificateLinkToReviewStep(...));
        }

        return true;
    }

    /**
     * Hook callback: generate the certificate file, save it, and email the reviewer.
     *
     * @param string $hookName
     * @param array  $args  [$submission, $reviewAssignment, $mailable]
     */
    public function sendCertificateEmail(string $hookName, array $args): bool
    {
        [$submission, $reviewAssignment] = $args;

        $request    = Application::get()->getRequest();
        $contextDao = Application::getContextDAO();
        $context    = $contextDao->getById($submission->getData('contextId'));

        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!$reviewer) {
            return false;
        }

        // Generate and save the static certificate HTML; get its direct URL
        $savedUrl = $this->generateAndSaveCertificate($request, $reviewAssignment, $context);

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
        $fromName  = $context->getData('contactName')  ?: $context->getLocalizedData('name');

        $locale     = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl      = in_array(substr($locale, 0, 2), $rtlLocales);
        $dir        = $isRtl ? 'rtl' : 'ltr';
        $align      = $isRtl ? 'right' : 'left';

        $label   = __('plugins.generic.reviewerCertificate.email.certificateLabel');
        $notice  = __('plugins.generic.reviewerCertificate.email.certificateNotice');
        $subject = __('plugins.generic.reviewerCertificate.email.certificateSubject');

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
    public function generateAndSaveCertificate($request, $reviewAssignment, $context): ?string
    {
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $reviewer   = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!$submission || !$reviewer) {
            return null;
        }

        $contextId = $context->getId();
        $reviewId  = $reviewAssignment->getId();

        // Locale + direction
        $locale     = Locale::getLocale();
        $rtlLocales = ['ar', 'fa', 'he', 'ur', 'ckb', 'ps'];
        $isRtl      = in_array(substr($locale, 0, 2), $rtlLocales);

        // Core certificate data
        $publication      = $submission->getCurrentPublication();
        $submissionTitle  = $publication->getLocalizedTitle();
        $reviewerName     = $reviewer->getFullName();
        $journalName      = $context->getLocalizedName();
        $dateCompleted    = $this->_formatDate($reviewAssignment->getDateCompleted(), $locale);
        $rawAcknowledged  = $reviewAssignment->getDateAcknowledged();
        $dateAcknowledged = $rawAcknowledged
            ? $this->_formatDate($rawAcknowledged, $locale)
            : $dateCompleted;

        // Plugin settings
        $editorName         = $this->getSetting($contextId, 'editorName') ?? '';
        $editorTitle        = $this->getSetting($contextId, 'editorTitle') ?: 'Editor-in-Chief';
        $editorNameFontSize = (int) ($this->getSetting($contextId, 'editorNameFontSize') ?: 12);
        $editorNameColor    = $this->getSetting($contextId, 'editorNameColor') ?: '#222222';
        $journalNameFontSize = (int) ($this->getSetting($contextId, 'journalNameFontSize') ?: 12);
        $journalNameColor   = $this->getSetting($contextId, 'journalNameColor') ?: '#7a6030';
        $signatureSize      = (int) ($this->getSetting($contextId, 'signatureSize') ?: 70);
        $logoSize           = (int) ($this->getSetting($contextId, 'logoSize') ?: 70);
        $accentColor        = $this->getSetting($contextId, 'accentColor') ?: '#b8975a';
        $enableQrCode       = (bool) ($this->getSetting($contextId, 'enableQrCode') ?? true);
        $signatureUrl       = $this->getSetting($contextId, 'signatureUrl') ?? '';
        $customLogoUrl      = $this->getSetting($contextId, 'customLogoUrl') ?? '';
        $backgroundImageUrl = $this->getSetting($contextId, 'backgroundImageUrl') ?? '';

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#b8975a';
        }

        // Custom body text
        $certificateBodyRaw  = $this->getSetting($contextId, 'certificateBody') ?? '';
        $certificateBodyHtml = $certificateBodyRaw
            ? str_replace(
                ['{journalName}', '{submissionTitle}'],
                [
                    '<em>' . htmlspecialchars($journalName, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</em>',
                    '<strong>' . htmlspecialchars($submissionTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</strong>',
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
            'reviewerName'        => $reviewerName,
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
            'accentColor'         => $accentColor,
            'enableQrCode'        => $enableQrCode,
            'certificateBodyHtml' => $certificateBodyHtml,
            'signatureUrl'        => $signatureUrl,
            'logoUrl'             => $logoUrl,
            'backgroundImageUrl'  => $backgroundImageUrl,
            'isRtl'               => $isRtl,
            'currentLocale'       => $locale,
            'certificateUrl'      => $gatewayUrl,
        ]);

        try {
            $html = $templateMgr->fetch($this->getTemplateResource('certificate.tpl'));
        } catch (\Exception $e) {
            error_log('[ReviewerCertificate] Template render failed: ' . $e->getMessage());
            return null;
        }

        // Build user-specific directory: public/site/images/{username}/
        $safeUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reviewer->getUsername());
        $userDir      = 'public/site/images/' . $safeUsername;

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
     * Format a date string using the active locale (IntlDateFormatter when available).
     */
    private function _formatDate(string $dateStr, string $locale): string
    {
        $timestamp = strtotime($dateStr);
        if (!$timestamp) {
            return $dateStr;
        }
        if (class_exists('\IntlDateFormatter')) {
            $fmt = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
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

        $request   = Application::get()->getRequest();
        $context   = $request->getContext();
        if (!$context) {
            return false;
        }

        $contextId = $context->getId();
        $reviewId  = $reviewAssignment->getId();

        // Prefer the saved static file URL (no login required); fall back to gateway
        $certUrl = $this->getSetting($contextId, 'cert_saved_url_' . $reviewId);
        if (!$certUrl) {
            $certUrl = $request->getDispatcher()->url(
                $request, PKPApplication::ROUTE_PAGE, null,
                'gateway', 'plugin',
                ['ReviewerCertificateGatewayPlugin', 'generate'],
                ['reviewId' => $reviewId]
            );
        }

        $label = __('plugins.generic.reviewerCertificate.certificate.downloadLink');

        $output .= '<div style="margin:1.2rem 0 0;padding:.85rem 1rem;'
            . 'background:#f0f6fb;border:1px solid #c5d9ec;border-radius:5px;display:inline-block;">'
            . '<a href="' . htmlspecialchars($certUrl) . '" target="_blank" '
            . 'style="display:inline-flex;align-items:center;gap:.45rem;padding:7px 18px;'
            . 'background:#2d6a9f;color:#fff;text-decoration:none;border-radius:4px;'
            . 'font-size:13px;font-family:Arial,sans-serif;font-weight:bold;">'
            . '&#127941; ' . htmlspecialchars($label)
            . '</a>'
            . '</div>';

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
                            $request, null, null, 'manage', null,
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
}
