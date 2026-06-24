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
			<div class="head-badge" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="9" r="6"></circle>
					<path d="M9 14.5 7.5 22l4.5-2.5L16.5 22 15 14.5"></path>
					<path d="m9.5 9 1.7 1.7L14.5 7.3"></path>
				</svg>
			</div>
			<div class="head-text">
				<h1>{if $viewAll}{translate key="plugins.generic.reviewerCertificate.list.headingAll"}{else}{translate key="plugins.generic.reviewerCertificate.list.heading"}{/if}</h1>
				<p>{if $viewAll}{$journalName|escape}{else}{$reviewerName|escape} &middot; {$journalName|escape}{/if}</p>
			</div>
		</div>
		<div class="body">
			{if $refreshed}
			<div class="rc-flash" role="status">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
				<span>{translate key="plugins.generic.reviewerCertificate.list.refreshed"}</span>
			</div>
			{/if}
			<div class="toolbar">
				<form class="search-form" method="get" action="{$listUrl|escape}">
					<span class="search-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
					</span>
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
			<ul class="cert-grid">
				{foreach from=$certificates item=cert}
				<li class="cert-card">
					<div class="cert-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>
							<path d="M14 3v5h5"></path>
							<path d="m9 14 1.5 1.5L14 12"></path>
						</svg>
					</div>
					<div class="cert-main">
						<h2 class="cert-title">{$cert.submissionTitle|escape}</h2>
						{if $viewAll}
						<div class="cert-reviewer">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
							<span>{$cert.reviewerName|escape}</span>
						</div>
						{/if}
						<div class="cert-meta">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
							<span>{translate key="plugins.generic.reviewerCertificate.list.colDate"}: {$cert.dateCompleted|escape}</span>
						</div>
					</div>
					<div class="cert-actions">
						<a class="btn btn-view" href="{$cert.viewUrl|escape}" target="_blank" rel="noopener">{translate key="plugins.generic.reviewerCertificate.list.view"}</a>
						{if $cert.pdfUrl}<a class="btn btn-pdf" href="{$cert.pdfUrl|escape}">{translate key="plugins.generic.reviewerCertificate.certificate.downloadPdf"}</a>{/if}
						{if $viewAll && $refreshUrl}
						<form class="refresh-form" method="post" action="{$refreshUrl|escape}">
							{csrf}
							<input type="hidden" name="reviewId" value="{$cert.reviewId|escape}">
							<button type="submit" class="btn btn-refresh" title="{translate key="plugins.generic.reviewerCertificate.list.refreshHelp"}">
								<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.4"></path><path d="M21 3v6h-6"></path></svg>
								{translate key="plugins.generic.reviewerCertificate.list.refresh"}
							</button>
						</form>
						{/if}
					</div>
				</li>
				{/foreach}
			</ul>

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
				<svg class="empty-icon" viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="9" r="6"></circle>
					<path d="M9 14.5 7.5 22l4.5-2.5L16.5 22 15 14.5"></path>
				</svg>
				<p>
					{if $searchQuery !== ''}
					{translate key="plugins.generic.reviewerCertificate.list.noResults" query=$searchQuery}
					{else}
					{translate key="plugins.generic.reviewerCertificate.list.empty"}
					{/if}
				</p>
			</div>
			{/if}
		</div>
	</div>
</div>
{/block}
