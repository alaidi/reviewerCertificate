{**
 * plugins/generic/reviewerCertificate/templates/certificatesBackend.tpl
 *
 * Reviewer's "My Certificates" list, rendered inside the OJS backend dashboard
 * layout (with the side navigation) so the menu item opens in-page rather than
 * as a standalone new window. Styles load via styles/certificates.css.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="rc-list" {if $isRtl}dir="rtl"{else}dir="ltr"{/if}>
	<div class="wrap">
		<div class="head">
			<h1>{translate key="plugins.generic.reviewerCertificate.list.heading"}</h1>
			<p>{$reviewerName|escape} &middot; {$journalName|escape}</p>
		</div>
		<div class="body">
			<div class="toolbar">
				<form class="search-form" method="get" action="{$listUrl|escape}">
					<input type="search" name="searchQuery" value="{$searchQuery|escape}" placeholder="{translate key="plugins.generic.reviewerCertificate.list.searchPlaceholder"}" aria-label="{translate key="plugins.generic.reviewerCertificate.list.searchPlaceholder"}">
					<button type="submit">{translate key="plugins.generic.reviewerCertificate.list.search"}</button>
					{if $searchQuery !== ''}
					<a class="btn-clear" href="{$listUrl|escape}">{translate key="plugins.generic.reviewerCertificate.list.clear"}</a>
					{/if}
				</form>
				{if $totalCount}
				<span class="result-count">{translate key="plugins.generic.reviewerCertificate.list.showing" start=$rangeStart end=$rangeEnd total=$totalCount}</span>
				{/if}
			</div>
			{if $certificates|@count}
			<table>
				<thead>
					<tr>
						<th>{translate key="plugins.generic.reviewerCertificate.list.colSubmission"}</th>
						<th>{translate key="plugins.generic.reviewerCertificate.list.colDate"}</th>
						<th>{translate key="plugins.generic.reviewerCertificate.list.colActions"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$certificates item=cert}
					<tr>
						<td class="title-cell">{$cert.submissionTitle|escape}</td>
						<td class="date-cell">{$cert.dateCompleted|escape}</td>
						<td class="actions">
							<a class="btn btn-view" href="{$cert.viewUrl|escape}" target="_blank">{translate key="plugins.generic.reviewerCertificate.list.view"}</a>
							{if $cert.pdfUrl}<a class="btn btn-pdf" href="{$cert.pdfUrl|escape}">{translate key="plugins.generic.reviewerCertificate.certificate.downloadPdf"}</a>{/if}
						</td>
					</tr>
					{/foreach}
				</tbody>
			</table>

			{if $totalPages > 1}
			<div class="pagination">
				{if $page > 1}
				<a href="{$listUrl|escape}?searchQuery={$searchQuery|escape:'url'}&amp;page={$page-1}" rel="prev">&laquo; {translate key="plugins.generic.reviewerCertificate.list.prev"}</a>
				{else}
				<span class="disabled">&laquo; {translate key="plugins.generic.reviewerCertificate.list.prev"}</span>
				{/if}

				{foreach from=$pageNumbers item=p}
				{if $p == $page}
				<span class="current">{$p}</span>
				{else}
				<a href="{$listUrl|escape}?searchQuery={$searchQuery|escape:'url'}&amp;page={$p}">{$p}</a>
				{/if}
				{/foreach}

				{if $page < $totalPages}
				<a href="{$listUrl|escape}?searchQuery={$searchQuery|escape:'url'}&amp;page={$page+1}" rel="next">{translate key="plugins.generic.reviewerCertificate.list.next"} &raquo;</a>
				{else}
				<span class="disabled">{translate key="plugins.generic.reviewerCertificate.list.next"} &raquo;</span>
				{/if}
			</div>
			{/if}
			{else}
			<div class="empty">
				{if $searchQuery !== ''}
				{translate key="plugins.generic.reviewerCertificate.list.noResults" query=$searchQuery}
				{else}
				{translate key="plugins.generic.reviewerCertificate.list.empty"}
				{/if}
			</div>
			{/if}
		</div>
	</div>
</div>
{/block}
