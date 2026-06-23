<?php

/**
 * @file tests/mocks/DatabaseMock.php
 *
 * In-memory table store for the reviewerCertificate test harness.
 *
 * Provides a minimal stand-in for the `reviewer_certificates` table so that
 * ReviewerCertificateDAO can be exercised without a real database connection.
 * Recognizes the specific SQL strings issued by ReviewerCertificateDAO
 * (substring matching) rather than implementing a real SQL engine.
 */

class DatabaseMock
{
    /** @var array<int, array<string, mixed>> Rows keyed by certificate_id */
    public static $rows = [];

    /** @var int Auto-increment counter for certificate_id */
    public static $nextId = 1;

    /** @var int|null Last insert id produced */
    public static $lastInsertId = null;

    /**
     * Reset all in-memory state. Call from TestCase::setUp() for isolation.
     */
    public static function reset(): void
    {
        self::$rows = [];
        self::$nextId = 1;
        self::$lastInsertId = null;
    }

    /**
     * Handle a write query (INSERT / UPDATE / DELETE) issued via DAO::update().
     *
     * @return int Number of affected rows
     */
    public static function update(string $sql, array $params = []): int
    {
        $normalized = self::normalize($sql);

        if (strpos($normalized, 'insert into reviewer_certificates') !== false) {
            [
                $reviewerId, $submissionId, $reviewId, $contextId, $templateId,
                $snapshotId, $snapshot, $dateIssued, $certificateCode, $downloadCount, $lastDownloaded,
            ] = $params;

            $id = self::$nextId++;
            self::$rows[$id] = [
                'certificate_id' => $id,
                'reviewer_id' => $reviewerId,
                'submission_id' => $submissionId,
                'review_id' => $reviewId,
                'context_id' => $contextId,
                'template_id' => $templateId,
                'snapshot_id' => $snapshotId,
                'snapshot' => $snapshot,
                'date_issued' => $dateIssued,
                'certificate_code' => $certificateCode,
                'download_count' => $downloadCount,
                'last_downloaded' => $lastDownloaded,
            ];
            self::$lastInsertId = $id;
            return 1;
        }

        if (strpos($normalized, 'update reviewer_certificates set') !== false) {
            // Last param is the certificate_id (WHERE certificate_id = ?)
            $certificateId = (int) array_pop($params);
            if (!isset(self::$rows[$certificateId])) {
                return 0;
            }
            [
                $reviewerId, $submissionId, $reviewId, $contextId, $templateId,
                $snapshotId, $snapshot, $dateIssued, $certificateCode, $downloadCount, $lastDownloaded,
            ] = $params;

            self::$rows[$certificateId] = [
                'certificate_id' => $certificateId,
                'reviewer_id' => $reviewerId,
                'submission_id' => $submissionId,
                'review_id' => $reviewId,
                'context_id' => $contextId,
                'template_id' => $templateId,
                'snapshot_id' => $snapshotId,
                'snapshot' => $snapshot,
                'date_issued' => $dateIssued,
                'certificate_code' => $certificateCode,
                'download_count' => $downloadCount,
                'last_downloaded' => $lastDownloaded,
            ];
            return 1;
        }

        if (strpos($normalized, 'delete from reviewer_certificates') !== false) {
            $certificateId = (int) ($params[0] ?? 0);
            if (isset(self::$rows[$certificateId])) {
                unset(self::$rows[$certificateId]);
                return 1;
            }
            return 0;
        }

        return 0;
    }

    /**
     * Handle a read query (SELECT) issued via DAO::retrieve().
     * Returns a plain array of row arrays matching the query.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function retrieve(string $sql, array $params = []): array
    {
        $normalized = self::normalize($sql);

        if (strpos($normalized, 'where certificate_id = ?') !== false) {
            $certificateId = (int) ($params[0] ?? 0);
            return isset(self::$rows[$certificateId]) ? [self::$rows[$certificateId]] : [];
        }

        if (strpos($normalized, 'where review_id = ?') !== false) {
            $reviewId = (int) ($params[0] ?? 0);
            foreach (self::$rows as $row) {
                if ((int) $row['review_id'] === $reviewId) {
                    return [$row];
                }
            }
            return [];
        }

        if (strpos($normalized, 'where certificate_code = ?') !== false) {
            $code = (string) ($params[0] ?? '');
            foreach (self::$rows as $row) {
                if ((string) $row['certificate_code'] === $code) {
                    return [$row];
                }
            }
            return [];
        }

        if (strpos($normalized, 'where context_id = ?') !== false && strpos($normalized, 'order by date_issued desc') !== false) {
            $contextId = (int) ($params[0] ?? 0);
            $matches = self::byContext($contextId);
            usort($matches, function ($a, $b) {
                return strcmp((string) $b['date_issued'], (string) $a['date_issued']);
            });
            return $matches;
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function byContext(int $contextId): array
    {
        $matches = [];
        foreach (self::$rows as $row) {
            if ((int) $row['context_id'] === $contextId) {
                $matches[] = $row;
            }
        }
        return $matches;
    }

    private static function normalize(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($sql)));
    }
}
