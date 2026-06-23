<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificate.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificate
 *
 * @brief Data model for a single issued reviewer certificate.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\core\Core;
use PKP\core\DataObject;

class ReviewerCertificate extends DataObject
{
    /** Generate a random 16-character uppercase hex code. */
    public static function generateCode(): string
    {
        return strtoupper(bin2hex(random_bytes(8)));
    }

    public function getCertificateId()
    {
        return $this->getData('certificateId');
    }
    public function setCertificateId($v)
    {
        $this->setData('certificateId', $v);
    }

    public function getReviewerId()
    {
        return $this->getData('reviewerId');
    }
    public function setReviewerId($v)
    {
        $this->setData('reviewerId', $v);
    }

    public function getSubmissionId()
    {
        return $this->getData('submissionId');
    }
    public function setSubmissionId($v)
    {
        $this->setData('submissionId', $v);
    }

    public function getReviewId()
    {
        return $this->getData('reviewId');
    }
    public function setReviewId($v)
    {
        $this->setData('reviewId', $v);
    }

    public function getContextId()
    {
        return $this->getData('contextId');
    }
    public function setContextId($v)
    {
        $this->setData('contextId', $v);
    }

    public function getTemplateId()
    {
        return $this->getData('templateId');
    }
    public function setTemplateId($v)
    {
        $this->setData('templateId', $v);
    }

    public function getSnapshotId()
    {
        return $this->getData('snapshotId');
    }
    public function setSnapshotId($v)
    {
        $this->setData('snapshotId', $v);
    }

    public function getSnapshot()
    {
        return $this->getData('snapshot');
    }
    public function setSnapshot($v)
    {
        $this->setData('snapshot', $v);
    }

    public function getDateIssued()
    {
        return $this->getData('dateIssued');
    }
    public function setDateIssued($v)
    {
        $this->setData('dateIssued', $v);
    }

    public function getCertificateCode()
    {
        return $this->getData('certificateCode');
    }
    public function setCertificateCode($v)
    {
        $this->setData('certificateCode', $v);
    }

    public function getDownloadCount()
    {
        return (int) $this->getData('downloadCount');
    }
    public function setDownloadCount($v)
    {
        $this->setData('downloadCount', $v);
    }

    public function getLastDownloaded()
    {
        return $this->getData('lastDownloaded');
    }
    public function setLastDownloaded($v)
    {
        $this->setData('lastDownloaded', $v);
    }

    /** Increment download count and stamp last_downloaded. */
    public function incrementDownloadCount(): void
    {
        $this->setDownloadCount($this->getDownloadCount() + 1);
        $this->setLastDownloaded(Core::getCurrentDate());
    }
}
