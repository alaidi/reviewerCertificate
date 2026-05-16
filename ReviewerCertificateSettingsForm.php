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

        $this->setData('editorName',          $p->getSetting($id, 'editorName'));
        $this->setData('editorTitle',         $p->getSetting($id, 'editorTitle') ?: 'Editor-in-Chief');
        $this->setData('editorNameFontSize',  $p->getSetting($id, 'editorNameFontSize') ?: '12');
        $this->setData('editorNameColor',     $p->getSetting($id, 'editorNameColor') ?: '#222222');
        $this->setData('journalNameFontSize', $p->getSetting($id, 'journalNameFontSize') ?: '12');
        $this->setData('journalNameColor',    $p->getSetting($id, 'journalNameColor') ?: '#7a6030');
        $this->setData('signatureSize',       $p->getSetting($id, 'signatureSize') ?: '70');
        $this->setData('logoSize',            $p->getSetting($id, 'logoSize') ?: '70');
        $this->setData('accentColor',         $p->getSetting($id, 'accentColor') ?: '#b8975a');
        $this->setData('certificateBody',     $p->getSetting($id, 'certificateBody') ?? '');
        $this->setData('enableQrCode',        $p->getSetting($id, 'enableQrCode') ?? '1');
        $this->setData('dateFormat',           $p->getSetting($id, 'dateFormat') ?: 'long');
        $this->setData('dateLocale',           $p->getSetting($id, 'dateLocale') ?? '');
        $this->setData('wkhtmltopdfPath',     $p->getSetting($id, 'wkhtmltopdfPath') ?? '');
        $this->setData('signatureUrl',        $p->getSetting($id, 'signatureUrl'));
        $this->setData('customLogoUrl',       $p->getSetting($id, 'customLogoUrl'));
        $this->setData('backgroundImageUrl',  $p->getSetting($id, 'backgroundImageUrl'));
    }

    public function readInputData(): void
    {
        $this->readUserVars([
            'editorName',
            'editorTitle',
            'editorNameFontSize',
            'editorNameColor',
            'journalNameFontSize',
            'journalNameColor',
            'signatureSize',
            'logoSize',
            'accentColor',
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

        // Plain text / URL fields
        $p->updateSetting($id, 'editorName',  $this->getData('editorName'));
        $p->updateSetting($id, 'editorTitle', $this->getData('editorTitle') ?: 'Editor-in-Chief');

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

        $accentColor = preg_match('/^#[0-9a-fA-F]{6}$/', $this->getData('accentColor'))
            ? $this->getData('accentColor') : '#b8975a';
        $p->updateSetting($id, 'accentColor', $accentColor);

        // Certificate body: store raw text (placeholders replaced at render time)
        $p->updateSetting($id, 'certificateBody', $this->getData('certificateBody') ?? '');

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
}
