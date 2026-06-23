<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificateTemplateDAO.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateTemplateDAO
 *
 * @brief CRUD + settings access for reviewer certificate templates.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;

require_once(dirname(__FILE__) . '/ReviewerCertificateTemplate.php');

class ReviewerCertificateTemplateDAO extends DAO
{
    public function newDataObject(): ReviewerCertificateTemplate
    {
        return new ReviewerCertificateTemplate();
    }

    public function getById(int $templateId): ?ReviewerCertificateTemplate
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificate_templates WHERE template_id = ?',
            [$templateId]
        )->current();

        return $row ? $this->_fromRow((array) $row) : null;
    }

    public function getByContextId(int $contextId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificate_templates WHERE context_id = ? ORDER BY template_id ASC',
            [$contextId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    public function getAllByContextId(int $contextId): array
    {
        $templates = [];
        $resultFactory = $this->getByContextId($contextId);
        while ($template = $resultFactory->next()) {
            $templates[] = $template;
        }
        return $templates;
    }

    public function getDefault(int $contextId): ?ReviewerCertificateTemplate
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificate_templates WHERE context_id = ? ORDER BY is_default DESC, template_id ASC',
            [$contextId]
        )->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    public function setDefault(int $templateId, int $contextId): void
    {
        $this->update('UPDATE reviewer_certificate_templates SET is_default = 0 WHERE context_id = ?', [$contextId]);
        $this->update('UPDATE reviewer_certificate_templates SET is_default = 1 WHERE template_id = ? AND context_id = ?', [$templateId, $contextId]);
    }

    public function insertObject(ReviewerCertificateTemplate $template): int
    {
        $this->update(
            'INSERT INTO reviewer_certificate_templates
                (context_id, template_name, layout, is_default, enabled, date_created, date_modified)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $template->getContextId(),
                $template->getTemplateName(),
                $template->getLayout(),
                $template->getIsDefault(),
                $template->getEnabled(),
                $template->getDateCreated(),
                $template->getDateModified(),
            ]
        );

        $template->setTemplateId($this->getInsertId());
        return (int) $template->getTemplateId();
    }

    public function updateObject(ReviewerCertificateTemplate $template): void
    {
        $this->update(
            'UPDATE reviewer_certificate_templates
             SET context_id = ?, template_name = ?, layout = ?, is_default = ?, enabled = ?, date_created = ?, date_modified = ?
             WHERE template_id = ?',
            [
                (int) $template->getContextId(),
                $template->getTemplateName(),
                $template->getLayout(),
                $template->getIsDefault(),
                $template->getEnabled(),
                $template->getDateCreated(),
                $template->getDateModified(),
                (int) $template->getTemplateId(),
            ]
        );
    }

    public function deleteById(int $templateId): void
    {
        $this->update('DELETE FROM reviewer_certificate_settings WHERE template_id = ?', [$templateId]);
        $this->update('DELETE FROM reviewer_certificate_templates WHERE template_id = ?', [$templateId]);
    }

    public function getSettingRow(int $templateId, string $settingName, string $locale = ''): ?array
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificate_settings WHERE template_id = ? AND locale = ? AND setting_name = ?',
            [$templateId, $locale, $settingName]
        )->current();

        return $row ? (array) $row : null;
    }

    public function getSettings(int $templateId): array
    {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificate_settings WHERE template_id = ?',
            [$templateId]
        );

        $settings = [];
        foreach ($result as $row) {
            $row = (array) $row;
            $settings[] = $row;
        }

        return $settings;
    }

    public function upsertSetting(int $templateId, string $settingName, $settingValue, string $settingType = 'string', string $locale = ''): void
    {
        $this->update(
            'DELETE FROM reviewer_certificate_settings WHERE template_id = ? AND locale = ? AND setting_name = ?',
            [$templateId, $locale, $settingName]
        );

        $this->update(
            'INSERT INTO reviewer_certificate_settings
                (template_id, locale, setting_name, setting_value, setting_type)
             VALUES (?, ?, ?, ?, ?)',
            [$templateId, $locale, $settingName, $settingValue, $settingType]
        );
    }

    public function deleteSetting(int $templateId, string $settingName, string $locale = ''): void
    {
        $this->update(
            'DELETE FROM reviewer_certificate_settings WHERE template_id = ? AND locale = ? AND setting_name = ?',
            [$templateId, $locale, $settingName]
        );
    }

    public function duplicateSettings(int $sourceTemplateId, int $targetTemplateId): void
    {
        foreach ($this->getSettings($sourceTemplateId) as $row) {
            $this->upsertSetting(
                $targetTemplateId,
                (string) $row['setting_name'],
                $row['setting_value'],
                (string) $row['setting_type'],
                (string) ($row['locale'] ?? '')
            );
        }
    }

    public function getInsertId(): int
    {
        if (method_exists($this, '_getInsertId')) {
            return (int) $this->_getInsertId('reviewer_certificate_templates', 'template_id');
        }

        if (class_exists('Illuminate\Support\Facades\DB')) {
            try {
                $pdo = \Illuminate\Support\Facades\DB::getPdo();
                if ($pdo !== null) {
                    return (int) $pdo->lastInsertId();
                }
            } catch (\Throwable $e) {
                error_log('[ReviewerCertificate] template getInsertId() fallback failed: ' . $e->getMessage());
            }
        }

        return 0;
    }

    public function _fromRow($row): ReviewerCertificateTemplate
    {
        $row = (array) $row;
        $template = $this->newDataObject();
        $template->setTemplateId($row['template_id']);
        $template->setContextId($row['context_id']);
        $template->setTemplateName($row['template_name']);
        $template->setLayout($row['layout']);
        $template->setIsDefault((int) ($row['is_default'] ?? 0));
        $template->setEnabled((int) ($row['enabled'] ?? 1));
        $template->setDateCreated($row['date_created']);
        $template->setDateModified($row['date_modified']);
        return $template;
    }
}
