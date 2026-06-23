<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificateSettingsForm.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReviewerCertificateSettingsForm
 *
 * @brief Settings form for the Reviewer Certificate plugin.
 */

namespace APP\plugins\generic\reviewerCertificate;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class ReviewerCertificateSettingsForm extends Form
{
    protected int $_journalId;
    protected ReviewerCertificatePlugin $_plugin;
    protected int $templateId;

    public function __construct(ReviewerCertificatePlugin $plugin, int $journalId, ?int $templateId = null)
    {
        $this->_journalId = $journalId;
        $this->_plugin = $plugin;

        if ($templateId !== null && $templateId > 0) {
            $this->templateId = $templateId;
        } else {
            /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao */
            $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');
            $defaultTemplate = $templateDao->getDefault($journalId);
            if ($defaultTemplate !== null) {
                $this->templateId = (int) $defaultTemplate->getTemplateId();
            } else {
                // No default template exists yet — lazily create one so that
                // settings saved by this form always land on a valid row.
                $t = $templateDao->newDataObject();
                $t->setContextId($journalId);
                $t->setTemplateName('Default');
                $t->setLayout('certificate');
                $t->setIsDefault(1);
                $t->setEnabled(1);
                $t->setDateCreated(\PKP\core\Core::getCurrentDate());
                $this->templateId = $templateDao->insertObject($t);
            }
        }

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        $id = $this->_journalId;
        $tid = $this->templateId;

        /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao */
        $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');

        $this->setData('editorName', $this->_getLocalizedTemplateSetting($templateDao, $tid, 'editorName'));
        $this->setData('editorTitle', $this->_getLocalizedTemplateSetting($templateDao, $tid, 'editorTitle', 'Editor-in-Chief'));
        $this->setData('editorNameFontSize', $this->_getTemplateSetting($templateDao, $tid, 'editorNameFontSize') ?: '12');
        $this->setData('editorNameColor', $this->_getTemplateSetting($templateDao, $tid, 'editorNameColor') ?: '#222222');
        $this->setData('journalNameFontSize', $this->_getTemplateSetting($templateDao, $tid, 'journalNameFontSize') ?: '12');
        $this->setData('journalNameColor', $this->_getTemplateSetting($templateDao, $tid, 'journalNameColor') ?: '#7a6030');
        $this->setData('signatureSize', $this->_getTemplateSetting($templateDao, $tid, 'signatureSize') ?: '70');
        $this->setData('logoSize', $this->_getTemplateSetting($templateDao, $tid, 'logoSize') ?: '70');
        $this->setData('signatureSectionOffsetY', $this->_getTemplateSetting($templateDao, $tid, 'signatureSectionOffsetY') ?: '0');
        $this->setData('signatureSectionPaddingTop', $this->_getTemplateSetting($templateDao, $tid, 'signatureSectionPaddingTop') ?: '0');
        $this->setData('signatureSectionGap', $this->_getTemplateSetting($templateDao, $tid, 'signatureSectionGap') ?: '80');
        $this->setData('editorBlockOffsetX', $this->_getTemplateSetting($templateDao, $tid, 'editorBlockOffsetX') ?: '0');
        $this->setData('editorBlockOffsetY', $this->_getTemplateSetting($templateDao, $tid, 'editorBlockOffsetY') ?: '0');
        $this->setData('dateBlockOffsetX', $this->_getTemplateSetting($templateDao, $tid, 'dateBlockOffsetX') ?: '0');
        $this->setData('dateBlockOffsetY', $this->_getTemplateSetting($templateDao, $tid, 'dateBlockOffsetY') ?: '0');
        $this->setData('contentOffsetY', $this->_getTemplateSetting($templateDao, $tid, 'contentOffsetY') ?: '0');
        $this->setData('qrSize', $this->_getTemplateSetting($templateDao, $tid, 'qrSize') ?: '68');
        $this->setData('qrOffsetX', $this->_getTemplateSetting($templateDao, $tid, 'qrOffsetX') ?: '0');
        $this->setData('qrOffsetY', $this->_getTemplateSetting($templateDao, $tid, 'qrOffsetY') ?: '0');

        // Element visibility toggles. Default to shown (1) when never saved.
        foreach (self::elementToggleKeys() as $toggle) {
            $stored = $this->_getTemplateSetting($templateDao, $tid, $toggle);
            $this->setData($toggle, ($stored === null || $stored === '') ? '1' : (string) $stored);
        }

        // Localized text overrides (blank => default used on the certificate)
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $this->setData($key, $this->_getLocalizedTemplateSetting($templateDao, $tid, $key));
        }

