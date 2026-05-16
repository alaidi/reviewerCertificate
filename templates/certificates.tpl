<!DOCTYPE html>
<html lang="{$currentLocale|escape}" {if $isRtl}dir="rtl"{else}dir="ltr"{/if}>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{translate key="plugins.generic.reviewerCertificate.list.title"}</title>
	{if $isRtl}
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
	{/if}
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }

		body {
			font-family: {if $isRtl}'Cairo', 'Amiri', 'Arial', sans-serif{else}'Segoe UI', Arial, sans-serif{/if};
			background: #f0ece4;
			color: #333;
			padding: 2.5rem 1rem;
		}

		.wrap {
			max-width: 880px;
			margin: 0 auto;
			background: #fff;
			border: 1px solid #d9d2c3;
			border-radius: 8px;
			overflow: hidden;
		}

		.head {
			background: #2d6a9f;
			color: #fff;
			padding: 1.6rem 2rem;
		}
		.head h1 { font-size: 1.4rem; margin-bottom: .25rem; }
		.head p  { font-size: .9rem; opacity: .9; }

		.body { padding: 1.5rem 2rem 2rem; }

		table { width: 100%; border-collapse: collapse; }
		th, td {
			padding: .8rem .6rem;
			text-align: {if $isRtl}right{else}left{/if};
			border-bottom: 1px solid #eee;
			font-size: .92rem;
			vertical-align: middle;
		}
		th {
			font-size: .78rem;
			text-transform: uppercase;
			letter-spacing: .04em;
			color: #888;
			border-bottom: 2px solid #ddd;
		}
		tr:last-child td { border-bottom: none; }

		.title-cell { font-weight: 600; color: #2c2c2c; }
		.date-cell  { color: #666; white-space: nowrap; }

		.actions { white-space: nowrap; }
		.btn {
			display: inline-block;
			padding: 6px 14px;
			border-radius: 4px;
			text-decoration: none;
			font-size: .82rem;
			font-weight: 600;
			margin-{if $isRtl}left{else}right{/if}: .4rem;
		}
		.btn-view { background: #2d6a9f; color: #fff; }
		.btn-pdf  { background: #c0392b; color: #fff; }

		.empty {
			text-align: center;
			padding: 3rem 1rem;
			color: #888;
			font-size: .95rem;
		}

		.toolbar {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: .75rem;
			margin-bottom: 1.25rem;
		}
		.search-form { display: flex; gap: .5rem; }
		.search-form input[type="search"] {
			padding: 7px 12px;
			border: 1px solid #ccc;
			border-radius: 4px;
			font-size: .9rem;
			min-width: 240px;
			font-family: inherit;
		}
		.search-form input[type="search"]:focus {
			outline: none;
			border-color: #2d6a9f;
		}
		.search-form button {
			padding: 7px 16px;
			border: none;
			border-radius: 4px;
			background: #2d6a9f;
			color: #fff;
			font-size: .85rem;
			font-weight: 600;
			cursor: pointer;
			font-family: inherit;
		}
		.search-form .btn-clear {
			padding: 7px 14px;
			border: 1px solid #ccc;
			border-radius: 4px;
			background: #fff;
			color: #666;
			text-decoration: none;
			font-size: .85rem;
			font-weight: 600;
		}
		.result-count { color: #888; font-size: .85rem; }

		.pagination {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: .4rem;
			margin-top: 1.5rem;
			flex-wrap: wrap;
		}
		.pagination a, .pagination span {
			display: inline-block;
			min-width: 34px;
			padding: 6px 10px;
			text-align: center;
			border: 1px solid #d9d2c3;
			border-radius: 4px;
			text-decoration: none;
			font-size: .85rem;
			color: #2d6a9f;
		}
		.pagination a:hover { background: #f0ece4; }
		.pagination .current {
			background: #2d6a9f;
			color: #fff;
			border-color: #2d6a9f;
			font-weight: 600;
		}
		.pagination .disabled {
			color: #bbb;
			border-color: #eee;
		}
	</style>
</head>
<body>
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
</body>
</html>
