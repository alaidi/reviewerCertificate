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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
	{/if}
	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
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

		.certificate {
			width: 960px;
			height: 678px;
			max-width: 100%;
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
			bottom: 28px;
			right: 28px;
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

		/* Screen-only responsive rules — must not leak into print/PDF,
		   where wkhtmltopdf's narrow render width would otherwise
		   collapse the certificate to content height. */
		@media screen and (max-width: 980px) {
			.certificate { width: 100%; height: auto; min-height: 0; }
		}

		@media screen and (max-width: 640px) {
			.certificate { padding: 28px 20px; }
			.cert-heading { font-size: 22px; }
			.reviewer-name { font-size: 24px; }
			.reviewer-affiliation { font-size: 13px; }
			.signature-section { gap: 20px; }
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
		[dir="rtl"] .rc-qr-wrap      { right: auto; left: 28px; }
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

	try {ldelim}
		var cert = document.querySelector('.certificate');
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
		btn.textContent = origText;
		btn.disabled = false;
	{rdelim}
{rdelim}
</script>

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
		<div class="logo-wrap">
			<img src="{$logoUrl|escape}" alt="{$journalName|escape}" style="max-height:{$logoSize|escape}px;max-width:{math equation='s*3' s=$logoSize}px;">
		</div>
		{/if}

		{if $showJournalName}
		<div class="journal-name" style="font-size:{$journalNameFontSize|escape}px;color:{$journalNameColor|escape};">{if $journalNameText}{$journalNameText|escape}{else}{$journalName|escape}{/if}</div>
		{/if}

		{if $showDividers}<div class="divider"></div>{/if}
		{if $showHeading}<h1 class="cert-heading">{if $headingText}{$headingText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.heading"}{/if}</h1>{/if}
		{if $showSubheading}<div class="cert-subheading">{if $subheadingText}{$subheadingText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.subheading"}{/if}</div>{/if}

		{if $showDividers}<div class="divider"></div>{/if}

		{if $showPresentedTo}<div class="presented-to">{if $presentedToText}{$presentedToText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.presentedTo"}{/if}</div>{/if}
		{if $showReviewerName}<div class="reviewer-name">{$reviewerName|escape}</div>{/if}
		{if $showReviewerName && $reviewerAffiliation}<div class="reviewer-affiliation">{$reviewerAffiliation|escape}</div>{/if}

		{if $showBody}
		<p class="body-text">
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
		<div class="date-line">
			{if $completedOnText}{$completedOnText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.completedOn"}{/if}
			{$dateCompleted|escape}
		</div>
		{/if}

		{* Signature section *}
		{if $showSignatureSection}
		<div class="signature-section" style="gap:{$signatureSectionGap|escape}px;padding-top:{$signatureSectionPaddingTop|escape}px;transform:translateY({$signatureSectionOffsetY|escape}px);">

			<div class="signature-block" style="transform:translate({$editorBlockOffsetX|escape}px,{$editorBlockOffsetY|escape}px);">
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

			<div class="signature-block" style="transform:translate({$dateBlockOffsetX|escape}px,{$dateBlockOffsetY|escape}px);">
				<div class="signature-img-wrap signature-date-wrap">
					<div style="font-size:14px;font-weight:bold;color:#333;padding-bottom:4px;">{$dateAcknowledged|escape}</div>
				</div>
				<div class="signature-line"></div>
				<div class="signature-label">{if $dateLabelText}{$dateLabelText|escape}{else}{translate key="plugins.generic.reviewerCertificate.certificate.date"}{/if}</div>
			</div>

		</div>
		{/if}
	</div>

	{* QR code for verification *}
	{if $enableQrCode}
	<div class="rc-qr-wrap" id="rc-qr-wrap">
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
				width: 68,
				height: 68,
				colorDark: '{$accentColor|escape}',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.M
			{rdelim});
		{rdelim}
	{rdelim})();
	</script>
	{/if}

</div>

</body>
</html>
