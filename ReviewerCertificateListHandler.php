<?php

/**
 * @file plugins/generic/reviewerCertificate/ReviewerCertificateListHandler.php
 *
 * Copyright (c) 2026 Abdul Hadi Mohammed Alaidi
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReviewerCertificateListHandler
 *
 * @brief Backend (dashboard) page that lists every certificate the logged-in
 *        reviewer has earned in the current journal. Rendered inside the
 *        standard OJS backend layout so it appears with the dashboard side
 *        navigation rather than as a standalone new window.
 *
 *        URL: /index.php/<journal>/reviewerCertificates
 */

namespace APP\plugins\generic\reviewerCertificate;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class ReviewerCertificateListHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    protected ReviewerCertificatePlugin $_plugin;

    public function __construct(ReviewerCertificatePlugin $plugin)
    {
        parent::__construct();
        $this->_plugin = $plugin;

        // Any signed-in journal participant who can hold the reviewer role may
        // reach the page; the list itself only ever contains the user's own
        // completed reviews.
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_SITE_ADMIN,
            ],
            ['index']
        );

        // Refreshing a frozen certificate is a manager/editor-only action.
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_SITE_ADMIN,
            ],
            ['refresh']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display the reviewer's certificate list inside the dashboard.
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        // Inline <style> in the page block is stripped when the dashboard Vue app
        // mounts, so the list CSS must load as a registered backend stylesheet.
        $templateMgr->addStyleSheet(
            'reviewerCertificateList',
            $request->getBaseUrl() . '/' . $this->_plugin->getPluginPath() . '/styles/certificates.css',
            ['contexts' => ['backend']]
        );

        // The gateway plugin owns the list-building logic; reuse it so the
        // standalone and in-dashboard pages render the same list.
        $gateway = PluginRegistry::getPlugin('gateways', 'ReviewerCertificateGatewayPlugin');

        $listUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getPath(),
            'reviewerCertificates'
        );
        $refreshUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getPath(),
            'reviewerCertificates',
            'refresh'
        );

        $viewAll = ($gateway && $context && $user)
            ? $gateway->isPrivilegedUser((int) $user->getId(), (int) $context->getId())
            : false;

        $data = ($gateway && $context && $user)
            ? $gateway->buildCertificatesViewData($request, $context, $user, $listUrl, $viewAll, $refreshUrl)
            : [];

        $templateMgr->assign($data);
        $templateMgr->assign([
            'pageTitle' => __('plugins.generic.reviewerCertificate.list.title'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
        ]);

        $templateMgr->display($this->_plugin->getTemplateResource('certificatesBackend.tpl'));
    }

    /**
     * Re-freeze one certificate from the current template/settings (admin only),
     * then return to the list. POST + CSRF + manager/editor role required.
     */
    public function refresh($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user) {
            $request->redirect(null, 'reviewerCertificates');
            return;
        }

        $gateway = PluginRegistry::getPlugin('gateways', 'ReviewerCertificateGatewayPlugin');
        $privileged = $gateway && $gateway->isPrivilegedUser((int) $user->getId(), (int) $context->getId());

        if ($privileged && $request->isPost() && $request->checkCSRF()) {
            $reviewId = (int) $request->getUserVar('reviewId');
            $reviewAssignment = $reviewId ? Repo::reviewAssignment()->get($reviewId) : null;
            if ($reviewAssignment && $reviewAssignment->getDateCompleted()) {
                $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
                if ($submission && (int) $submission->getData('contextId') === (int) $context->getId()) {
                    require_once __DIR__ . '/classes/CertificateGenerator.php';
                    $templateDao = \PKP\db\DAORegistry::getDAO('ReviewerCertificateTemplateDAO');
                    $template = $templateDao->getDefault((int) $context->getId());
                    $generator = new \APP\plugins\generic\reviewerCertificate\classes\CertificateGenerator();
                    $generator->refreeze($this->_plugin, $request, $reviewAssignment, $context, $template);
                    // Rewrite the saved HTML file from the refreshed snapshot.
                    $this->_plugin->generateAndSaveCertificate($request, $reviewAssignment, $context);
                }
            }
        }

        $request->redirect(null, 'reviewerCertificates', null, null, ['refreshed' => 1]);
    }
}
