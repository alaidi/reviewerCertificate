<!DOCTYPE html>
<html lang="{$currentLocale|escape}" {if $isRtl}dir="rtl"{else}dir="ltr"{/if}>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{translate key="plugins.generic.reviewerCertificate.certificate.heading"} — {$reviewerName|escape}</title>
	{if $isRtl}
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
	{/if}
	{if $enableQrCode}
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	{/if}
	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }

		body {
			font-family: {if $isRtl}'Amiri', 'Cairo', 'Arial', sans-serif{else}'Georgia', 'Times New Roman', serif{/if};
			background: #f0ece4;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 2rem;
		}

		.btn-bar {
			display: flex;
			gap: .6rem;
			margin-bottom: 1.5rem;
			flex-wrap: wrap;
			justify-content: center;
		}
		.print-btn {
			padding: 0.6rem 2rem;
			background: #2d6a9f;
			color: #fff;
			border: none;
			border-radius: 4px;
			font-size: 1rem;
			cursor: pointer;
			font-family: Arial, sans-serif;
			text-decoration: none;
			display: inline-block;
		}
		.print-btn:hover { background: #1f4f78; }
		.pdf-btn { background: #c0392b; }
		.pdf-btn:hover { background: #922b21; }

		/* Fixed-size canvas wrapper. The certificate keeps its native
		   960x678 dimensions on every device; on narrow screens it is
		   scaled down as a whole (see rcFit) so the layout never
		   reflows and mobile matches desktop exactly. */
		.cert-viewport {
			width: 960px;
			max-width: 100%;
		}

		.certificate {
			width: 960px;
			height: 678px;
			transform-origin: top left;
			background: #fff;
			position: relative;
			padding: 36px 56px 38px;
			box-shadow: 0 8px 40px rgba(0,0,0,0.15);
			overflow: hidden;
		}

		.certificate.has-bg {
			background-size: cover;
			background-position: center;
			background-repeat: no-repeat;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		.certificate.has-bg .content {
			padding: 30px;
			border-radius: 4px;
		}

		/* Outer border */
		.certificate::before {
			content: '';
			position: absolute;
			inset: 14px;
			border: 3px solid {$accentColor|escape};
			pointer-events: none;
		}
		/* Inner border */
		.certificate::after {
			content: '';
			position: absolute;
			inset: 20px;
			border: 1px solid {$accentColor|escape};
			pointer-events: none;
			opacity: 0.55;
		}

		/* Corner ornaments */
		.corner { position: absolute; width: 48px; height: 48px; border-color: {$accentColor|escape}; border-style: solid; }
		.corner-tl { top: 8px;    left: 8px;   border-width: 4px 0 0 4px; }
		.corner-tr { top: 8px;    right: 8px;  border-width: 4px 4px 0 0; }
		.corner-bl { bottom: 8px; left: 8px;   border-width: 0 0 4px 4px; }
		.corner-br { bottom: 8px; right: 8px;  border-width: 0 4px 4px 0; }

		.content {
			position: relative;
			z-index: 1;
			text-align: center;
			display: flex;
			flex-direction: column;
			height: 100%;
		}

		/* Logo */
		.logo-wrap { margin-bottom: 0.7rem; min-height: 10px; }
		.logo-wrap img { max-height: 56px; max-width: 180px; object-fit: contain; }

		.journal-name {
			font-family: Arial, sans-serif;
			font-size: 12px;
			font-weight: bold;
			letter-spacing: 3px;
			text-transform: uppercase;
			color: #7a6030;
			margin-bottom: 0.8rem;
		}

		.seal {
			width: 56px; height: 56px;
			margin: 0 auto 0.7rem;
			border: 3px solid {$accentColor|escape};
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 24px;
			color: {$accentColor|escape};
		}

		.cert-heading {
			font-size: 32px;
			font-weight: normal;
			color: {$textColor|escape};
			letter-spacing: 2px;
			margin-bottom: 0.2rem;
		}
		.cert-subheading {
			font-size: 12px;
			letter-spacing: 5px;
			text-transform: uppercase;
			color: #7a6030;
			margin-bottom: 1rem;
		}

		.divider {
			width: 80px; height: 2px;
			background: linear-gradient(to right, transparent, {$accentColor|escape}, transparent);
			margin: 0 auto 1rem;
		}

		.presented-to {
			font-family: Arial, sans-serif;
			font-size: 11px;
			letter-spacing: 3px;
			text-transform: uppercase;
			color: #888;
			margin-bottom: 0.3rem;
		}

		.reviewer-name {
			font-size: 34px;
			font-style: italic;
			color: {$textColor|escape};
			margin-bottom: 0.15rem;
			font-weight: normal;
		}

		.reviewer-affiliation {
			font-size: 16px;
			color: {$textColor|escape};
			opacity: 0.75;
			margin-bottom: 1rem;
			font-style: italic;
		}

		.body-text {
			font-size: 14px;
			line-height: 1.7;
			color: {$textColor|escape};
			max-width: 600px;
			margin: 0 auto 0.9rem;
		}
		.submission-title { font-style: italic; font-weight: bold; color: #1a1a2e; }

		.date-line {
			font-family: Arial, sans-serif;
			font-size: 12px;
			color: #888;
			margin-bottom: 1.4rem;
		}

		/* Signature section */
		.signature-section {
			display: flex;
			justify-content: center;
			gap: 80px;
			align-items: flex-start;
			margin-top: auto;
		}

		.signature-block {
			text-align: center;
			min-width: 180px;
			position: relative;
		}

		.signature-img-wrap {
			position: absolute;
			bottom: 100%;
			left: 50%;
			transform: translateX(-50%);
			display: flex;
			align-items: flex-end;
			justify-content: center;
			margin-bottom: 4px;
		}
		.signature-img-wrap img {
			max-height: 56px;
			max-width: 180px;
			object-fit: contain;
		}

		.signature-date-wrap {
			position: absolute;
			bottom: 100%;
			left: 50%;
			transform: translateX(-50%);
			min-height: 36px;
			margin-bottom: 4px;
		}

		.signature-line {
			width: 100%;
			border-bottom: 1px solid #aaa;
			margin-bottom: 0.4rem;
		}

		.signature-label {
			font-family: Arial, sans-serif;
			font-size: 11px;
			letter-spacing: 2px;
			text-transform: uppercase;
			color: #555;
		}
		.signature-name {
			font-family: Arial, sans-serif;
			font-size: 12px;
			color: #222;
			font-weight: bold;
			margin-top: 3px;
		}

		/* QR code */
		.rc-qr-wrap {
			position: absolute;
			bottom: calc(28px + {$qrOffsetY|default:0}px);
			right: calc(28px - {$qrOffsetX|default:0}px);
			z-index: 10;
			text-align: center;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		.rc-qr-wrap canvas, .rc-qr-wrap img { display: block; }
		.rc-qr-label {
			font-size: 8px;
			color: #aaa;
			font-family: Arial, sans-serif;
			letter-spacing: 1px;
			margin-top: 2px;
			text-transform: uppercase;
		}

		@media print {
			@page {
				size: 297mm 210mm;
				margin: 0;
			}

			html, body {
				width: 297mm;
				height: 210mm;
				margin: 0;
				padding: 0;
				background: #fff;
				display: block;
				position: relative;
			}

			.btn-bar { display: none !important; }

			/* Neutralise the on-screen scale transform so wkhtmltopdf
			   / browser print renders at full page size. Stylesheet
			   !important overrides the inline transform set by rcFit. */
			.cert-viewport {
				width: auto !important;
				max-width: none !important;
				height: auto !important;
				overflow: visible !important;
			}
			.certificate { transform: none !important; }

			/* Absolute fill guarantees the certificate spans the whole
			   page in wkhtmltopdf, which does not reliably honour an
			   explicit mm height on a normal-flow element. */
			.certificate {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				width: auto;
				height: auto;
				max-width: none;
				box-shadow: none;
				padding: 10mm 15mm;
				page-break-inside: avoid;
				break-inside: avoid;
				overflow: hidden;
				display: flex;
				flex-direction: column;
				justify-content: center;
			}
		}

		/* Screen-only: shrink body padding on small screens so the
		   scaled certificate gets as much width as possible. The
		   certificate itself is never reflowed — rcFit() scales the
		   whole canvas proportionally instead. */
		@media screen and (max-width: 640px) {
			body { padding: 1rem 0.5rem; }
		}

		/* RTL overrides */
		[dir="rtl"] .cert-heading    { font-family: 'Amiri', serif; font-size: 36px; letter-spacing: 0; }
		[dir="rtl"] .reviewer-name   { font-family: 'Amiri', serif; font-size: 40px; }
		[dir="rtl"] .reviewer-affiliation { font-family: 'Cairo', sans-serif; font-size: 16px; letter-spacing: 0; }
		[dir="rtl"] .journal-name    { font-family: 'Cairo', sans-serif; letter-spacing: 0; }
		[dir="rtl"] .cert-subheading { font-family: 'Cairo', sans-serif; letter-spacing: 0; }
		[dir="rtl"] .presented-to    { font-family: 'Cairo', sans-serif; letter-spacing: 0; }
		[dir="rtl"] .body-text       { font-family: 'Amiri', serif; font-size: 16px; line-height: 2; }
		[dir="rtl"] .date-line       { font-family: 'Cairo', sans-serif; letter-spacing: 0; }
		[dir="rtl"] .signature-label { font-family: 'Cairo', sans-serif; letter-spacing: 0; }
		[dir="rtl"] .signature-name  { font-family: 'Cairo', sans-serif; }
		[dir="rtl"] .rc-qr-wrap      { right: auto; left: calc(28px + {$qrOffsetX|default:0}px); }

		{if $rcPreviewMode|default:false}
		/* Drag-to-place affordance — only in the settings live preview. */
		.rc-pos.rc-draggable {
			cursor: grab;
			outline: 2px dashed {$accentColor|escape};
			outline-offset: 3px;
			border-radius: 2px;
			touch-action: none;
			user-select: none;
		}
		.rc-pos.rc-draggable:hover { outline-color: #2d6a9f; }
		.rc-pos.rc-draggable.rc-dragging { cursor: grabbing; outline-style: solid; }
		/* Anchor the drag hint to in-content elements without disturbing the
		   QR wrap (absolute), which lives outside .content. */
		.content .rc-pos.rc-draggable { position: relative; }
		.rc-pos-drag-hint {
			position: absolute;
			top: -22px;
			left: 50%;
			transform: translateX(-50%);
			background: #2d6a9f;
			color: #fff;
			font-family: Arial, sans-serif;
			font-size: 9px;
			line-height: 1;
			letter-spacing: .5px;
			white-space: nowrap;
			padding: 4px 7px;
			border-radius: 3px;
			pointer-events: none;
			opacity: 0;
			transition: opacity .15s;
			z-index: 20;
		}
		.rc-pos.rc-draggable:hover .rc-pos-drag-hint,
		.rc-pos.rc-draggable.rc-dragging .rc-pos-drag-hint { opacity: 1; }
		{/if}
	</style>
</head>
<body>

<div class="btn-bar">
	<button class="print-btn" onclick="window.print()">
		{translate key="plugins.generic.reviewerCertificate.certificate.print"}
	</button>
	<button class="print-btn pdf-btn" id="rc-img-btn" onclick="rcDownloadImage(this)">
		{translate key="plugins.generic.reviewerCertificate.certificate.downloadImage"}
	</button>
	{if $pdfUrl}
	<a class="print-btn" href="{$pdfUrl|escape}" style="background:#7b3fb0;">
		{translate key="plugins.generic.reviewerCertificate.certificate.downloadPdfServer"}
	</a>
	{/if}
</div>

<script>
async function rcDownloadImage(btn) {ldelim}
	if (typeof html2canvas === 'undefined') {ldelim}
		alert('Image library not loaded. Please check your internet connection.');
		return;
	{rdelim}

	var origText = btn.textContent;
	btn.textContent = '{translate key="plugins.generic.reviewerCertificate.certificate.generating"}';
	btn.disabled = true;

	var btnBar = document.querySelector('.btn-bar');
	var qrWrap = document.getElementById('rc-qr-wrap');
	if (btnBar) btnBar.style.display = 'none';
	if (qrWrap) qrWrap.style.display = 'none';

	// Capture at full native size, not the on-screen mobile scale.
	var rcVp = document.getElementById('rc-cert-viewport');
	if (rcVp) rcVp.style.height = '';

	try {ldelim}
		var cert = document.querySelector('.certificate');
		cert.style.transform = 'none';
		var canvas = await html2canvas(cert, {ldelim}
			scale: 3,
			useCORS: true,
			backgroundColor: '#ffffff',
			logging: false,
			imageTimeout: 8000
		{rdelim});

		var link = document.createElement('a');
		link.download = 'reviewer_certificate_{$reviewId|escape}.png';
		link.href = canvas.toDataURL('image/png');
		link.click();
	{rdelim} catch (err) {ldelim}
		alert('Could not generate image: ' + err.message);
	{rdelim} finally {ldelim}
		if (btnBar) btnBar.style.display = '';
		if (qrWrap) qrWrap.style.display = '';
		if (typeof window.rcFit === 'function') window.rcFit();
		btn.textContent = origText;
		btn.disabled = false;
	{rdelim}
{rdelim}
</script>

<div class="cert-viewport" id="rc-cert-viewport">
{if $backgroundImageUrl}
<div class="certificate has-bg" style="background-image:url('{$backgroundImageUrl|escape}')">
{else}
<div class="certificate">
{/if}
	<div class="corner corner-tl"></div>
	<div class="corner corner-tr"></div>
	<div class="corner corner-bl"></div>
	<div class="corner corner-br"></div>

	<div class="content" style="transform:translateY({$contentOffsetY|default:0|escape}px);">

		{* Journal logo *}
		{if $showLogo && $logoUrl}
		<div class="logo-wrap rc-pos" data-rc-key="logo" data-rc-store="map"
			data-rc-x="{$elementOffsets.logo.x|default:0}" data-rc-y="{$elementOffsets.logo.y|default:0}"
			style="transform:translate({$elementOffsets.logo.x|default:0}px,{$elementOffsets.logo.y|default:0}px);">
			{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}
			<img src="{$logoUrl|escape}" alt="{$journalName|escape}" style="max-height:{$logoSize|escape}px;max-width:{math equation='s*3' s=$logoSize}px;">
		</div>
		{/if}

		{if $showJournalName}
		<div class="journal-name rc-pos" data-rc-key="journalName" data-rc-store="map"
			data-rc-x="{$elementOffsets.journalName.x|default:0}" data-rc-y="{$elementOffsets.journalName.y|default:0}"
			style="font-size:{$journalNameFontSize|escape}px;color:{$journalNameColor|escape};transform:translate({$elementOffsets.journalName.x|default:0}px,{$elementOffsets.journalName.y|default:0}px);">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{if $journalNameText}{$journalNameText|escape}{else}{$journalName|escape}{/if}</div>
		{/if}

		{if $showDividers}<div class="divider"></div>{/if}
		{if $showHeading}<h1 class="cert-heading rc-pos" data-rc-key="heading" data-rc-store="map" data-rc-x="{$elementOffsets.heading.x|default:0}" data-rc-y="{$elementOffsets.heading.y|default:0}" style="transform:translate({$elementOffsets.heading.x|default:0}px,{$elementOffsets.heading.y|default:0}px);{if $elementFontSizes.heading|default:0}font-size:{$elementFontSizes.heading}px;{/if}">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{if $headingText}{$headingText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.heading"}{/if}</h1>{/if}
		{if $showSubheading}<div class="cert-subheading rc-pos" data-rc-key="subheading" data-rc-store="map" data-rc-x="{$elementOffsets.subheading.x|default:0}" data-rc-y="{$elementOffsets.subheading.y|default:0}" style="transform:translate({$elementOffsets.subheading.x|default:0}px,{$elementOffsets.subheading.y|default:0}px);{if $elementFontSizes.subheading|default:0}font-size:{$elementFontSizes.subheading}px;{/if}">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{if $subheadingText}{$subheadingText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.subheading"}{/if}</div>{/if}

		{if $showDividers}<div class="divider"></div>{/if}

		{if $showPresentedTo}<div class="presented-to rc-pos" data-rc-key="presentedTo" data-rc-store="map" data-rc-x="{$elementOffsets.presentedTo.x|default:0}" data-rc-y="{$elementOffsets.presentedTo.y|default:0}" style="transform:translate({$elementOffsets.presentedTo.x|default:0}px,{$elementOffsets.presentedTo.y|default:0}px);{if $elementFontSizes.presentedTo|default:0}font-size:{$elementFontSizes.presentedTo}px;{/if}">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{if $presentedToText}{$presentedToText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.presentedTo"}{/if}</div>{/if}
		{if $showReviewerName}<div class="reviewer-name rc-pos" data-rc-key="reviewerName" data-rc-store="map" data-rc-x="{$elementOffsets.reviewerName.x|default:0}" data-rc-y="{$elementOffsets.reviewerName.y|default:0}" style="transform:translate({$elementOffsets.reviewerName.x|default:0}px,{$elementOffsets.reviewerName.y|default:0}px);{if $elementFontSizes.reviewerName|default:0}font-size:{$elementFontSizes.reviewerName}px;{/if}">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{$reviewerName|escape}</div>{/if}
		{if $showReviewerName && $reviewerAffiliation}<div class="reviewer-affiliation rc-pos" data-rc-key="reviewerAffiliation" data-rc-store="map" data-rc-x="{$elementOffsets.reviewerAffiliation.x|default:0}" data-rc-y="{$elementOffsets.reviewerAffiliation.y|default:0}" style="transform:translate({$elementOffsets.reviewerAffiliation.x|default:0}px,{$elementOffsets.reviewerAffiliation.y|default:0}px);{if $elementFontSizes.reviewerAffiliation|default:0}font-size:{$elementFontSizes.reviewerAffiliation}px;{/if}">{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}{$reviewerAffiliation|escape}</div>{/if}

		{if $showBody}
		<p class="body-text rc-pos" data-rc-key="body" data-rc-store="map"
			data-rc-x="{$elementOffsets.body.x|default:0}" data-rc-y="{$elementOffsets.body.y|default:0}"
			style="transform:translate({$elementOffsets.body.x|default:0}px,{$elementOffsets.body.y|default:0}px);{if $elementFontSizes.body|default:0}font-size:{$elementFontSizes.body}px;{/if}">
			{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}
			{if $certificateBodyHtml}
				{$certificateBodyHtml nofilter}
			{else}
				{capture assign="submissionTitleHtml"}<em>{$submissionTitle|escape}</em>{/capture}
				{translate key="plugins.generic.reviewerCertificate.certificate.body"
					journalName=$journalName|escape
					submissionTitle=$submissionTitleHtml}
			{/if}
		</p>
		{/if}

		{if $showDateLine}
		<div class="date-line rc-pos" data-rc-key="dateLine" data-rc-store="map"
			data-rc-x="{$elementOffsets.dateLine.x|default:0}" data-rc-y="{$elementOffsets.dateLine.y|default:0}"
			style="transform:translate({$elementOffsets.dateLine.x|default:0}px,{$elementOffsets.dateLine.y|default:0}px);{if $elementFontSizes.dateLine|default:0}font-size:{$elementFontSizes.dateLine}px;{/if}">
			{if $rcPreviewMode|default:false}<span class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</span>{/if}
			{if $completedOnText}{$completedOnText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.completedOn"}{/if}
			{$dateCompleted|escape}
		</div>
		{/if}

		{* Signature section *}
		{if $showSignatureSection}
		<div class="signature-section" style="gap:{$signatureSectionGap|escape}px;padding-top:{$signatureSectionPaddingTop|escape}px;transform:translateY({$signatureSectionOffsetY|escape}px);">

			<div class="signature-block rc-pos" data-rc-key="editorBlock"
				data-rc-x="{$editorBlockOffsetX|default:0}" data-rc-y="{$editorBlockOffsetY|default:0}"
				style="transform:translate({$editorBlockOffsetX|escape}px,{$editorBlockOffsetY|escape}px);">
				{if $rcPreviewMode|default:false}<div class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</div>{/if}
				{if $signatureUrl}
					<div class="signature-img-wrap">
						<img src="{$signatureUrl|escape}" alt="{translate key="plugins.generic.reviewerCertificate.certificate.editorSignature"}" style="max-height:{$signatureSize|escape}px;max-width:{math equation='s*3' s=$signatureSize}px;">
					</div>
				{/if}
				<div class="signature-line"></div>
				<div class="signature-label">{$editorTitle|escape}</div>
				{if $editorName}
					<div class="signature-name"
						style="font-size:{$editorNameFontSize|escape}px;color:{$editorNameColor|escape};">
						{$editorName|escape}
					</div>
				{/if}
			</div>

			{if $showDateBlock}
			<div class="signature-block rc-pos" data-rc-key="dateBlock"
				data-rc-x="{$dateBlockOffsetX|default:0}" data-rc-y="{$dateBlockOffsetY|default:0}"
				style="transform:translate({$dateBlockOffsetX|escape}px,{$dateBlockOffsetY|escape}px);">
				{if $rcPreviewMode|default:false}<div class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</div>{/if}
				<div class="signature-img-wrap signature-date-wrap">
					<div style="font-size:14px;font-weight:bold;color:#333;padding-bottom:4px;">{$dateAcknowledged|escape}</div>
				</div>
				<div class="signature-line"></div>
				<div class="signature-label">{if $dateLabelText}{$dateLabelText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.date"}{/if}</div>
			</div>
			{/if}

		</div>
		{/if}
	</div>

	{* QR code for verification *}
	{if $enableQrCode}
	<div class="rc-qr-wrap rc-pos" id="rc-qr-wrap" data-rc-key="qr" data-rc-mode="rel" data-rc-ysign="-1"
		data-rc-x="{$qrOffsetX|default:0}" data-rc-y="{$qrOffsetY|default:0}">
		{if $rcPreviewMode|default:false}<div class="rc-pos-drag-hint">{translate key="plugins.generic.reviewerCertificate.certificate.dragHint"}</div>{/if}
		<div id="rc-qrcode"></div>
		<div class="rc-qr-label">{translate key="plugins.generic.reviewerCertificate.certificate.verify"}</div>
	</div>
	<script>
	(function() {ldelim}
		var el = document.getElementById('rc-qrcode');
		if (el && typeof QRCode !== 'undefined') {ldelim}
			var rcUrl = {if $certificateUrl}'{$certificateUrl|escape:"javascript"}'{else}window.location.href{/if};
			new QRCode(el, {ldelim}
				text: rcUrl,
				width: {$qrSize|default:68},
				height: {$qrSize|default:68},
				colorDark: '{$accentColor|escape}',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.M
			{rdelim});
		{rdelim}
	{rdelim})();
	</script>
	{/if}

	{if $rcPreviewMode|default:false}
	<script>
	{* Live-preview only: drag any .rc-pos element around the certificate and
	   push its offsets back to the settings form (see settingsForm.tpl). *}
	(function() {ldelim}
		var cert = document.querySelector('.certificate');
		if (!cert) return;
		var NAT_W = 960;
		function clamp(v) {ldelim} return Math.max(-400, Math.min(400, v)); {rdelim}

		var els = document.querySelectorAll('.rc-pos');
		for (var i = 0; i < els.length; i++) {ldelim} initOne(els[i]); {rdelim}

		function initOne(el) {ldelim}
			var key = el.getAttribute('data-rc-key');
			if (!key) return;
			var mode  = el.getAttribute('data-rc-mode') || 'abs';
			var store = el.getAttribute('data-rc-store') || 'field';
			var ysign = parseInt(el.getAttribute('data-rc-ysign'), 10) || 1;
			var curX = parseInt(el.getAttribute('data-rc-x'), 10) || 0;
			var curY = parseInt(el.getAttribute('data-rc-y'), 10) || 0;
			el.classList.add('rc-draggable');

			var dragging = false, startPX = 0, startPY = 0, startX = 0, startY = 0, scale = 1;

			function apply(dx, dy) {ldelim}
				{* abs: element is positioned by transform translate(fieldX, fieldY);
				   rel: element is anchored (bottom/right) — translate by raw delta. *}
				el.style.transform = (mode === 'abs')
					? 'translate(' + curX + 'px,' + curY + 'px)'
					: 'translate(' + dx + 'px,' + dy + 'px)';
			{rdelim}
			function send() {ldelim}
				if (window.parent === window) return;
				window.parent.postMessage({ldelim}
					source: 'rc-pos', type: 'move', key: key, store: store,
					offsetX: Math.round(curX), offsetY: Math.round(curY)
				{rdelim}, '*');
			{rdelim}

			el.addEventListener('pointerdown', function(e) {ldelim}
				e.stopPropagation();
				dragging = true;
				startPX = e.clientX; startPY = e.clientY;
				startX = curX; startY = curY;
				var r = cert.getBoundingClientRect();
				scale = r.width > 0 ? (r.width / NAT_W) : 1;
				el.classList.add('rc-dragging');
				try {ldelim} el.setPointerCapture(e.pointerId); {rdelim} catch (err) {ldelim}{rdelim}
				e.preventDefault();
			{rdelim});
			el.addEventListener('pointermove', function(e) {ldelim}
				if (!dragging) return;
				var dx = (e.clientX - startPX) / scale;
				var dy = (e.clientY - startPY) / scale;
				curX = clamp(startX + dx);
				curY = clamp(startY + ysign * dy);
				apply(dx, dy);
				send();
			{rdelim});
			function end(e) {ldelim}
				if (!dragging) return;
				dragging = false;
				el.classList.remove('rc-dragging');
				try {ldelim} el.releasePointerCapture(e.pointerId); {rdelim} catch (err) {ldelim}{rdelim}
				send();
			{rdelim}
			el.addEventListener('pointerup', end);
			el.addEventListener('pointercancel', end);
		{rdelim}
	{rdelim})();
	</script>
	{/if}

</div>
</div>

<script>
(function() {ldelim}
	function rcFit() {ldelim}
		var vp   = document.getElementById('rc-cert-viewport');
		var cert = vp ? vp.querySelector('.certificate') : null;
		if (!vp || !cert) return;
		// Native canvas size is fixed (960x678); offsetWidth/Height
		// ignore the transform so they stay the true dimensions.
		var natW = 960, natH = 678;
		cert.style.transform = 'none';
		var avail = vp.clientWidth;
		var scale = Math.min(1, avail / natW);
		cert.style.transform = 'scale(' + scale + ')';
		vp.style.height = (natH * scale) + 'px';
	{rdelim}

	window.rcFit = rcFit;
	window.addEventListener('load', rcFit);
	window.addEventListener('resize', rcFit);
	window.addEventListener('orientationchange', rcFit);

	// Clear the scale transform before printing so the @media print
	// rules render at full page size, then restore it afterwards.
	window.addEventListener('beforeprint', function() {ldelim}
		var vp = document.getElementById('rc-cert-viewport');
		var cert = vp ? vp.querySelector('.certificate') : null;
		if (cert) cert.style.transform = 'none';
		if (vp) vp.style.height = '';
	{rdelim});
	window.addEventListener('afterprint', rcFit);

	rcFit();
{rdelim})();
</script>

</body>
</html>
