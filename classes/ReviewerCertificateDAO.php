<?php

/**
 * @file plugins/generic/reviewerCertificate/classes/ReviewerCertificateDAO.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateDAO
 *
 * @brief CRUD + statistics for issued reviewer certificates.
 */

namespace APP\plugins\generic\reviewerCertificate\classes;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;

require_once(dirname(__FILE__) . '/ReviewerCertificate.php');

class ReviewerCertificateDAO extends DAO
{
    public function newDataObject(): ReviewerCertificate
    {
        return new ReviewerCertificate();
    }

    public function getById($certificateId): ?ReviewerCertificate
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_id = ?',
            [(int) $certificateId]
        )->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    public function getByReviewId($reviewId): ?ReviewerCertificate
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE review_id = ?',
            [(int) $reviewId]
        )->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    public function getByCertificateCode($code): ?ReviewerCertificate
    {
        $row = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_code = ?',
            [(string) $code]
        )->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    public function getByContextId($contextId): DAOResultFactory
    {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE context_id = ? ORDER BY date_issued DESC',
            [(int) $contextId]
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    public function insertObject(ReviewerCertificate $cert): int
    {
        $this->update(
            'INSERT INTO reviewer_certificates
                (reviewer_id, submission_id, review_id, context_id, template_id,
                 snapshot_id, snapshot, date_issued, certificate_code, download_count, last_downloaded)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $cert->getReviewerId(),
                (int) $cert->getSubmissionId(),
                (int) $cert->getReviewId(),
                (int) $cert->getContextId(),
                $cert->getTemplateId() !== null ? (int) $cert->getTemplateId() : null,
                $cert->getSnapshotId() !== null ? (int) $cert->getSnapshotId() : null,
                $cert->getSnapshot(),
                $cert->getDateIssued(),
                $cert->getCertificateCode(),
                (int) $cert->getDownloadCount(),
                $cert->getLastDownloaded(),
            ]
        );
        $cert->setCertificateId($this->getInsertId());
        return (int) $cert->getCertificateId();
    }

    public function updateObject(ReviewerCertificate $cert): void
    {
        $this->update(
            'UPDATE reviewer_certificates SET
                reviewer_id = ?, submission_id = ?, review_id = ?, context_id = ?, template_id = ?,
                snapshot_id = ?, snapshot = ?, date_issued = ?, certificate_code = ?,
                download_count = ?, last_downloaded = ?
             WHERE certificate_id = ?',
            [
                (int) $cert->getReviewerId(),
                (int) $cert->getSubmissionId(),
                (int) $cert->getReviewId(),
                (int) $cert->getContextId(),
                $cert->getTemplateId() !== null ? (int) $cert->getTemplateId() : null,
                $cert->getSnapshotId() !== null ? (int) $cert->getSnapshotId() : null,
                $cert->getSnapshot(),
                $cert->getDateIssued(),
                $cert->getCertificateCode(),
                (int) $cert->getDownloadCount(),
                $cert->getLastDownloaded(),
                (int) $cert->getCertificateId(),
            ]
        );
    }

    public function deleteById($certificateId): void
    {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE certificate_id = ?',
            [(int) $certificateId]
        );
    }

    public function _fromRow($row): ReviewerCertificate
    {
        $row = (array) $row;
        $cert = $this->newDataObject();
        $cert->setCertificateId($row['certificate_id']);
        $cert->setReviewerId($row['reviewer_id']);
        $cert->setSubmissionId($row['submission_id']);
        $cert->setReviewId($row['review_id']);
        $cert->setContextId($row['context_id']);
        $cert->setTemplateId($row['template_id']);
        $cert->setSnapshotId($row['snapshot_id'] ?? null);
        $cert->setSnapshot($row['snapshot']);
        $cert->setDateIssued($row['date_issued']);
        $cert->setCertificateCode($row['certificate_code']);
        $cert->setDownloadCount($row['download_count']);
        $cert->setLastDownloaded($row['last_downloaded']);
        return $cert;
    }

    /** OJS 3.5 removed _getInsertId() from base DAO; use PDO lastInsertId. */
    public function getInsertId(): int
    {
        return $this->_insertId('reviewer_certificates', 'certificate_id');
    }

    private function _insertId(string $table, string $column): int
    {
        if (method_exists($this, '_getInsertId')) {
            return (int) $this->_getInsertId($table, $column);
        }
        if (class_exists('Illuminate\Support\Facades\DB')) {
            try {
                $pdo = \Illuminate\Support\Facades\DB::getPdo();
                if ($pdo !== null) {
                    return (int) $pdo->lastInsertId();
                }
            } catch (\Throwable $e) {
                error_log('[ReviewerCertificate] _insertId() fallback failed: ' . $e->getMessage());
            }
        }
        return 0;
    }

    /**
     * Content-addressed store for the shared (template-level) part of a certificate
     * snapshot. Identical content collapses to one row, so the bulk wording isn't
     * duplicated across every certificate. Returns the existing row's id when the
     * same content was already stored, else inserts and returns the new id.
     */
    public function findOrCreateContentSnapshot(array $content): int
    {
        $json = self::canonicalJson($content);
        $hash = hash('sha256', $json);
        $row = $this->retrieve(
            'SELECT snapshot_id FROM reviewer_certificate_snapshots WHERE content_hash = ?',
            [$hash]
        )->current();
        if ($row) {
            return (int) $row->snapshot_id;
        }
        $this->update(
            'INSERT INTO reviewer_certificate_snapshots (content_hash, content, date_created) VALUES (?, ?, ?)',
            [$hash, $json, \PKP\core\Core::getCurrentDate()]
        );
        return $this->_insertId('reviewer_certificate_snapshots', 'snapshot_id');
    }

    public function getContentSnapshot(int $snapshotId): ?string
    {
        $row = $this->retrieve(
            'SELECT content FROM reviewer_certificate_snapshots WHERE snapshot_id = ?',
            [$snapshotId]
        )->current();
        return $row ? (string) $row->content : null;
    }

    /** Deterministic JSON (recursively key-sorted) so the content hash is stable. */
    public static function canonicalJson(array $data): string
    {
        $sort = function (&$v) use (&$sort) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as &$child) {
                    $sort($child);
                }
            }
        };
        $sort($data);
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
