<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReviewerCertificatePureLogicTest extends TestCase
{
    public function testElementToggleKeysRemainStable(): void
    {
        $expected = [
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
        ];

        $this->assertSame($expected, self::elementToggleKeys());
    }

    public function testTextOverrideKeysRemainStable(): void
    {
        $this->assertSame(
            [
                'journalNameText',
                'headingText',
                'subheadingText',
                'presentedToText',
                'completedOnText',
                'dateLabelText',
            ],
            self::textOverrideKeys()
        );
    }

    public function testElementOffsetKeysRemainStable(): void
    {
        $this->assertSame(
            [
                'logo',
                'journalName',
                'heading',
                'subheading',
                'presentedTo',
                'reviewerName',
                'reviewerAffiliation',
                'body',
                'dateLine',
            ],
            self::elementOffsetKeys()
        );
    }

    public function testNormalizeElementFontSizesClampsAndDefaultsToZero(): void
    {
        $out = self::normalizeElementFontSizes([
            'heading' => 200,    // over max -> clamp to 72
            'subheading' => 4,   // positive but below min -> clamp up to 8
            'presentedTo' => 0,  // zero -> use default (0)
            'body' => 18,        // in range -> kept
            'bogusKey' => 30,    // unknown -> dropped
        ]);

        $this->assertSame(72, $out['heading']);
        $this->assertSame(8, $out['subheading']);
        $this->assertSame(0, $out['presentedTo']);
        $this->assertSame(18, $out['body']);
        $this->assertArrayNotHasKey('bogusKey', $out);
        // Every known key is present; unspecified ones default to 0.
        $this->assertSame(0, $out['reviewerName']);
        $this->assertSame(self::elementFontSizeKeys(), array_keys($out));
    }

    public function testNormalizeElementFontSizesParsesJsonString(): void
    {
        $out = self::normalizeElementFontSizes('{"heading":40,"body":0}');
        $this->assertSame(40, $out['heading']);
        $this->assertSame(0, $out['body']);
    }

    public function testToLocalizedReturnsExistingLocalizedArrayUnchanged(): void
    {
        $value = [
            'en_US' => 'Editor-in-Chief',
            'ar_IQ' => 'رئيس التحرير',
        ];

        $this->assertSame($value, self::_toLocalized($value, ['en_US', 'ar_IQ']));
    }

    public function testToLocalizedExpandsLegacyScalarAcrossSupportedLocales(): void
    {
        $supportedLocales = ['en_US', 'ar_IQ', 'fr_CA'];

        $this->assertSame(
            [
                'en_US' => 'Legacy value',
                'ar_IQ' => 'Legacy value',
                'fr_CA' => 'Legacy value',
            ],
            self::_toLocalized('Legacy value', $supportedLocales)
        );
    }

    public function testToLocalizedReturnsEmptyArrayForBlankLegacyValue(): void
    {
        $supportedLocales = ['en_US', 'ar_IQ'];

        $this->assertSame([], self::_toLocalized('', $supportedLocales));
        $this->assertSame([], self::_toLocalized(null, $supportedLocales));
    }

    public function testFormatDateReturnsOriginalStringForInvalidInput(): void
    {
        $this->assertSame('not-a-date', self::_formatDate('not-a-date', 'en_US', 'Y-m-d'));
    }

    public function testFormatDateSupportsExplicitPhpDatePatterns(): void
    {
        $this->assertSame('2026-05-19', self::_formatDate('2026-05-19 10:11:12', 'en_US', 'Y-m-d'));
    }

    // ------------------------------------------------------------------------
    // Reimplementation of pure logic copied from the plugin, no PKP dependencies
    // ------------------------------------------------------------------------

    private static function elementToggleKeys(): array
    {
        return [
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
        ];
    }

    private static function textOverrideKeys(): array
    {
        return [
            'journalNameText',
            'headingText',
            'subheadingText',
            'presentedToText',
            'completedOnText',
            'dateLabelText',
        ];
    }

    private static function elementOffsetKeys(): array
    {
        return [
            'logo',
            'journalName',
            'heading',
            'subheading',
            'presentedTo',
            'reviewerName',
            'reviewerAffiliation',
            'body',
            'dateLine',
        ];
    }

    private static function elementFontSizeKeys(): array
    {
        return [
            'heading',
            'subheading',
            'presentedTo',
            'reviewerName',
            'reviewerAffiliation',
            'body',
            'dateLine',
        ];
    }

    private static function normalizeElementFontSizes(mixed $raw): array
    {
        $data = is_array($raw)
            ? $raw
            : (is_string($raw) && $raw !== '' ? json_decode($raw, true) : []);
        if (!is_array($data)) {
            $data = [];
        }
        $out = [];
        foreach (self::elementFontSizeKeys() as $key) {
            $v = isset($data[$key]) ? (int) $data[$key] : 0;
            $out[$key] = $v > 0 ? max(8, min(72, $v)) : 0;
        }
        return $out;
    }

    private static function _toLocalized(mixed $value, array $supportedLocales): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === '' || $value === null) {
            return [];
        }

        $result = [];
        foreach ($supportedLocales as $locale) {
            $result[$locale] = $value;
        }
        return $result;
    }

    private static function _formatDate(string $dateStr, string $locale, string $pattern): string
    {
        $timestamp = strtotime($dateStr);
        if ($timestamp === false) {
            return $dateStr;
        }
        return date($pattern, $timestamp);
    }
}
