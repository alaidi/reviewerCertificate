<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificateTemplate.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateTemplate
 *
 * @brief Data model for a reviewer certificate template definition.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\core\DataObject;

class ReviewerCertificateTemplate extends DataObject
{
    public function getTemplateId()
    {
        return $this->getData('templateId');
    }

    public function setTemplateId($value): void
    {
        $this->setData('templateId', $value);
    }

    public function getContextId()
    {
        return $this->getData('contextId');
    }

    public function setContextId($value): void
    {
        $this->setData('contextId', $value);
    }

    public function getTemplateName(): string
    {
        return (string) $this->getData('templateName');
    }

    public function setTemplateName($value): void
    {
        $this->setData('templateName', (string) $value);
    }

    public function getLayout(): string
    {
        return (string) $this->getData('layout');
    }

    public function setLayout($value): void
    {
        $this->setData('layout', (string) $value);
    }

    public function getIsDefault(): int
    {
        return (int) $this->getData('isDefault');
    }

    public function setIsDefault(int $value): void
    {
        $this->setData('isDefault', $value);
    }

    public function getEnabled(): int
    {
        return (int) $this->getData('enabled');
    }

    public function setEnabled(int $value): void
    {
        $this->setData('enabled', $value);
    }

    public function getDateCreated()
    {
        return $this->getData('dateCreated');
    }

    public function setDateCreated($value): void
    {
        $this->setData('dateCreated', $value);
    }

    public function getDateModified()
    {
        return $this->getData('dateModified');
    }

    public function setDateModified($value): void
    {
        $this->setData('dateModified', $value);
    }
}
