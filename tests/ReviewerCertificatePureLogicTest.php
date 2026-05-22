<?php

declare(strict_types=1);

use APP\plugins\generic\reviewerCertificate\ReviewerCertificatePlugin;
use APP\plugins\generic\reviewerCertificate\ReviewerCertificateSettingsForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewerCertificatePlugin::class)]
#[CoversClass(ReviewerCertificateSettingsForm::class)]
final class ReviewerCertificatePureLogicTest extends TestCase
{
    public function testElementToggleKeysRemainStableAcrossPluginAndForm(): void
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

        $this->assertSame($expected, ReviewerCertificatePlugin::elementToggleKeys());
        $this->assertSame($expected, ReviewerCertificateSettingsForm::elementToggleKeys());
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
            ReviewerCertificatePlugin::textOverrideKeys()
        );
    }

    public function testToLocalizedReturnsExistingLocalizedArrayUnchanged(): void
    {
        $form = $this->newSettingsForm([
            'en_US' => 'English',
            'ar_IQ' => 'Arabic',
        ]);

        $value = [
            'en_US' => 'Editor-in-Chief',
            'ar_IQ' => 'رئيس التحرير',
        ];

        $this->assertSame($value, $this->invokePrivateMethod($form, '_toLocalized', [$value]));
    }

    public function testToLocalizedExpandsLegacyScalarAcrossSupportedLocales(): void
    {
        $form = $this->newSettingsForm([
            'en_US' => 'English',
            'ar_IQ' => 'Arabic',
            'fr_CA' => 'French',
        ]);

        $this->assertSame(
            [
                'en_US' => 'Legacy value',
                'ar_IQ' => 'Legacy value',
                'fr_CA' => 'Legacy value',
            ],
            $this->invokePrivateMethod($form, '_toLocalized', ['Legacy value'])
        );
    }

    public function testToLocalizedReturnsEmptyArrayForBlankLegacyValue(): void
    {
        $form = $this->newSettingsForm([
            'en_US' => 'English',
            'ar_IQ' => 'Arabic',
        ]);

        $this->assertSame([], $this->invokePrivateMethod($form, '_toLocalized', ['']));
        $this->assertSame([], $this->invokePrivateMethod($form, '_toLocalized', [null]));
    }

    public function testFormatDateReturnsOriginalStringForInvalidInput(): void
    {
        $plugin = $this->newPlugin();

        $this->assertSame(
            'not-a-date',
            $this->invokePrivateMethod($plugin, '_formatDate', ['not-a-date', 'en_US', 'Y-m-d'])
        );
    }

    public function testFormatDateSupportsExplicitPhpDatePatterns(): void
    {
        $plugin = $this->newPlugin();

        $this->assertSame(
            '2026-05-19',
            $this->invokePrivateMethod($plugin, '_formatDate', ['2026-05-19 10:11:12', 'en_US', 'Y-m-d'])
        );
    }

    private function newPlugin(): ReviewerCertificatePlugin
    {
        $reflection = new ReflectionClass(ReviewerCertificatePlugin::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function newSettingsForm(array $supportedLocales): ReviewerCertificateSettingsForm
    {
        $reflection = new ReflectionClass(ReviewerCertificateSettingsForm::class);
        /** @var ReviewerCertificateSettingsForm $form */
        $form = $reflection->newInstanceWithoutConstructor();
        $form->supportedLocales = $supportedLocales;
        return $form;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $arguments);
    }
}
