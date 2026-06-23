<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/migration/ReviewerCertificateSeedTemplateMigration.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateSeedTemplateMigration
 *
 * @brief For each existing context that has reviewerCertificate plugin_settings but
 *        no template row yet, creates a Default template and copies every
 *        plugin_settings row into reviewer_certificate_settings. Idempotent.
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\db\DAORegistry;

class ReviewerCertificateSeedTemplateMigration extends Migration
{
    public function up(): void
    {
        // Guard: table must exist before we try to seed it.
        if (!DB::getSchemaBuilder()->hasTable('reviewer_certificate_templates')) {
            return;
        }

        /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateTemplateDAO $templateDao */
        $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');

        // Distinct context_ids that have any reviewerCertificate plugin_settings row.
        $contextIds = DB::table('plugin_settings')
            ->where('plugin_name', 'reviewercertificateplugin')
            ->distinct()
            ->pluck('context_id');

        foreach ($contextIds as $contextId) {
            // Idempotency guard: skip contexts that already have a default template.
            if ($templateDao->getDefault((int) $contextId) !== null) {
                continue;
            }

            // Create the Default template row.
            $template = $templateDao->newDataObject();
            $template->setContextId((int) $contextId);
            $template->setTemplateName('Default');
            $template->setLayout('certificate');
            $template->setIsDefault(1);
            $template->setEnabled(1);
            $template->setDateCreated(Core::getCurrentDate());

            $templateId = $templateDao->insertObject($template);

            // Copy every plugin_settings row for this context into reviewer_certificate_settings.
            $settings = DB::table('plugin_settings')
                ->where('plugin_name', 'reviewercertificateplugin')
                ->where('context_id', $contextId)
                ->get();

            foreach ($settings as $s) {
                $templateDao->upsertSetting(
                    $templateId,
                    (string) $s->setting_name,
                    $s->setting_value,
                    (string) ($s->setting_type ?: 'string'),
                    (string) ($s->locale ?? '')
                );
            }
        }
    }

    public function down(): void
    {
        // Intentional no-op: seeded data is managed by the template DAO.
    }
}
