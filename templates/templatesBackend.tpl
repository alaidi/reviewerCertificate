{**
 * plugins/generic/reviewerCertificate/templates/templatesBackend.tpl
 *
 * Backend template-list modal: table of certificate templates with
 * Edit / Set Default / Delete actions, and a "New template" button.
 *}
<div class="rc-templates-backend">
	<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
		<h2 style="margin:0;">{translate key="plugins.generic.reviewerCertificate.templates.heading"}</h2>
		{include file="linkAction/linkAction.tpl" action=$newTemplateAction contextId="reviewerCertificateNewTemplate"}
	</div>

	{if $templates|@count}
	<table class="pkpTable">
		<thead>
			<tr>
				<th>{translate key="plugins.generic.reviewerCertificate.templates.colName"}</th>
				<th>{translate key="plugins.generic.reviewerCertificate.templates.colDefault"}</th>
				<th>{translate key="plugins.generic.reviewerCertificate.templates.colActions"}</th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$templates item=tpl}
			<tr>
				<td>{$tpl->getTemplateName()|escape}</td>
				<td>
					{if $tpl->getIsDefault()}
					<span class="pkpBadge pkpBadge--success">{translate key="plugins.generic.reviewerCertificate.templates.badge.default"}</span>
					{/if}
				</td>
				<td>
					{* Edit *}
					{include file="linkAction/linkAction.tpl" action=$editTemplateActions[$tpl->getTemplateId()] contextId=$tpl->getTemplateId()}

					{* Set default (only if not already default) *}
					{if !$tpl->getIsDefault()}
					{include file="linkAction/linkAction.tpl" action=$setDefaultTemplateActions[$tpl->getTemplateId()] contextId=$tpl->getTemplateId()}
					{/if}

					{* Delete (only if not default, to hint the user) *}
					{if !$tpl->getIsDefault()}
					{include file="linkAction/linkAction.tpl" action=$deleteTemplateActions[$tpl->getTemplateId()] contextId=$tpl->getTemplateId()}
					{/if}
				</td>
			</tr>
			{/foreach}
		</tbody>
	</table>
	{else}
	<p class="pkpNotification">{translate key="plugins.generic.reviewerCertificate.templates.empty"}</p>
	{/if}
</div>
