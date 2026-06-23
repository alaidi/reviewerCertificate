{**
 * plugins/generic/reviewerCertificate/templates/templatesBackend.tpl
 *
 * Backend template-list modal: table of certificate templates with
 * Edit / Set Default / Delete actions, and a "New template" button.
 *}
<div class="rc-templates-backend">
	<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
		<h2 style="margin:0;">{translate key="plugins.generic.reviewerCertificate.templates.heading"}</h2>
		<a class="pkpButton"
		   href="{$manageUrl|escape}&amp;verb=editTemplate"
		   data-modal="ajax"
		   data-title="{translate key="plugins.generic.reviewerCertificate.templates.newTemplate"|escape}">
			{translate key="plugins.generic.reviewerCertificate.templates.newTemplate"}
		</a>
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
					<a class="pkpButton pkpButton--ghost"
					   href="{$manageUrl|escape}&amp;verb=editTemplate&amp;templateId={$tpl->getTemplateId()|escape:'url'}"
					   data-modal="ajax"
					   data-title="{translate key="plugins.generic.reviewerCertificate.templates.edit"|escape}">
						{translate key="plugins.generic.reviewerCertificate.templates.edit"}
					</a>

					{* Set default (only if not already default) *}
					{if !$tpl->getIsDefault()}
					<a class="pkpButton pkpButton--ghost"
					   href="{$manageUrl|escape}&amp;verb=setDefaultTemplate&amp;templateId={$tpl->getTemplateId()|escape:'url'}&amp;csrfToken={csrf type="raw"|escape}"
					   data-confirm="{translate key="plugins.generic.reviewerCertificate.templates.verify.setDefault"|escape}">
						{translate key="plugins.generic.reviewerCertificate.templates.setDefault"}
					</a>
					{/if}

					{* Delete (only if not default, to hint the user) *}
					{if !$tpl->getIsDefault()}
					<a class="pkpButton pkpButton--ghost pkpButton--warningIcon"
					   href="{$manageUrl|escape}&amp;verb=deleteTemplate&amp;templateId={$tpl->getTemplateId()|escape:'url'}&amp;csrfToken={csrf type="raw"|escape}"
					   data-confirm="{translate key="plugins.generic.reviewerCertificate.templates.verify.delete"|escape}">
						{translate key="plugins.generic.reviewerCertificate.templates.delete"}
					</a>
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
