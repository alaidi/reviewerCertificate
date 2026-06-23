<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/migration/ReviewerCertificateBackfillMigration.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateBackfillMigration
 *
 * @brief Best-effort backfill of reviewer_certificates rows for every
 *        completed review that has no certificate yet.
 *
 * Idempotency guarantees:
 *   1. The SQL query uses a NOT EXISTS sub-select on rc.review_id = ra.review_id,
 *      so rows that already have a certificate are never returned.
 *   2. CertificateGenerator::freeze() calls getByReviewId() before inserting
 *      and returns the existing row if found — a second defence at the PHP layer.
 *   Running up() twice on a live DB inserts at most N rows on the first run
 *   and 0 on any subsequent run.
 *
 * Best-effort semantics:
 *   Every row is processed inside its own try/catch(\Throwable) block.
 *   A failure on one review (missing user, broken template setting, etc.)
 *   is logged to the PHP error_log and does NOT abort the remaining rows.
 */

namespace APP\plugins\generic\reviewerCertificate\classes\migration;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;

class ReviewerCertificateBackfillMigration extends Migration
{
    public function up(): void
    {
        // Guard: the schema migration that creates this table runs just before us,
        // but if somehow it is absent (e.g., a manual partial install) we bail out
        // cleanly rather than throwing a PDO exception.
        if (!DB::getSchemaBuilder()->hasTable('reviewer_certificates')) {
            return;
        }

        // Query: every completed review without a certificate row.
        // The NOT EXISTS clause is the primary idempotency filter.
        $rows = DB::table('review_assignments AS ra')
            ->join('submissions AS s', 's.submission_id', '=', 'ra.submission_id')
            ->whereNotNull('ra.date_completed')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('reviewer_certificates AS rc')
                    ->whereColumn('rc.review_id', 'ra.review_id');
            })
            ->get(['ra.review_id', 'ra.reviewer_id', 'ra.submission_id', 's.context_id']);

        if ($rows->isEmpty()) {
            return;
        }

        // Resolve the plugin once up front.  During install the plugin is always
        // registered; on a manual CLI run it may not be — in that case bail.
        $plugin = PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');
        if ($plugin === null) {
            error_log('[ReviewerCertificateBackfillMigration] Plugin not registered — cannot resolve settings; skipping backfill.');
            return;
        }

        $request = Application::get()->getRequest();

        // Per-context cache so we don't hit the DB 456 times for the same journal.
        // Structure: [ contextId => ['context' => ..., 'template' => ...] ]
        $contextCache = [];

        /** @var \PKP\db\DAO $templateDao */
        $templateDao = DAORegistry::getDAO('ReviewerCertificateTemplateDAO');

        foreach ($rows as $row) {
            try {
                $contextId = (int) $row->context_id;

                // Load (and cache) the context object + default template.
                if (!isset($contextCache[$contextId])) {
                    $context = Application::getContextDAO()->getById($contextId);
                    $template = ($context !== null)
                        ? $templateDao->getDefault($contextId)
                        : null;
                    $contextCache[$contextId] = [
                        'context' => $context,
                        'template' => $template,
                    ];
                }

                $context = $contextCache[$contextId]['context'];
                $template = $contextCache[$contextId]['template'];

                if ($context === null) {
                    // Journal deleted or context_id is bogus — skip silently.
                    continue;
                }

                // Load the ReviewAssignment object via the Repo facade.
                $reviewAssignment = Repo::reviewAssignment()->get((int) $row->review_id);
                if ($reviewAssignment === null) {
                    continue;
                }

                // freeze() is idempotent (returns existing row if already present)
                // and render-free (no Smarty/TemplateManager calls).
                (new CertificateGenerator())->freeze(
                    $plugin,
                    $request,
                    $reviewAssignment,
                    $context,
                    $template   // may be null — freeze() tolerates null
                );
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[ReviewerCertificateBackfillMigration] Skipping review_id=%d: %s — %s:%d',
                    (int) ($row->review_id ?? 0),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
                continue;
            }
        }
    }

    /**
     * No-op: the install migration's down() drops the tables entirely.
     */
    public function down(): void
    {
    }
}
