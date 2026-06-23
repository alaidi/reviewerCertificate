<?php

/**
 * @file tests/Unit/ReviewerCertificateDAOTest.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @brief Unit tests for ReviewerCertificateDAO CRUD, canonicalJson stability,
 *        and generateCode format.
 */

namespace APP\plugins\generic\reviewerCertificate\Tests\Unit;

use APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificate;
use APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateDAO;
use APP\plugins\generic\reviewerCertificate\Tests\TestCase;

class ReviewerCertificateDAOTest extends TestCase
{
    private ReviewerCertificateDAO $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new ReviewerCertificateDAO();
    }

    public function testCanonicalJsonIsKeyOrderStable(): void
    {
        $a = ReviewerCertificateDAO::canonicalJson(['b' => 1, 'a' => 2]);
        $b = ReviewerCertificateDAO::canonicalJson(['a' => 2, 'b' => 1]);
        $this->assertSame($a, $b);
    }

    public function testGenerateCodeIsUppercaseHex(): void
    {
        $this->assertMatchesRegularExpression('/^[A-F0-9]{16}$/', ReviewerCertificate::generateCode());
    }

    public function testInsertAndFetchByReviewId(): void
    {
        $cert = $this->dao->newDataObject();
        $cert->setReviewerId(5);
        $cert->setSubmissionId(10);
        $cert->setReviewId(42);
        $cert->setContextId(1);
        $cert->setDateIssued('2026-06-23 00:00:00');
        $cert->setCertificateCode(ReviewerCertificate::generateCode());
        $cert->setDownloadCount(0);
        $id = $this->dao->insertObject($cert);
        $this->assertGreaterThan(0, $id);

        $fetched = $this->dao->getByReviewId(42);
        $this->assertNotNull($fetched);
        $this->assertSame(1, (int) $fetched->getContextId());
        $this->assertSame(5, (int) $fetched->getReviewerId());
    }

    public function testGetByCertificateCode(): void
    {
        $code = ReviewerCertificate::generateCode();
        $cert = $this->dao->newDataObject();
        $cert->setReviewerId(6);
        $cert->setSubmissionId(11);
        $cert->setReviewId(43);
        $cert->setContextId(1);
        $cert->setDateIssued('2026-06-23 00:00:00');
        $cert->setCertificateCode($code);
        $this->dao->insertObject($cert);

        $found = $this->dao->getByCertificateCode($code);
        $this->assertNotNull($found);
        $this->assertSame($code, $found->getCertificateCode());
    }

    public function testGetByContextIdReturnsResults(): void
    {
        $cert = $this->dao->newDataObject();
        $cert->setReviewerId(7);
        $cert->setSubmissionId(12);
        $cert->setReviewId(44);
        $cert->setContextId(2);
        $cert->setDateIssued('2026-06-23 00:00:00');
        $cert->setCertificateCode(ReviewerCertificate::generateCode());
        $cert->setDownloadCount(0);
        $this->dao->insertObject($cert);

        $factory = $this->dao->getByContextId(2);
        $results = $factory->toArray();
        $this->assertCount(1, $results);
        $this->assertSame(2, (int) $results[0]->getContextId());
    }
}
