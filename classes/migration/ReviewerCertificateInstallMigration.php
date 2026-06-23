<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/migration/ReviewerCertificateInstallMigration.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateInstallMigration
 *
 * @brief Creates the reviewer certificate plugin tables (OJS 3.5, Laravel Schema).
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReviewerCertificateInstallMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reviewer_certificate_templates')) {
            Schema::create('reviewer_certificate_templates', function (Blueprint $table) {
                $table->bigIncrements('template_id');
                $table->bigInteger('context_id');
                $table->string('template_name', 255);
                $table->string('layout', 40)->default('certificate');
                $table->tinyInteger('is_default')->default(0);
                $table->tinyInteger('enabled')->default(1);
                $table->timestamp('date_created')->useCurrent();
                $table->timestamp('date_modified')->nullable();
                $table->index(['context_id'], 'reviewer_certificate_templates_context_id');
            });
        }

        if (!Schema::hasTable('reviewer_certificates')) {
            Schema::create('reviewer_certificates', function (Blueprint $table) {
                $table->bigIncrements('certificate_id');
                $table->bigInteger('reviewer_id');
                $table->bigInteger('submission_id');
                $table->bigInteger('review_id');
                $table->bigInteger('context_id');
                $table->bigInteger('template_id')->nullable();
                $table->bigInteger('snapshot_id')->nullable();
                $table->text('snapshot')->nullable();
                $table->timestamp('date_issued')->useCurrent();
                $table->string('certificate_code', 100);
                $table->integer('download_count')->default(0);
                $table->timestamp('last_downloaded')->nullable();
                $table->index(['reviewer_id'], 'reviewer_certificates_reviewer_id');
                $table->index(['review_id'], 'reviewer_certificates_review_id');
                $table->index(['certificate_code'], 'reviewer_certificates_certificate_code');
                $table->index(['context_id'], 'reviewer_certificates_context_id');
                $table->index(['snapshot_id'], 'reviewer_certificates_snapshot_id');
                $table->unique(['review_id'], 'reviewer_certificates_review_id_unique');
                $table->unique(['certificate_code'], 'reviewer_certificates_code_unique');
            });
        }

        if (!Schema::hasTable('reviewer_certificate_snapshots')) {
            Schema::create('reviewer_certificate_snapshots', function (Blueprint $table) {
                $table->bigIncrements('snapshot_id');
                $table->string('content_hash', 64);
                $table->text('content')->nullable();
                $table->timestamp('date_created')->useCurrent();
                $table->unique(['content_hash'], 'reviewer_certificate_snapshots_hash');
            });
        }

        if (!Schema::hasTable('reviewer_certificate_settings')) {
            Schema::create('reviewer_certificate_settings', function (Blueprint $table) {
                $table->bigInteger('template_id');
                $table->string('locale', 14)->default('');
                $table->string('setting_name', 255);
                $table->text('setting_value')->nullable();
                $table->string('setting_type', 6);
                $table->index(['template_id'], 'reviewer_certificate_settings_template_id');
                $table->unique(['template_id', 'locale', 'setting_name'], 'reviewer_certificate_settings_pkey');
            });
        }

        // Existing installs: add freeze columns if the certificates table predates them.
        if (Schema::hasTable('reviewer_certificates')
            && !Schema::hasColumn('reviewer_certificates', 'snapshot_id')) {
            Schema::table('reviewer_certificates', function (Blueprint $table) {
                $table->bigInteger('snapshot_id')->nullable()->after('template_id');
                $table->text('snapshot')->nullable()->after('snapshot_id');
            });
        }

        // Existing installs created by the pre-rewrite (wide) templates schema lack
        // the thin schema's columns; add any that are missing before seeding.
        if (Schema::hasTable('reviewer_certificate_templates')) {
            if (!Schema::hasColumn('reviewer_certificate_templates', 'layout')) {
                Schema::table('reviewer_certificate_templates', function (Blueprint $table) {
                    $table->string('layout', 40)->default('certificate');
                });
            }
            if (!Schema::hasColumn('reviewer_certificate_templates', 'is_default')) {
                Schema::table('reviewer_certificate_templates', function (Blueprint $table) {
                    $table->tinyInteger('is_default')->default(0);
                });
            }
            if (!Schema::hasColumn('reviewer_certificate_templates', 'enabled')) {
                Schema::table('reviewer_certificate_templates', function (Blueprint $table) {
                    $table->tinyInteger('enabled')->default(1);
                });
            }
        }

        require_once __DIR__ . '/ReviewerCertificateSeedTemplateMigration.php';
        (new ReviewerCertificateSeedTemplateMigration())->up();

        require_once __DIR__ . '/ReviewerCertificateBackfillMigration.php';
        (new ReviewerCertificateBackfillMigration())->up();
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_certificate_settings');
        Schema::dropIfExists('reviewer_certificate_snapshots');
        Schema::dropIfExists('reviewer_certificates');
        Schema::dropIfExists('reviewer_certificate_templates');
    }
}
