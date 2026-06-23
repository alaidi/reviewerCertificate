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

        $data = ($gateway && $context && $user)
            ? $gateway->buildCertificatesViewData($request, $context, $user, $listUrl)
            : [];

        $templateMgr->assign($data);
        $templateMgr->assign([
            'pageTitle' => __('plugins.generic.reviewerCertificate.list.title'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
        ]);

        $templateMgr->display($this->_plugin->getTemplateResource('certificatesBackend.tpl'));
    }
}
