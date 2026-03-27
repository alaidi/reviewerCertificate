<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificateSettingsForm.php
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