        $this->setData('accentColor', $this->_getTemplateSetting($templateDao, $tid, 'accentColor') ?: '#b8975a');
        $this->setData('textColor', $this->_getTemplateSetting($templateDao, $tid, 'textColor') ?: '#1a1a2e');
        $this->setData('certificateBody', $this->_getLocalizedTemplateSetting($templateDao, $tid, 'certificateBody'));
        $this->setData('sendEmail', $this->_getTemplateSetting($templateDao, $tid, 'sendEmail') ?? '1');
        $this->setData('enableQrCode', $this->_getTemplateSetting($templateDao, $tid, 'enableQrCode') ?? '1');
        $this->setData('dateFormat', $this->_getTemplateSetting($templateDao, $tid, 'dateFormat') ?: 'long');
        $this->setData('dateLocale', $this->_getTemplateSetting($templateDao, $tid, 'dateLocale') ?? '');
        $this->setData('wkhtmltopdfPath', $this->_plugin->getSetting($id, 'wkhtmltopdfPath') ?? '');
        $this->setData('signatureUrl', $this->_getTemplateSetting($templateDao, $tid, 'signatureUrl'));
        $this->setData('customLogoUrl', $this->_getTemplateSetting($templateDao, $tid, 'customLogoUrl'));
        $this->setData('backgroundImageUrl', $this->_getTemplateSetting($templateDao, $tid, 'backgroundImageUrl'));
    }

    /**
     * Fields that hold one value per supported form locale.
     */
    public function getLocaleFieldNames(): array
    {
        return array_merge(
            ['editorName', 'editorTitle', 'certificateBody'],
            ReviewerCertificatePlugin::textOverrideKeys()
        );
    }

    /**
     * Per-element visibility toggles. Delegates to the parent plugin so the
     * key list has a single source of truth.
     */
    public static function elementToggleKeys(): array
    {
        return ReviewerCertificatePlugin::elementToggleKeys();
    }

    public function readInputData(): void
    {
        $this->readUserVars([
            ...ReviewerCertificatePlugin::textOverrideKeys(),
            'editorName',
            'editorTitle',
            'editorNameFontSize',
            'editorNameColor',
            'journalNameFontSize',
            'journalNameColor',
            'signatureSize',
            'logoSize',
            'signatureSectionOffsetY',
            'signatureSectionPaddingTop',
            'signatureSectionGap',
            'editorBlockOffsetX',
            'editorBlockOffsetY',
            'dateBlockOffsetX',
            'dateBlockOffsetY',
            'contentOffsetY',
            'qrSize',
            'qrOffsetX',
            'qrOffsetY',
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
            'accentColor',
            'textColor',
            'certificateBody',
            'sendEmail',
            'enableQrCode',
            'dateFormat',
            'dateLocale',
            'wkhtmltopdfPath',
            'signatureUrl',
            'customLogoUrl',
            'backgroundImageUrl',
            'signatureTemporaryFileId',
            'logoTemporaryFileId',
            'backgroundTemporaryFileId',
        ]);
    }

    public function fetch($request, $template = null, $display = false): string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->_plugin->getName());

        // URL for the PKP temporary files API (used by the upload widget)
        $context = $request->getContext();
        $supportedLocales = $context->getSupportedFormLocales();
        $templateMgr->assign('supportedLocales', $supportedLocales);

        $tid = $this->templateId;

        /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao */
        $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');

        foreach (array_merge(['editorName', 'editorTitle', 'certificateBody'], ReviewerCertificatePlugin::textOverrideKeys()) as $field) {
            $raw = $this->_getLocalizedTemplateSetting($templateDao, $tid, $field);
            $localized = is_array($raw) ? $raw : [];
            if (!is_array($raw) && $raw !== null && $raw !== '') {
                foreach (array_keys($supportedLocales) as $loc) {
                    if (!isset($localized[$loc])) {
                        $localized[$loc] = $raw;
                    }
                }
            }
            $templateMgr->assign($field . 'Localized', $localized);
        }
        $temporaryFileApiUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_API,
            $context->getPath(),
            'temporaryFiles'
        );
        $templateMgr->assign('temporaryFileApiUrl', $temporaryFileApiUrl);

        // Detect wkhtmltopdf for the settings form status indicator
        $wkhtmltopdfDetected = $this->_detectWkhtmltopdf(
            $this->_plugin->getSetting($this->_journalId, 'wkhtmltopdfPath') ?? ''
        );
        $templateMgr->assign('wkhtmltopdfDetected', $wkhtmltopdfDetected);

        // Build the base certificate preview URL (reviewId appended via JS)
        $previewBaseUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getPath(),
            'gateway',
            'plugin',
            ['ReviewerCertificateGatewayPlugin', 'generate']
        );
        $templateMgr->assign('previewBaseUrl', $previewBaseUrl);

        // Try to find a real completed review ID for a useful default
        $sampleReviewId = 1;
        try {
            $reviewAssignmentDao = \PKP\db\DAORegistry::getDAO('ReviewAssignmentDAO');
            $result = $reviewAssignmentDao->retrieve(
                'SELECT review_id FROM review_assignments
                  WHERE context_id = ? AND date_completed IS NOT NULL
                  ORDER BY review_id DESC LIMIT 1',
                [$this->_journalId]
            );
            if ($row = $result->current()) {
                $sampleReviewId = (int) $row->review_id;
            }
        } catch (\Exception $e) {
            // Fall back to 1
        }
        $templateMgr->assign('sampleReviewId', $sampleReviewId);

        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs): void
    {
        $id = $this->_journalId;
        $p = $this->_plugin;
        $tid = $this->templateId;

        /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao */
        $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');

        // Localized text fields — stored per locale as individual setting rows
        $supportedLocales = Locale::getSupportedFormLocales();

        $editorNameData = $this->getData('editorName');
        $editorNameArr = is_array($editorNameData) ? $editorNameData : [];
        foreach (array_keys($supportedLocales) as $locale) {
            $templateDao->upsertSetting($tid, 'editorName', $editorNameArr[$locale] ?? '', 'string', $locale);
        }

        $editorTitleData = $this->getData('editorTitle');
        $editorTitleArr = is_array($editorTitleData) ? $editorTitleData : [];
        foreach (array_keys($supportedLocales) as $locale) {
            $templateDao->upsertSetting($tid, 'editorTitle', $editorTitleArr[$locale] ?? '', 'string', $locale);
        }

        // Localized per-element text overrides (blank => default on render)
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $data = $this->getData($key);
            $arr = is_array($data) ? $data : [];
            foreach (array_keys($supportedLocales) as $locale) {
                $templateDao->upsertSetting($tid, $key, $arr[$locale] ?? '', 'string', $locale);
            }
        }

        $fontSize = (int) $this->getData('editorNameFontSize');
        $templateDao->upsertSetting($tid, 'editorNameFontSize', ($fontSize >= 8 && $fontSize <= 72) ? (string) $fontSize : '12');

        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->getData('editorNameColor'))
            ? $this->getData('editorNameColor') : '#222222';
        $templateDao->upsertSetting($tid, 'editorNameColor', $color);

        $journalFontSize = (int) $this->getData('journalNameFontSize');
        $templateDao->upsertSetting($tid, 'journalNameFontSize', ($journalFontSize >= 8 && $journalFontSize <= 72) ? (string) $journalFontSize : '12');

        $journalColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->getData('journalNameColor'))
            ? $this->getData('journalNameColor') : '#7a6030';
        $templateDao->upsertSetting($tid, 'journalNameColor', $journalColor);

        $signatureSize = (int) $this->getData('signatureSize');
        $templateDao->upsertSetting($tid, 'signatureSize', (string) (($signatureSize >= 20 && $signatureSize <= 300) ? $signatureSize : 70));

        $logoSize = (int) $this->getData('logoSize');
        $templateDao->upsertSetting($tid, 'logoSize', (string) (($logoSize >= 20 && $logoSize <= 300) ? $logoSize : 70));

        // Signature-section layout offsets. Y/X offsets accept negatives
        // (move up / left); padding-top and gap are non-negative.
        $clamp = fn ($v, $min, $max, $default) => (($n = (int) $v) >= $min && $n <= $max) ? $n : $default;
        $templateDao->upsertSetting($tid, 'signatureSectionOffsetY', (string) $clamp($this->getData('signatureSectionOffsetY'), -400, 400, 0));
        $templateDao->upsertSetting($tid, 'signatureSectionPaddingTop', (string) $clamp($this->getData('signatureSectionPaddingTop'), 0, 400, 0));
        $templateDao->upsertSetting($tid, 'signatureSectionGap', (string) $clamp($this->getData('signatureSectionGap'), 0, 400, 80));
        $templateDao->upsertSetting($tid, 'editorBlockOffsetX', (string) $clamp($this->getData('editorBlockOffsetX'), -400, 400, 0));
        $templateDao->upsertSetting($tid, 'editorBlockOffsetY', (string) $clamp($this->getData('editorBlockOffsetY'), -400, 400, 0));
        $templateDao->upsertSetting($tid, 'dateBlockOffsetX', (string) $clamp($this->getData('dateBlockOffsetX'), -400, 400, 0));
        $templateDao->upsertSetting($tid, 'dateBlockOffsetY', (string) $clamp($this->getData('dateBlockOffsetY'), -400, 400, 0));

        // Global vertical shift for all certificate text (− up / + down)
        $templateDao->upsertSetting($tid, 'contentOffsetY', (string) $clamp($this->getData('contentOffsetY'), -400, 400, 0));

        $qrSize = (int) $this->getData('qrSize');
        $templateDao->upsertSetting($tid, 'qrSize', (string) (($qrSize >= 20 && $qrSize <= 300) ? $qrSize : 68));
        $templateDao->upsertSetting($tid, 'qrOffsetX', (string) $clamp($this->getData('qrOffsetX'), -400, 400, 0));
        $templateDao->upsertSetting($tid, 'qrOffsetY', (string) $clamp($this->getData('qrOffsetY'), -400, 400, 0));

        // Element visibility toggles (unchecked checkbox => not submitted => hide)
        foreach (self::elementToggleKeys() as $toggle) {
            $templateDao->upsertSetting($tid, $toggle, $this->getData($toggle) ? '1' : '0');
        }

        $accentColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->getData('accentColor'))
            ? $this->getData('accentColor') : '#b8975a';
        $templateDao->upsertSetting($tid, 'accentColor', $accentColor);

        $textColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->getData('textColor'))
            ? $this->getData('textColor') : '#1a1a2e';
        $templateDao->upsertSetting($tid, 'textColor', $textColor);

        // Certificate body: store raw text per locale (placeholders replaced at render time)
        $certificateBodyData = $this->getData('certificateBody');
        $certificateBodyArr = is_array($certificateBodyData) ? $certificateBodyData : [];
        foreach (array_keys($supportedLocales) as $locale) {
            $templateDao->upsertSetting($tid, 'certificateBody', $certificateBodyArr[$locale] ?? '', 'string', $locale);
        }

        $templateDao->upsertSetting($tid, 'sendEmail', $this->getData('sendEmail') ? '1' : '0');
        $templateDao->upsertSetting($tid, 'enableQrCode', $this->getData('enableQrCode') ? '1' : '0');

        $allowedFormats = ['long', 'medium', 'short', 'Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d', 'd F Y', 'F d, Y', 'j F Y', 'd M Y', 'M d, Y'];
        $dateFormat = $this->getData('dateFormat') ?: 'long';
        $templateDao->upsertSetting($tid, 'dateFormat', in_array($dateFormat, $allowedFormats) ? $dateFormat : 'long');

        $allowedLocales = ['','ar','ar_IQ','ar_SA','ar_EG','ar_AE','ar_KW','ar_BH','ar_QA','ar_OM','ar_JO','ar_LB','ar_SY','ar_PS','ar_MA','ar_DZ','ar_TN','ar_LY','ar_SD','ar_YE','en','en_US','en_GB','en_AU','en_CA','fr','fr_FR','fr_CA','de','de_DE','es','es_ES','tr','tr_TR','fa','fa_IR','ku','ckb'];
        $dateLocale = trim($this->getData('dateLocale') ?? '');
        $templateDao->upsertSetting($tid, 'dateLocale', in_array($dateLocale, $allowedLocales) ? $dateLocale : '');

        // wkhtmltopdf path: system-level binary path — stays in plugin_settings
        $p->updateSetting($id, 'wkhtmltopdfPath', trim($this->getData('wkhtmltopdfPath') ?? ''));

        $templateDao->upsertSetting($tid, 'signatureUrl', (string) ($this->getData('signatureUrl') ?? ''));
        $templateDao->upsertSetting($tid, 'customLogoUrl', (string) ($this->getData('customLogoUrl') ?? ''));
        $templateDao->upsertSetting($tid, 'backgroundImageUrl', (string) ($this->getData('backgroundImageUrl') ?? ''));

        // Process any uploaded temporary files (these override the URL text fields)
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $publicFileManager = new PublicFileManager();
        $temporaryFileManager = new TemporaryFileManager();
        /** @var \PKP\file\TemporaryFileDAO $temporaryFileDao */
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');

        foreach (['signature' => 'signatureUrl', 'logo' => 'customLogoUrl', 'background' => 'backgroundImageUrl'] as $fileType => $settingKey) {
            $tempFileId = (int) $this->getData($fileType . 'TemporaryFileId');
            if (!$tempFileId) {
                continue;
            }

            $temporaryFile = $temporaryFileDao->getTemporaryFile($tempFileId, $user->getId());
            if (!$temporaryFile) {
                continue;
            }

            $ext = strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION)) ?: 'jpg';

            // Unique filename per upload. A fixed name (e.g.
            // reviewer_cert_signature.jpg) keeps the same URL across
            // re-uploads, so browsers and wkhtmltopdf serve the stale
            // cached copy. A fresh name changes the URL and busts the cache.
            $filename = 'reviewer_cert_' . $fileType . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;

            if ($publicFileManager->copyContextFile($id, $temporaryFile->getFilePath(), $filename)) {
                // Remove the previously stored managed file so the public
                // directory does not accumulate orphaned uploads.
                $oldUrl = (string) ($this->_getTemplateSetting($templateDao, $tid, $settingKey) ?? '');
                if ($oldUrl !== '') {
                    $oldName = basename(parse_url($oldUrl, PHP_URL_PATH) ?: '');
                    if ($oldName !== '' && $oldName !== $filename && strpos($oldName, 'reviewer_cert_' . $fileType . '_') === 0) {
                        $publicFileManager->removeContextFile($id, $oldName);
                    }
                }

                $url = $request->getBaseUrl()
                    . '/' . $publicFileManager->getContextFilesPath($id)
                    . '/' . $filename;
                $templateDao->upsertSetting($tid, $settingKey, $url);
            }

            $temporaryFileManager->deleteById($tempFileId, $user->getId());
        }

        parent::execute(...$functionArgs);
    }

    private function _detectWkhtmltopdf(string $configured): string
    {
        if ($configured && is_executable($configured)) {
            return $configured;
        }
        foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/opt/homebrew/bin/wkhtmltopdf'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        $which = trim((string) shell_exec('which wkhtmltopdf 2>/dev/null'));
        return ($which && is_executable($which)) ? $which : '';
    }

    /**
     * Read a non-localized setting value from the template settings table.
     * Returns null when not yet saved.
     */
    private function _getTemplateSetting(
        \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao,
        int $templateId,
        string $name
    ): ?string {
        if ($templateId <= 0) {
            return null;
        }
        $row = $templateDao->getSettingRow($templateId, $name, '');
        return $row ? $row['setting_value'] : null;
    }

    /**
     * Read a localized setting from the template settings table and return a
     * [locale => value] map suitable for multilingual form widgets.
     *
     * When no per-locale rows exist, falls back to the supplied $default for
     * every supported form locale.
     */
    private function _getLocalizedTemplateSetting(
        \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao,
        int $templateId,
        string $name,
        string $default = ''
    ): array {
        $supportedLocales = Locale::getSupportedFormLocales();
        $result = [];

        if ($templateId > 0) {
            foreach (array_keys($supportedLocales) as $locale) {
                $row = $templateDao->getSettingRow($templateId, $name, $locale);
                $result[$locale] = $row ? $row['setting_value'] : $default;
            }
        } else {
            foreach (array_keys($supportedLocales) as $locale) {
                $result[$locale] = $default;
            }
        }

        return $result;
    }
}
