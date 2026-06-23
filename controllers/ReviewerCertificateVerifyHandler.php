<?php

/**
 * @file plugins/generic/reviewerCertificate/controllers/ReviewerCertificateVerifyHandler.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3.
 *
 * @class ReviewerCertificateVerifyHandler
 *
 * @brief Public endpoint that verifies a reviewer certificate by its code.
 */

namespace APP\plugins\generic\reviewerCertificate\controllers;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\facades\Locale;

class ReviewerCertificateVerifyHandler extends Handler
{
    private $plugin;

    public function setPlugin($plugin): void
    {
        $this->plugin = $plugin;
    }

    /** Public — no authorization required. */
    public function authorize($request, &$args, $roleAssignments)
    {
        return true;
    }

    public function verify($args, $request)
    {
        // Extract code from path args, query string, or REQUEST_URI fallback
        $code = $args[0] ?? $request->getUserVar('code');
        if (!$code) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('#/reviewerCertificateVerify/verify/([A-Fa-f0-9]{8,32})#', $uri, $m)) {
                $code = $m[1];
            }
        }
        if ($code) {
            $code = strtoupper(trim((string) $code));
            if (!preg_match('/^[A-F0-9]{8,32}$/', $code)) {
                $code = null;
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificateCode', $code);
        $templateMgr->assign('isValid', false);

        if ($code) {
            /** @var \APP\plugins\generic\reviewerCertificate\classes\ReviewerCertificateDAO $dao */
            $dao = DAORegistry::getDAO('ReviewerCertificateDAO');
            $context = $request->getContext();
            $cert = $dao ? $dao->getByCertificateCode($code) : null;

            // Context isolation: reject codes that belong to another journal.
            if ($cert && $context && (int) $cert->getContextId() !== (int) $context->getId()) {
                $cert = null;
            }

            if ($cert) {
                $contextDao = Application::getContextDAO();
                $certContext = $contextDao->getById($cert->getContextId());
                $journalName = $certContext ? $certContext->getLocalizedName() : '';

                // Try to get reviewer name and date from the frozen per-cert snapshot first.
                // The snapshot's dateCompleted is already a formatted string, so it is
                // used verbatim; only raw fallback values get reformatted below.
                $reviewerName = '';
                $dateCompleted = '';
                $dateFromSnapshot = false;

                $snapshotJson = $cert->getSnapshot();
                if ($snapshotJson) {
                    $snapshot = json_decode($snapshotJson, true);
                    if (is_array($snapshot)) {
                        $reviewerName = $snapshot['reviewerName'] ?? '';
                        $dateCompleted = $snapshot['dateCompleted'] ?? '';
                        $dateFromSnapshot = ($dateCompleted !== '');
                    }
                }

                // Fall back to live lookups when snapshot data is absent.
                if (!$reviewerName) {
                    $reviewer = Repo::user()->get((int) $cert->getReviewerId());
                    if ($reviewer) {
                        $reviewerName = $reviewer->getFullName();
                    }
                }

                if (!$dateCompleted) {
                    // Prefer review_assignments.date_completed (Pattern 10)
                    $reviewAssignment = Repo::reviewAssignment()->get((int) $cert->getReviewId());
                    if ($reviewAssignment) {
                        $dateCompleted = $reviewAssignment->getDateCompleted() ?? '';
                    }
                    // Final fallback: date_issued on the certificate row
                    if (!$dateCompleted) {
                        $dateCompleted = $cert->getDateIssued() ?? '';
                    }
                }

                // Format for display only when the value is a raw DB timestamp;
                // the snapshot already stores a frozen, formatted string.
                $locale = Locale::getLocale();
                $formattedDate = ($dateCompleted && !$dateFromSnapshot)
                    ? $this->_formatDate($dateCompleted, $locale)
                    : $dateCompleted;

                if ($reviewerName) {
                    $templateMgr->assign([
                        'isValid' => true,
                        'reviewerName' => $reviewerName,
                        'journalName' => $journalName,
                        'dateCompleted' => $formattedDate,
                    ]);
                }
            }
        }

        return $templateMgr->display($this->plugin->getTemplateResource('verify.tpl'));
    }

    /**
     * Format a date string using IntlDateFormatter (long style) or a PHP fallback.
     */
    private function _formatDate(string $dateStr, string $locale): string
    {
        $timestamp = strtotime($dateStr);
        if (!$timestamp) {
            return $dateStr;
        }
        if (class_exists('\IntlDateFormatter')) {
            $fmt = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                null,
                \IntlDateFormatter::GREGORIAN
            );
            $result = $fmt->format($timestamp);
            if ($result !== false) {
                return $result;
            }
        }
        return date('F d, Y', $timestamp);
    }
}
