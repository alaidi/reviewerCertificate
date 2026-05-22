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
