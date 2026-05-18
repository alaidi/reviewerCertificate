<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificateSettingsForm.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

    public function __construct(ReviewerCertificatePlugin $plugin, int $journalId)
    {
        $this->_journalId = $journalId;
        $this->_plugin    = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        $id = $this->_journalId;
        $p  = $this->_plugin;

        $this->setData('editorName',          $this->_getLocalizedSetting($p, $id, 'editorName'));
        $this->setData('editorTitle',         $this->_getLocalizedSetting($p, $id, 'editorTitle', 'Editor-in-Chief'));
        $this->setData('editorNameFontSize',  $p->getSetting($id, 'editorNameFontSize') ?: '12');
        $this->setData('editorNameColor',     $p->getSetting($id, 'editorNameColor') ?: '#222222');
        $this->setData('journalNameFontSize', $p->getSetting($id, 'journalNameFontSize') ?: '12');
        $this->setData('journalNameColor',    $p->getSetting($id, 'journalNameColor') ?: '#7a6030');
        $this->setData('signatureSize',       $p->getSetting($id, 'signatureSize') ?: '70');
        $this->setData('logoSize',            $p->getSetting($id, 'logoSize') ?: '70');
        $this->setData('signatureSectionOffsetY',    $p->getSetting($id, 'signatureSectionOffsetY') ?: '0');
        $this->setData('signatureSectionPaddingTop', $p->getSetting($id, 'signatureSectionPaddingTop') ?: '0');
        $this->setData('signatureSectionGap',        $p->getSetting($id, 'signatureSectionGap') ?: '80');
        $this->setData('editorBlockOffsetX',  $p->getSetting($id, 'editorBlockOffsetX') ?: '0');
        $this->setData('editorBlockOffsetY',  $p->getSetting($id, 'editorBlockOffsetY') ?: '0');
        $this->setData('dateBlockOffsetX',    $p->getSetting($id, 'dateBlockOffsetX') ?: '0');
        $this->setData('dateBlockOffsetY',    $p->getSetting($id, 'dateBlockOffsetY') ?: '0');
        $this->setData('contentOffsetY',      $p->getSetting($id, 'contentOffsetY') ?: '0');
        $this->setData('qrSize',              $p->getSetting($id, 'qrSize') ?: '68');
        $this->setData('qrOffsetX',           $p->getSetting($id, 'qrOffsetX') ?: '0');
        $this->setData('qrOffsetY',           $p->getSetting($id, 'qrOffsetY') ?: '0');

        // Element visibility toggles. Default to shown (1) when never saved.
        foreach (self::elementToggleKeys() as $toggle) {
            $stored = $p->getSetting($id, $toggle);
            $this->setData($toggle, ($stored === null || $stored === '') ? '1' : (string) $stored);
        }

        // Localized text overrides (blank => default used on the certificate)
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $this->setData($key, $this->_getLocalizedSetting($p, $id, $key));
        }

        $this->setData('accentColor',         $p->getSetting($id, 'accentColor') ?: '#b8975a');
        $this->setData('textColor',           $p->getSetting($id, 'textColor') ?: '#1a1a2e');
        $this->setData('certificateBody',     $this->_getLocalizedSetting($p, $id, 'certificateBody'));
        $this->setData('enableQrCode',        $p->getSetting($id, 'enableQrCode') ?? '1');
        $this->setData('dateFormat',          $p->getSetting($id, 'dateFormat') ?: 'long');
        $this->setData('dateLocale',          $p->getSetting($id, 'dateLocale') ?? '');
        $this->setData('wkhtmltopdfPath',     $p->getSetting($id, 'wkhtmltopdfPath') ?? '');
        $this->setData('signatureUrl',        $p->getSetting($id, 'signatureUrl'));
        $this->setData('customLogoUrl',       $p->getSetting($id, 'customLogoUrl'));
        $this->setData('backgroundImageUrl',  $p->getSetting($id, 'backgroundImageUrl'));
    }

    /**
     * Normalize a stored setting into a [localeKey => value] array so the
     * multilingual form widgets render correctly.
     *
     * A legacy scalar value (saved before multilingual support) is
     * pre-filled into every supported form locale rather than being
     * guessed onto a single locale: the language of free text cannot be
     * reliably inferred, and a wrong guess silently mislabels the value
     * (e.g. Arabic text tagged as English). The admin then corrects each
     * language box explicitly.
     */
    private function _toLocalized($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }

        $localized = [];
        foreach (array_keys($this->supportedLocales ?? []) as $localeKey) {
            $localized[$localeKey] = (string) $value;
        }
        return $localized;
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

        $id = $this->_journalId;
        $p  = $this->_plugin;
        foreach (array_merge(['editorName', 'editorTitle', 'certificateBody'], ReviewerCertificatePlugin::textOverrideKeys()) as $field) {
            $raw = $p->getSetting($id, $field);
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
        $p  = $this->_plugin;

        // Localized text fields (one value per supported locale)
        $editorNameData = $this->getData('editorName');
        $p->updateSetting($id, 'editorName',  is_array($editorNameData) ? $editorNameData : [], 'object');
        $editorTitleData = $this->getData('editorTitle');
        $p->updateSetting($id, 'editorTitle', is_array($editorTitleData) ? $editorTitleData : [], 'object');

        // Localized per-element text overrides (blank => default on render)
        foreach (ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $data = $this->getData($key);
            $p->updateSetting($id, $key, is_array($data) ? $data : [], 'object');
        }

        $fontSize = (int) $this->getData('editorNameFontSize');
        $p->updateSetting($id, 'editorNameFontSize', ($fontSize >= 8 && $fontSize <= 72) ? $fontSize : 12);

        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $this->getData('editorNameColor'))
            ? $this->getData('editorNameColor') : '#222222';
        $p->updateSetting($id, 'editorNameColor', $color);

        $journalFontSize = (int) $this->getData('journalNameFontSize');
        $p->updateSetting($id, 'journalNameFontSize', ($journalFontSize >= 8 && $journalFontSize <= 72) ? $journalFontSize : 12);

        $journalColor = preg_match('/^#[0-9a-fA-F]{6}$/', $this->getData('journalNameColor'))
            ? $this->getData('journalNameColor') : '#7a6030';
        $p->updateSetting($id, 'journalNameColor', $journalColor);

        $signatureSize = (int) $this->getData('signatureSize');
        $p->updateSetting($id, 'signatureSize', ($signatureSize >= 20 && $signatureSize <= 300) ? $signatureSize : 70);

        $logoSize = (int) $this->getData('logoSize');
        $p->updateSetting($id, 'logoSize', ($logoSize >= 20 && $logoSize <= 300) ? $logoSize : 70);

        // Signature-section layout offsets. Y/X offsets accept negatives
        // (move up / left); padding-top and gap are non-negative.
        $clamp = fn ($v, $min, $max, $default) => (($n = (int) $v) >= $min && $n <= $max) ? $n : $default;
        $p->updateSetting($id, 'signatureSectionOffsetY',    $clamp($this->getData('signatureSectionOffsetY'), -400, 400, 0));
        $p->updateSetting($id, 'signatureSectionPaddingTop', $clamp($this->getData('signatureSectionPaddingTop'), 0, 400, 0));
        $p->updateSetting($id, 'signatureSectionGap',        $clamp($this->getData('signatureSectionGap'), 0, 400, 80));
        $p->updateSetting($id, 'editorBlockOffsetX',         $clamp($this->getData('editorBlockOffsetX'), -400, 400, 0));
        $p->updateSetting($id, 'editorBlockOffsetY',         $clamp($this->getData('editorBlockOffsetY'), -400, 400, 0));
        $p->updateSetting($id, 'dateBlockOffsetX',           $clamp($this->getData('dateBlockOffsetX'), -400, 400, 0));
        $p->updateSetting($id, 'dateBlockOffsetY',           $clamp($this->getData('dateBlockOffsetY'), -400, 400, 0));

        // Global vertical shift for all certificate text (− up / + down)
        $p->updateSetting($id, 'contentOffsetY',             $clamp($this->getData('contentOffsetY'), -400, 400, 0));

        $qrSize = (int) $this->getData('qrSize');
        $p->updateSetting($id, 'qrSize', ($qrSize >= 20 && $qrSize <= 300) ? $qrSize : 68);
        $p->updateSetting($id, 'qrOffsetX', $clamp($this->getData('qrOffsetX'), -400, 400, 0));
        $p->updateSetting($id, 'qrOffsetY', $clamp($this->getData('qrOffsetY'), -400, 400, 0));

        // Element visibility toggles (unchecked checkbox => not submitted => hide)
        foreach (self::elementToggleKeys() as $toggle) {
            $p->updateSetting($id, $toggle, $this->getData($toggle) ? '1' : '0');
        }

        $accentColor = preg_match('/^#[0-9a-fA-F]{6}$/', $this->getData('accentColor'))
            ? $this->getData('accentColor') : '#b8975a';
        $p->updateSetting($id, 'accentColor', $accentColor);

        $textColor = preg_match('/^#[0-9a-fA-F]{6}$/', $this->getData('textColor'))
            ? $this->getData('textColor') : '#1a1a2e';
        $p->updateSetting($id, 'textColor', $textColor);

        // Certificate body: store raw text per locale (placeholders replaced at render time)
        $certificateBodyData = $this->getData('certificateBody');
        $p->updateSetting($id, 'certificateBody', is_array($certificateBodyData) ? $certificateBodyData : [], 'object');

        $p->updateSetting($id, 'enableQrCode', $this->getData('enableQrCode') ? '1' : '0');

        $allowedFormats = ['long', 'medium', 'short', 'Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d', 'd F Y', 'F d, Y', 'j F Y', 'd M Y', 'M d, Y'];
        $dateFormat = $this->getData('dateFormat') ?: 'long';
        $p->updateSetting($id, 'dateFormat', in_array($dateFormat, $allowedFormats) ? $dateFormat : 'long');

        $allowedLocales = ['','ar','ar_IQ','ar_SA','ar_EG','ar_AE','ar_KW','ar_BH','ar_QA','ar_OM','ar_JO','ar_LB','ar_SY','ar_PS','ar_MA','ar_DZ','ar_TN','ar_LY','ar_SD','ar_YE','en','en_US','en_GB','en_AU','en_CA','fr','fr_FR','fr_CA','de','de_DE','es','es_ES','tr','tr_TR','fa','fa_IR','ku','ckb'];
        $dateLocale = trim($this->getData('dateLocale') ?? '');
        $p->updateSetting($id, 'dateLocale', in_array($dateLocale, $allowedLocales) ? $dateLocale : '');

        // wkhtmltopdf path: store as-is; gateway validates executability at runtime
        $p->updateSetting($id, 'wkhtmltopdfPath', trim($this->getData('wkhtmltopdfPath') ?? ''));

        $p->updateSetting($id, 'signatureUrl',       $this->getData('signatureUrl'));
        $p->updateSetting($id, 'customLogoUrl',      $this->getData('customLogoUrl'));
        $p->updateSetting($id, 'backgroundImageUrl', $this->getData('backgroundImageUrl'));

        // Process any uploaded temporary files (these override the URL text fields)
        $request              = Application::get()->getRequest();
        $user                 = $request->getUser();
        $publicFileManager    = new PublicFileManager();
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

            $ext      = strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = 'reviewer_cert_' . $fileType . '.' . $ext;

            if ($publicFileManager->copyContextFile($id, $temporaryFile->getFilePath(), $filename)) {
                $url = $request->getBaseUrl()
                    . '/' . $publicFileManager->getContextFilesPath($id)
                    . '/' . $filename;
                $p->updateSetting($id, $settingKey, $url);
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

    private function _getLocalizedSetting(ReviewerCertificatePlugin $plugin, int $contextId, string $name, string $default = ''): array
    {
        $raw = $plugin->getSetting($contextId, $name);
        if (is_array($raw) && !empty($raw)) {
            return $raw;
        }
        $supportedLocales = Locale::getSupportedFormLocales();
        $fallback = ($raw !== null && $raw !== '' && !is_array($raw)) ? (string) $raw : $default;
        $result = [];
        foreach (array_keys($supportedLocales) as $loc) {
            $result[$loc] = $fallback;
        }
        return $result;
    }
}
