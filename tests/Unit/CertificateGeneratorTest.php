<?php

/**
 * @file tests/Unit/CertificateGeneratorTest.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @brief Unit tests for CertificateGenerator partition and canonicalJson stability.
 *
 * These tests exercise the pure-logic layer:
 *  - reviewerName belongs to perCert (not shared)
 *  - editorTitle belongs to shared (not perCert)
 *  - two resolves with identical shared inputs produce the same canonicalJson hash
 *
 * Because resolveSnapshotData() depends on OJS framework objects (Repo, TemplateManager,
 * etc.) that are unavailable in this harness, we test the partition contract via the
 * static key-list accessors on CertificateGenerator and the canonicalJson hash stability
 * via ReviewerCertificateDAO directly — exactly what the brief requires.
 */

namespace APP\plugins\generic\reviewerCertificate\Tests\Unit;

use APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator;
use APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateDAO;
use APP\plugins\generic\reviewerCertificate\Tests\TestCase;

class CertificateGeneratorTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Partition tests
    // -----------------------------------------------------------------------

    /**
     * reviewerName must be in perCert, never in shared.
     */
    public function testReviewerNameIsInPerCert(): void
    {
        $this->assertContains('reviewerName', CertificateGenerator::perCertKeys());
        $this->assertNotContains('reviewerName', CertificateGenerator::sharedKeys());
    }

    /**
     * editorTitle must be in shared, never in perCert.
     */
    public function testEditorTitleIsInShared(): void
    {
        $this->assertContains('editorTitle', CertificateGenerator::sharedKeys());
        $this->assertNotContains('editorTitle', CertificateGenerator::perCertKeys());
    }

    /**
     * All elementToggleKeys must land in shared.
     */
    public function testAllElementToggleKeysAreShared(): void
    {
        $shared = CertificateGenerator::sharedKeys();
        foreach (\APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin::elementToggleKeys() as $key) {
            $this->assertContains(
                $key,
                $shared,
                "elementToggleKey '{$key}' must be in shared"
            );
        }
    }

    /**
     * All textOverrideKeys must land in shared.
     */
    public function testAllTextOverrideKeysAreShared(): void
    {
        $shared = CertificateGenerator::sharedKeys();
        foreach (\APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin::textOverrideKeys() as $key) {
            $this->assertContains(
                $key,
                $shared,
                "textOverrideKey '{$key}' must be in shared"
            );
        }
    }

    /**
     * No key should appear in both partitions.
     */
    public function testPartitionsAreDisjoint(): void
    {
        $overlap = array_intersect(
            CertificateGenerator::sharedKeys(),
            CertificateGenerator::perCertKeys()
        );
        $this->assertEmpty(
            $overlap,
            'Keys appear in both shared and perCert: ' . implode(', ', $overlap)
        );
    }

    // -----------------------------------------------------------------------
    // canonicalJson hash stability
    // -----------------------------------------------------------------------

    /**
     * Two identical shared arrays (regardless of key order) must produce the
     * same canonicalJson hash — the foundation of content-addressed dedup.
     */
    public function testIdenticalSharedInputsProduceSameCanonicalJsonHash(): void
    {
        $sharedA = [
            'editorTitle' => 'Editor-in-Chief',
            'editorName' => 'Dr. Jane Smith',
            'certificateBody' => 'Thank you for reviewing.',
            'accentColor' => '#b8975a',
            'textColor' => '#1a1a2e',
            'editorNameColor' => '#222222',
            'journalNameColor' => '#7a6030',
            'editorNameFontSize' => 12,
            'journalNameFontSize' => 12,
            'signatureSize' => 70,
            'logoSize' => 70,
            'enableQrCode' => true,
            'qrSize' => 68,
            'qrOffsetX' => 0,
            'qrOffsetY' => 0,
            'contentOffsetY' => 0,
            'layout' => 'landscape',
            'showLogo' => true,
            'showJournalName' => true,
            'showDividers' => true,
            'showHeading' => true,
            'showSubheading' => true,
            'showPresentedTo' => true,
            'showReviewerName' => true,
            'showBody' => true,
            'showDateLine' => true,
            'showSignatureSection' => true,
            'journalNameText' => '',
            'headingText' => '',
            'subheadingText' => '',
            'presentedToText' => '',
            'completedOnText' => '',
            'dateLabelText' => '',
        ];

        // Build a second array with keys in a different order
        $sharedB = array_reverse($sharedA, true);

        $hashA = hash('sha256', ReviewerCertificateDAO::canonicalJson($sharedA));
        $hashB = hash('sha256', ReviewerCertificateDAO::canonicalJson($sharedB));

        $this->assertSame(
            $hashA,
            $hashB,
            'canonicalJson hash must be stable regardless of key insertion order'
        );
    }

    /**
     * Two shared arrays with different values must produce different hashes.
     */
    public function testDifferentSharedInputsProduceDifferentHashes(): void
    {
        $sharedA = ['editorTitle' => 'Editor-in-Chief', 'accentColor' => '#b8975a'];
        $sharedB = ['editorTitle' => 'Chief Editor',    'accentColor' => '#b8975a'];

        $hashA = hash('sha256', ReviewerCertificateDAO::canonicalJson($sharedA));
        $hashB = hash('sha256', ReviewerCertificateDAO::canonicalJson($sharedB));

        $this->assertNotSame($hashA, $hashB);
    }

    // -----------------------------------------------------------------------
    // _uniqueCode via ReviewerCertificate::generateCode (format check)
    // -----------------------------------------------------------------------

    /**
     * ReviewerCertificate::generateCode() must return a 16-char uppercase hex string.
     * (Duplicated here as a sanity cross-check from the generator's perspective.)
     */
    public function testGenerateCodeFormatIsUppercaseHex(): void
    {
        $code = \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificate::generateCode();
        $this->assertMatchesRegularExpression(
            '/^[A-F0-9]{16}$/',
            $code,
            'generateCode() must return exactly 16 uppercase hex characters'
        );
    }
}
