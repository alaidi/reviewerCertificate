{**
 * plugins/generic/reviewerCertificate/templates/settingsForm.tpl
 * Settings form for the Reviewer Certificate plugin.
 *}
<style>
.rc-uploader {
	border: 2px dashed #aaa;
	border-radius: 4px;
	padding: 1rem 1.5rem;
	text-align: center;
	cursor: pointer;
	transition: border-color .15s, background .15s;
	background: #fafafa;
	display: flex;
	align-items: center;
	gap: 1rem;
	flex-wrap: wrap;
	margin-top: .5rem;
}
.rc-uploader:hover, .rc-uploader.rc-drag-over {
	border-color: #2d6a9f;
	background: #f0f6fb;
}
.rc-uploader-icon {
	font-size: 1.8rem;
	color: #2d6a9f;
	flex-shrink: 0;
}
.rc-uploader-text { text-align: left; font-family: Arial, sans-serif; }
.rc-uploader-text strong { display: block; font-size: 13px; color: #333; }
.rc-uploader-text small { font-size: 11px; color: #888; }
.rc-uploader-preview {
	max-height: 64px;
	max-width: 160px;
	border-radius: 3px;
	object-fit: contain;
	border: 1px solid #ddd;
	padding: 2px;
	margin-left: auto;
}
.rc-status { font-size: 12px; font-family: Arial, sans-serif; margin-top: .35rem; }
.rc-status.ok  { color: #2d6a9f; }
.rc-status.err { color: #c00; }
</style>

<script>
$(function() {ldelim}
	$('#reviewerCertificateSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
{rdelim});

function rcSetup(wrapperId, hiddenId, previewId, statusId) {ldelim}
	var wrapper = document.getElementById(wrapperId);
	var input   = wrapper.querySelector('input[type=file]');

	wrapper.addEventListener('click', function() {ldelim} input.click(); {rdelim});

	wrapper.addEventListener('dragover', function(e) {ldelim}
		e.preventDefault();
		wrapper.classList.add('rc-drag-over');
	{rdelim});
	wrapper.addEventListener('dragleave', function() {ldelim}
		wrapper.classList.remove('rc-drag-over');
	{rdelim});
	wrapper.addEventListener('drop', function(e) {ldelim}
		e.preventDefault();
		wrapper.classList.remove('rc-drag-over');
		if (e.dataTransfer.files.length) rcUpload(e.dataTransfer.files[0], wrapperId, hiddenId, previewId, statusId);
	{rdelim});

	input.addEventListener('change', function() {ldelim}
		if (input.files.length) rcUpload(input.files[0], wrapperId, hiddenId, previewId, statusId);
	{rdelim});
{rdelim}

function rcUpload(file, wrapperId, hiddenId, previewId, statusId) {ldelim}
	var statusEl = document.getElementById(statusId);
	statusEl.className = 'rc-status';
	statusEl.textContent = 'Uploading\u2026';

	var formData = new FormData();
	formData.append(file.name, file);

	fetch({$temporaryFileApiUrl|json_encode}, {ldelim}
		method: 'POST',
		credentials: 'same-origin',
		headers: {ldelim}
			'X-Csrf-Token': (pkp && pkp.currentUser) ? pkp.currentUser.csrfToken : ''
		{rdelim},
		body: formData
	{rdelim})
	.then(function(r) {ldelim} return r.json(); {rdelim})
	.then(function(data) {ldelim}
		if (data.id) {ldelim}
			document.getElementById(hiddenId).value = data.id;
			var prev = document.getElementById(previewId);
			prev.src = URL.createObjectURL(file);
			prev.style.display = 'inline-block';
			// Show file name in uploader text
			var label = document.querySelector('#' + wrapperId + ' .rc-uploader-text strong');
			if (label) label.textContent = file.name;
			statusEl.className = 'rc-status ok';
			statusEl.textContent = '\u2713 Ready \u2014 click Save to apply.';
		{rdelim} else {ldelim}
			statusEl.className = 'rc-status err';
			statusEl.textContent = data.error || 'Upload failed.';
		{rdelim}
	{rdelim})
	.catch(function() {ldelim}
		statusEl.className = 'rc-status err';
		statusEl.textContent = 'Upload failed. Please try again.';
	{rdelim});
{rdelim}

$(function() {ldelim}
	rcSetup('rcSignatureUploader',   'signatureTemporaryFileId',   'signaturePreview',   'signatureStatus');
	rcSetup('rcLogoUploader',        'logoTemporaryFileId',        'logoPreview',        'logoStatus');
	rcSetup('rcBackgroundUploader',  'backgroundTemporaryFileId',  'backgroundPreview',  'backgroundStatus');
{rdelim});

/* ── Color theme presets ── */
var RC_THEMES = {ldelim}
	gold:    {ldelim} accent: '#b8975a', journal: '#7a6030', editor: '#222222' {rdelim},
	blue:    {ldelim} accent: '#2d6a9f', journal: '#1f4f78', editor: '#1a1a2e' {rdelim},
	dark:    {ldelim} accent: '#444444', journal: '#555555', editor: '#111111' {rdelim},
	emerald: {ldelim} accent: '#2d7a4f', journal: '#2d5a3a', editor: '#1a2e1a' {rdelim}
{rdelim};

function rcApplyTheme(name) {ldelim}
	var t = RC_THEMES[name];
	if (!t) return;
	function set(textId, pickerId, val) {ldelim}
		var txt = document.getElementById(textId);
		var pk  = document.getElementById(pickerId);
		if (txt) txt.value = val;
		if (pk)  pk.value  = val;
	{rdelim}
	set('accentColor',      'accentColorPicker',      t.accent);
	set('journalNameColor', 'journalNameColorPicker',  t.journal);
	set('editorNameColor',  'editorNameColorPicker',   t.editor);
{rdelim}
</script>

<form class="pkp_form" id="reviewerCertificateSettingsForm" method="post"
	action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">

	{csrf}
	{include file="common/formErrors.tpl"}

	{fbvFormArea id="reviewerCertificateSettingsArea"}

		<p style="margin-bottom:1rem">{translate key="plugins.generic.reviewerCertificate.settings.description"}</p>

		{* ── Certificate Preview ──────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.previewSection"}
			<div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem;flex-wrap:wrap;">
				<label for="rc-preview-id" style="font-family:Arial,sans-serif;font-size:13px;font-weight:bold;white-space:nowrap;">
					{translate key="plugins.generic.reviewerCertificate.settings.previewReviewId"}
				</label>
				<input type="number" id="rc-preview-id" value="{$sampleReviewId|escape}" min="1" step="1"
					style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
				<button type="button" onclick="rcLoadPreview()"
					style="padding:.38rem 1.1rem;background:#2d6a9f;color:#fff;border:none;border-radius:4px;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">
					{translate key="plugins.generic.reviewerCertificate.settings.previewButton"}
				</button>
				<a id="rc-preview-link" href="#" target="_blank"
					style="font-size:12px;color:#2d6a9f;font-family:Arial,sans-serif;text-decoration:underline;display:none;">
					{translate key="plugins.generic.reviewerCertificate.settings.previewOpenTab"}
				</a>
			</div>

			<div id="rc-preview-wrap" style="position:relative;width:100%;padding-top:70.6%;background:#e8e4dc;border:1px solid #ccc;border-radius:4px;overflow:hidden;display:none;">
				<iframe id="rc-preview-frame"
					style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;background:#fff;"
					src="" title="Certificate Preview" sandbox="allow-scripts allow-same-origin">
				</iframe>
				<div id="rc-preview-loading"
					style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(240,236,228,.85);font-family:Arial,sans-serif;font-size:14px;color:#555;">
					{translate key="plugins.generic.reviewerCertificate.settings.previewLoading"}
				</div>
			</div>
			<p class="pkp_help" style="margin-top:.4rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.previewHelp"}
				<code style="background:#eee;padding:1px 5px;border-radius:3px;font-size:11px;font-family:monospace;" id="rc-preview-url-display"></code>
			</p>
		{/fbvFormSection}

		<script>
		var RC_PREVIEW_BASE = {$previewBaseUrl|json_encode};

		function rcLoadPreview() {ldelim}
			var id = parseInt(document.getElementById('rc-preview-id').value, 10);
			if (!id || id < 1) {ldelim} alert('Please enter a valid Review ID.'); return; {rdelim}

			// Send the in-progress form values so the preview reflects
			// unsaved changes. Only safe scalar style/layout fields; the
			// gateway re-validates and clamps every value and saves nothing.
			var rcLiveFields = [
				'signatureSectionOffsetY','signatureSectionPaddingTop','signatureSectionGap',
				'editorBlockOffsetX','editorBlockOffsetY','dateBlockOffsetX','dateBlockOffsetY',
				'editorNameFontSize','editorNameColor','journalNameFontSize','journalNameColor',
				'signatureSize','logoSize','accentColor','textColor','contentOffsetY',
				'qrSize','qrOffsetX','qrOffsetY'
			];
			var params = 'reviewId=' + id + '&rcPreview=1';
			rcLiveFields.forEach(function(name) {ldelim}
				var el = document.getElementById(name);
				if (el && el.value !== '') {ldelim}
					params += '&' + name + '=' + encodeURIComponent(el.value);
				{rdelim}
			{rdelim});

			var rcToggleFields = [
					'showLogo','showJournalName','showDividers','showHeading','showSubheading',
					'showPresentedTo','showReviewerName','showBody','showDateLine','showSignatureSection'
				];
				rcToggleFields.forEach(function(name) {ldelim}
					var el = document.getElementById(name);
					if (el) {ldelim}
						params += '&' + name + '=' + (el.checked ? '1' : '0');
					{rdelim}
				{rdelim});

				var url = RC_PREVIEW_BASE + '?' + params;

			var wrap  = document.getElementById('rc-preview-wrap');
			var frame = document.getElementById('rc-preview-frame');
			var loading = document.getElementById('rc-preview-loading');
			var link  = document.getElementById('rc-preview-link');
			var display = document.getElementById('rc-preview-url-display');

			wrap.style.display = 'block';
			loading.style.display = 'flex';
			frame.src = '';

			link.href = url;
			link.style.display = 'inline';
			if (display) display.textContent = url;

			frame.onload = function() {ldelim} loading.style.display = 'none'; {rdelim};
			frame.src = url;
		{rdelim}
		</script>

		{* ── Editor-in-Chief ─────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.editorSection"}
			{fbvElement type="text" id="editorName" name="editorName" value=$editorName
				multilingual=true
				label="plugins.generic.reviewerCertificate.settings.editorName"
				maxlength="255" size=$fbvStyles.size.LARGE}
			{fbvElement type="text" id="editorTitle" name="editorTitle" value=$editorTitle
				multilingual=true
				label="plugins.generic.reviewerCertificate.settings.editorTitle"
				maxlength="100" size=$fbvStyles.size.MEDIUM}
			<div style="display:flex;gap:1.5rem;align-items:flex-end;flex-wrap:wrap;margin-top:.5rem;">
				<div>
					<label for="editorNameFontSize" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.editorNameFontSize"}
					</label>
					<input type="number" id="editorNameFontSize" name="editorNameFontSize"
						value="{$editorNameFontSize|escape}" min="8" max="72" step="1"
						style="width:80px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (8–72)</span>
				</div>
				<div>
					<label for="editorNameColor" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.editorNameColor"}
					</label>
					<div style="display:flex;align-items:center;gap:.5rem;">
						<input type="color" id="editorNameColorPicker"
							value="{$editorNameColor|escape}"
							style="width:42px;height:34px;padding:2px;border:1px solid #ccc;border-radius:3px;cursor:pointer;"
							oninput="document.getElementById('editorNameColor').value=this.value;">
						<input type="text" id="editorNameColor" name="editorNameColor"
							value="{$editorNameColor|escape}" maxlength="7"
							style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:13px;font-family:monospace;"
							oninput="if(/^#[0-9a-fA-F]{ldelim}6{rdelim}$/.test(this.value))document.getElementById('editorNameColorPicker').value=this.value;">
					</div>
				</div>
			</div>
		{/fbvFormSection}

		{* ── Signature Position (Editor-in-Chief + Date) ─────────────────────── *}
		<div id="rc-section-position">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.positionSection"}
			<p class="pkp_help" style="margin-bottom:.85rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.positionHelp"}
			</p>

			<div style="display:flex;gap:1.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;">
				<div>
					<label for="signatureSectionOffsetY" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.signatureSectionOffsetY"}
					</label>
					<input type="number" id="signatureSectionOffsetY" name="signatureSectionOffsetY"
						value="{$signatureSectionOffsetY|escape}" min="-400" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; up / + down)</span>
				</div>
				<div>
					<label for="signatureSectionPaddingTop" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.signatureSectionPaddingTop"}
					</label>
					<input type="number" id="signatureSectionPaddingTop" name="signatureSectionPaddingTop"
						value="{$signatureSectionPaddingTop|escape}" min="0" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (0–400)</span>
				</div>
				<div>
					<label for="signatureSectionGap" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.signatureSectionGap"}
					</label>
					<input type="number" id="signatureSectionGap" name="signatureSectionGap"
						value="{$signatureSectionGap|escape}" min="0" max="400" step="5"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (0–400)</span>
				</div>
			</div>

			<div style="display:flex;gap:1.5rem;align-items:flex-end;flex-wrap:wrap;">
				<div>
					<label for="editorBlockOffsetX" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.editorBlockOffsetX"}
					</label>
					<input type="number" id="editorBlockOffsetX" name="editorBlockOffsetX"
						value="{$editorBlockOffsetX|escape}" min="-400" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; left / + right)</span>
				</div>
				<div>
					<label for="editorBlockOffsetY" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.editorBlockOffsetY"}
					</label>
					<input type="number" id="editorBlockOffsetY" name="editorBlockOffsetY"
						value="{$editorBlockOffsetY|escape}" min="-400" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; up / + down)</span>
				</div>
				<div>
					<label for="dateBlockOffsetX" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.dateBlockOffsetX"}
					</label>
					<input type="number" id="dateBlockOffsetX" name="dateBlockOffsetX"
						value="{$dateBlockOffsetX|escape}" min="-400" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; left / + right)</span>
				</div>
				<div>
					<label for="dateBlockOffsetY" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.dateBlockOffsetY"}
					</label>
					<input type="number" id="dateBlockOffsetY" name="dateBlockOffsetY"
						value="{$dateBlockOffsetY|escape}" min="-400" max="400" step="2"
						style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; up / + down)</span>
				</div>
			</div>
		{/fbvFormSection}
		</div>

		{* ── Show / Hide & Move Elements ─────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.visibilitySection"}
			<p class="pkp_help" style="margin-bottom:.85rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.visibilityHelp"}
			</p>

			<div class="rc-toggle-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.55rem .75rem;margin-bottom:1.1rem;">
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showLogo" name="showLogo" value="1" {if $showLogo}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showLogo"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showJournalName" name="showJournalName" value="1" {if $showJournalName}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showJournalName"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showDividers" name="showDividers" value="1" {if $showDividers}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showDividers"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showHeading" name="showHeading" value="1" {if $showHeading}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showHeading"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showSubheading" name="showSubheading" value="1" {if $showSubheading}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showSubheading"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showPresentedTo" name="showPresentedTo" value="1" {if $showPresentedTo}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showPresentedTo"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showReviewerName" name="showReviewerName" value="1" {if $showReviewerName}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showReviewerName"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showBody" name="showBody" value="1" {if $showBody}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showBody"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showDateLine" name="showDateLine" value="1" {if $showDateLine}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showDateLine"}
				</label>
				<label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;font-size:14px;">
					<input type="checkbox" id="showSignatureSection" name="showSignatureSection" value="1" {if $showSignatureSection}checked{/if} style="width:16px;height:16px;cursor:pointer;">
					{translate key="plugins.generic.reviewerCertificate.settings.showSignatureSection"}
				</label>
			</div>

			<div>
				<label for="contentOffsetY" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
					{translate key="plugins.generic.reviewerCertificate.settings.contentOffsetY"}
				</label>
				<input type="number" id="contentOffsetY" name="contentOffsetY"
					value="{$contentOffsetY|escape}" min="-400" max="400" step="2"
					style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
				<span style="font-size:12px;color:#888;margin-left:.3rem;">px (&minus; up / + down)</span>
				<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.contentOffsetYHelp"}</p>
			</div>
		{/fbvFormSection}

		{* ── Element Text Overrides ──────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.textOverrideSection"}
			<p class="pkp_help" style="margin-bottom:.85rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.textOverrideHelp"}
			</p>
			<div id="rc-override-journalNameText">
			{fbvElement type="text" id="journalNameText" name="journalNameText" value=$journalNameText
				multilingual=true maxlength="255" size=$fbvStyles.size.LARGE
				label="plugins.generic.reviewerCertificate.settings.journalNameText"}
			</div>
			<div id="rc-override-headingText">
			{fbvElement type="text" id="headingText" name="headingText" value=$headingText
				multilingual=true maxlength="255" size=$fbvStyles.size.LARGE
				label="plugins.generic.reviewerCertificate.settings.headingText"}
			</div>
			<div id="rc-override-subheadingText">
			{fbvElement type="text" id="subheadingText" name="subheadingText" value=$subheadingText
				multilingual=true maxlength="255" size=$fbvStyles.size.LARGE
				label="plugins.generic.reviewerCertificate.settings.subheadingText"}
			</div>
			<div id="rc-override-presentedToText">
			{fbvElement type="text" id="presentedToText" name="presentedToText" value=$presentedToText
				multilingual=true maxlength="255" size=$fbvStyles.size.MEDIUM
				label="plugins.generic.reviewerCertificate.settings.presentedToText"}
			</div>
			<div id="rc-override-completedOnText">
			{fbvElement type="text" id="completedOnText" name="completedOnText" value=$completedOnText
				multilingual=true maxlength="255" size=$fbvStyles.size.MEDIUM
				label="plugins.generic.reviewerCertificate.settings.completedOnText"}
			</div>
			<div id="rc-override-dateLabelText">
			{fbvElement type="text" id="dateLabelText" name="dateLabelText" value=$dateLabelText
				multilingual=true maxlength="100" size=$fbvStyles.size.SMALL
				label="plugins.generic.reviewerCertificate.settings.dateLabelText"}
			</div>
		{/fbvFormSection}

		{* ── Journal Name ────────────────────────────────────────────────────── *}
		<div id="rc-section-journalName">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.journalNameSection"}
			<div style="display:flex;gap:1.5rem;align-items:flex-end;flex-wrap:wrap;margin-top:.25rem;">
				<div>
					<label for="journalNameFontSize" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.journalNameFontSize"}
					</label>
					<input type="number" id="journalNameFontSize" name="journalNameFontSize"
						value="{$journalNameFontSize|escape}" min="8" max="72" step="1"
						style="width:80px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;">
					<span style="font-size:12px;color:#888;margin-left:.3rem;">px (8–72)</span>
				</div>
				<div>
					<label for="journalNameColor" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.journalNameColor"}
					</label>
					<div style="display:flex;align-items:center;gap:.5rem;">
						<input type="color" id="journalNameColorPicker"
							value="{$journalNameColor|escape}"
							style="width:42px;height:34px;padding:2px;border:1px solid #ccc;border-radius:3px;cursor:pointer;"
							oninput="document.getElementById('journalNameColor').value=this.value;">
						<input type="text" id="journalNameColor" name="journalNameColor"
							value="{$journalNameColor|escape}" maxlength="7"
							style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:13px;font-family:monospace;"
							oninput="if(/^#[0-9a-fA-F]{ldelim}6{rdelim}$/.test(this.value))document.getElementById('journalNameColorPicker').value=this.value;">
					</div>
				</div>
			</div>
		{/fbvFormSection}
		</div>

		{* ── Color Theme ─────────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.themeSection"}
			<div style="margin-bottom:.85rem;">
				<span style="font-size:13px;font-weight:bold;display:block;margin-bottom:.5rem;">
					{translate key="plugins.generic.reviewerCertificate.settings.themePresets"}
				</span>
				<div style="display:flex;gap:.6rem;flex-wrap:wrap;">
					<button type="button" onclick="rcApplyTheme('gold')"
						style="padding:.35rem .9rem;border:2px solid #b8975a;background:#fff;border-radius:20px;cursor:pointer;font-size:12px;color:#7a6030;font-weight:bold;">
						&#11044; {translate key="plugins.generic.reviewerCertificate.settings.themeGold"}
					</button>
					<button type="button" onclick="rcApplyTheme('blue')"
						style="padding:.35rem .9rem;border:2px solid #2d6a9f;background:#fff;border-radius:20px;cursor:pointer;font-size:12px;color:#2d6a9f;font-weight:bold;">
						&#11044; {translate key="plugins.generic.reviewerCertificate.settings.themeBlue"}
					</button>
					<button type="button" onclick="rcApplyTheme('dark')"
						style="padding:.35rem .9rem;border:2px solid #444;background:#fff;border-radius:20px;cursor:pointer;font-size:12px;color:#444;font-weight:bold;">
						&#11044; {translate key="plugins.generic.reviewerCertificate.settings.themeDark"}
					</button>
					<button type="button" onclick="rcApplyTheme('emerald')"
						style="padding:.35rem .9rem;border:2px solid #2d7a4f;background:#fff;border-radius:20px;cursor:pointer;font-size:12px;color:#2d7a4f;font-weight:bold;">
						&#11044; {translate key="plugins.generic.reviewerCertificate.settings.themeEmerald"}
					</button>
				</div>
				<p class="pkp_help" style="margin-top:.4rem;">{translate key="plugins.generic.reviewerCertificate.settings.themePresetsHelp"}</p>
			</div>
			<div style="display:flex;align-items:flex-end;gap:.5rem;flex-wrap:wrap;">
				<div>
					<label for="accentColor" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.accentColor"}
					</label>
					<div style="display:flex;align-items:center;gap:.5rem;">
						<input type="color" id="accentColorPicker"
							value="{$accentColor|escape}"
							style="width:42px;height:34px;padding:2px;border:1px solid #ccc;border-radius:3px;cursor:pointer;"
							oninput="document.getElementById('accentColor').value=this.value;">
						<input type="text" id="accentColor" name="accentColor"
							value="{$accentColor|escape}" maxlength="7"
							style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:13px;font-family:monospace;"
							oninput="if(/^#[0-9a-fA-F]{ldelim}6{rdelim}$/.test(this.value))document.getElementById('accentColorPicker').value=this.value;">
					</div>
				</div>
				<div>
					<label for="textColor" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.25rem;">
						{translate key="plugins.generic.reviewerCertificate.settings.textColor"}
					</label>
					<div style="display:flex;align-items:center;gap:.5rem;">
						<input type="color" id="textColorPicker"
							value="{$textColor|escape}"
							style="width:42px;height:34px;padding:2px;border:1px solid #ccc;border-radius:3px;cursor:pointer;"
							oninput="document.getElementById('textColor').value=this.value;">
						<input type="text" id="textColor" name="textColor"
							value="{$textColor|escape}" maxlength="7"
							style="width:90px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:13px;font-family:monospace;"
							oninput="if(/^#[0-9a-fA-F]{ldelim}6{rdelim}$/.test(this.value))document.getElementById('textColorPicker').value=this.value;">
					</div>
					<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.textColorHelp"}</p>
				</div>
			</div>
		{/fbvFormSection}

		{* ── Certificate Body Text ────────────────────────────────────────────── *}
		<div id="rc-section-body">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.bodySection"}
			{fbvElement type="textarea" id="certificateBody" name="certificateBody" value=$certificateBody
				multilingual=true rows="4"
				label="plugins.generic.reviewerCertificate.settings.certificateBody"}
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.certificateBodyHelp"}</p>
		{/fbvFormSection}
		</div>

		{* ── QR Code ──────────────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.qrSection"}
			<label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:14px;">
				<input type="checkbox" name="enableQrCode" value="1" {if $enableQrCode}checked{/if}
					style="width:16px;height:16px;cursor:pointer;">
				{translate key="plugins.generic.reviewerCertificate.settings.enableQrCode"}
			</label>
			<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.qrHelp"}</p>
		{/fbvFormSection}

		{* ── Date Format ─────────────────────────────────────────────────────── *}
		<div id="rc-section-dateFormat">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.dateFormatSection"}
			<label for="dateFormat" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.35rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.dateFormat"}
			</label>
			<select id="dateFormat" name="dateFormat"
				style="width:100%;max-width:340px;padding:.45rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;font-family:Arial,sans-serif;">
				<option value="long" {if $dateFormat === 'long'}selected{/if}>{translate key="plugins.generic.reviewerCertificate.settings.dateFormatLong"}</option>
				<option value="medium" {if $dateFormat === 'medium'}selected{/if}>{translate key="plugins.generic.reviewerCertificate.settings.dateFormatMedium"}</option>
				<option value="short" {if $dateFormat === 'short'}selected{/if}>{translate key="plugins.generic.reviewerCertificate.settings.dateFormatShort"}</option>
				<option value="d-m-Y" {if $dateFormat === 'd-m-Y'}selected{/if}>dd-mm-yyyy</option>
				<option value="d/m/Y" {if $dateFormat === 'd/m/Y'}selected{/if}>dd/mm/yyyy</option>
				<option value="m/d/Y" {if $dateFormat === 'm/d/Y'}selected{/if}>mm/dd/yyyy</option>
				<option value="Y-m-d" {if $dateFormat === 'Y-m-d'}selected{/if}>yyyy-mm-dd</option>
				<option value="Y/m/d" {if $dateFormat === 'Y/m/d'}selected{/if}>yyyy/mm/dd</option>
				<option value="d.m.Y" {if $dateFormat === 'd.m.Y'}selected{/if}>dd.mm.yyyy</option>
				<option value="Y.m.d" {if $dateFormat === 'Y.m.d'}selected{/if}>yyyy.mm.dd</option>
				<option value="d F Y" {if $dateFormat === 'd F Y'}selected{/if}>dd Month yyyy</option>
				<option value="F d, Y" {if $dateFormat === 'F d, Y'}selected{/if}>Month dd, yyyy</option>
				<option value="j F Y" {if $dateFormat === 'j F Y'}selected{/if}>d Month yyyy (no leading zero)</option>
				<option value="d M Y" {if $dateFormat === 'd M Y'}selected{/if}>dd Mon yyyy</option>
				<option value="M d, Y" {if $dateFormat === 'M d, Y'}selected{/if}>Mon dd, yyyy</option>
			</select>
			<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.dateFormatHelp"}</p>

			<label for="dateLocale" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.35rem;margin-top:1rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.dateLocale"}
			</label>
			<select id="dateLocale" name="dateLocale"
				style="width:100%;max-width:340px;padding:.45rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;font-family:Arial,sans-serif;">
				<option value="" {if !$dateLocale}selected{/if}>{translate key="plugins.generic.reviewerCertificate.settings.dateLocaleAuto"}</option>
				<optgroup label="العربية — Arabic">
					<option value="ar" {if $dateLocale === 'ar'}selected{/if}>ar — العربية (عام)</option>
					<option value="ar_IQ" {if $dateLocale === 'ar_IQ'}selected{/if}>ar_IQ — العراق</option>
					<option value="ar_SA" {if $dateLocale === 'ar_SA'}selected{/if}>ar_SA — السعودية</option>
					<option value="ar_EG" {if $dateLocale === 'ar_EG'}selected{/if}>ar_EG — مصر</option>
					<option value="ar_AE" {if $dateLocale === 'ar_AE'}selected{/if}>ar_AE — الإمارات</option>
					<option value="ar_KW" {if $dateLocale === 'ar_KW'}selected{/if}>ar_KW — الكويت</option>
					<option value="ar_BH" {if $dateLocale === 'ar_BH'}selected{/if}>ar_BH — البحرين</option>
					<option value="ar_QA" {if $dateLocale === 'ar_QA'}selected{/if}>ar_QA — قطر</option>
					<option value="ar_OM" {if $dateLocale === 'ar_OM'}selected{/if}>ar_OM — عُمان</option>
					<option value="ar_JO" {if $dateLocale === 'ar_JO'}selected{/if}>ar_JO — الأردن</option>
					<option value="ar_LB" {if $dateLocale === 'ar_LB'}selected{/if}>ar_LB — لبنان</option>
					<option value="ar_SY" {if $dateLocale === 'ar_SY'}selected{/if}>ar_SY — سوريا</option>
					<option value="ar_PS" {if $dateLocale === 'ar_PS'}selected{/if}>ar_PS — فلسطين</option>
					<option value="ar_MA" {if $dateLocale === 'ar_MA'}selected{/if}>ar_MA — المغرب</option>
					<option value="ar_DZ" {if $dateLocale === 'ar_DZ'}selected{/if}>ar_DZ — الجزائر</option>
					<option value="ar_TN" {if $dateLocale === 'ar_TN'}selected{/if}>ar_TN — تونس</option>
					<option value="ar_LY" {if $dateLocale === 'ar_LY'}selected{/if}>ar_LY — ليبيا</option>
					<option value="ar_SD" {if $dateLocale === 'ar_SD'}selected{/if}>ar_SD — السودان</option>
					<option value="ar_YE" {if $dateLocale === 'ar_YE'}selected{/if}>ar_YE — اليمن</option>
				</optgroup>
				<optgroup label="English">
					<option value="en" {if $dateLocale === 'en'}selected{/if}>en — English (generic)</option>
					<option value="en_US" {if $dateLocale === 'en_US'}selected{/if}>en_US — English (US)</option>
					<option value="en_GB" {if $dateLocale === 'en_GB'}selected{/if}>en_GB — English (UK)</option>
					<option value="en_AU" {if $dateLocale === 'en_AU'}selected{/if}>en_AU — English (Australia)</option>
					<option value="en_CA" {if $dateLocale === 'en_CA'}selected{/if}>en_CA — English (Canada)</option>
				</optgroup>
				<optgroup label="Others">
					<option value="fr" {if $dateLocale === 'fr'}selected{/if}>fr — Français</option>
					<option value="fr_FR" {if $dateLocale === 'fr_FR'}selected{/if}>fr_FR — Français (France)</option>
					<option value="fr_CA" {if $dateLocale === 'fr_CA'}selected{/if}>fr_CA — Français (Canada)</option>
					<option value="de" {if $dateLocale === 'de'}selected{/if}>de — Deutsch</option>
					<option value="de_DE" {if $dateLocale === 'de_DE'}selected{/if}>de_DE — Deutsch (Deutschland)</option>
					<option value="es" {if $dateLocale === 'es'}selected{/if}>es — Español</option>
					<option value="es_ES" {if $dateLocale === 'es_ES'}selected{/if}>es_ES — Español (España)</option>
					<option value="tr" {if $dateLocale === 'tr'}selected{/if}>tr — Türkçe</option>
					<option value="tr_TR" {if $dateLocale === 'tr_TR'}selected{/if}>tr_TR — Türkçe (Türkiye)</option>
					<option value="fa" {if $dateLocale === 'fa'}selected{/if}>fa — فارسی</option>
					<option value="fa_IR" {if $dateLocale === 'fa_IR'}selected{/if}>fa_IR — فارسی (ایران)</option>
					<option value="ku" {if $dateLocale === 'ku'}selected{/if}>ku — Kurdish</option>
					<option value="ckb" {if $dateLocale === 'ckb'}selected{/if}>ckb — کوردی (سۆرانی)</option>
				</optgroup>
			</select>
			<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.dateLocaleHelp"}</p>
		{/fbvFormSection}
		</div>

		{* ── PDF Generation ──────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.pdfSection"}
			{if $wkhtmltopdfDetected}
				<p class="pkp_help" style="color:#2d7a4f;margin-bottom:.75rem;">
					&#10003; {translate key="plugins.generic.reviewerCertificate.settings.wkhtmltopdfFound"}:
					<code style="background:#eee;padding:1px 4px;border-radius:2px;font-size:12px;">{$wkhtmltopdfDetected|escape}</code>
				</p>
			{else}
				<p class="pkp_help" style="color:#c0392b;margin-bottom:.75rem;">
					&#10007; {translate key="plugins.generic.reviewerCertificate.settings.wkhtmltopdfNotFound"}
				</p>
			{/if}
			{fbvElement type="text" id="wkhtmltopdfPath" value=$wkhtmltopdfPath
				label="plugins.generic.reviewerCertificate.settings.wkhtmltopdfPath"
				maxlength="500" size=$fbvStyles.size.LARGE}
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.wkhtmltopdfHelp"}</p>
		{/fbvFormSection}

		{* ── Signature ───────────────────────────────────────────────────────── *}
		<div id="rc-section-signature">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.signatureSection"}
			<input type="hidden" id="signatureTemporaryFileId" name="signatureTemporaryFileId" value="">

			{fbvElement type="text" id="signatureUrl" value=$signatureUrl
				label="plugins.generic.reviewerCertificate.settings.signatureUrl"
				maxlength="500" size=$fbvStyles.size.LARGE}
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.signatureUrlHelp"}</p>

			<div id="rcSignatureUploader" class="rc-uploader" role="button" tabindex="0"
				aria-label="{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}">
				<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
				<span class="rc-uploader-icon">&#128247;</span>
				<div class="rc-uploader-text">
					<strong>{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}</strong>
					<small>{translate key="plugins.generic.reviewerCertificate.settings.uploadHelp"}</small>
				</div>
				{if $signatureUrl}
					<img id="signaturePreview" src="{$signatureUrl|escape}" class="rc-uploader-preview" style="display:inline-block">
				{else}
					<img id="signaturePreview" src="" class="rc-uploader-preview" style="display:none">
				{/if}
			</div>
			<div id="signatureStatus" class="rc-status"></div>
			<div style="margin-top:.75rem;">
				<label for="signatureSize" style="font-size:13px;font-weight:bold;">
					{translate key="plugins.generic.reviewerCertificate.settings.imageSize"}
				</label>
				<input type="number" id="signatureSize" name="signatureSize"
					value="{$signatureSize|escape}" min="20" max="300" step="5"
					style="width:80px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;margin-left:.5rem;">
				<span style="font-size:12px;color:#888;margin-left:.3rem;">px (20–300)</span>
			</div>
		{/fbvFormSection}
		</div>

		{* ── Logo ────────────────────────────────────────────────────────────── *}
		<div id="rc-section-logo">
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.logoSection"}
			<input type="hidden" id="logoTemporaryFileId" name="logoTemporaryFileId" value="">

			{fbvElement type="text" id="customLogoUrl" value=$customLogoUrl
				label="plugins.generic.reviewerCertificate.settings.customLogoUrl"
				maxlength="500" size=$fbvStyles.size.LARGE}
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.customLogoUrlHelp"}</p>

			<div id="rcLogoUploader" class="rc-uploader" role="button" tabindex="0"
				aria-label="{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}">
				<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
				<span class="rc-uploader-icon">&#128247;</span>
				<div class="rc-uploader-text">
					<strong>{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}</strong>
					<small>{translate key="plugins.generic.reviewerCertificate.settings.uploadHelp"}</small>
				</div>
				{if $customLogoUrl}
					<img id="logoPreview" src="{$customLogoUrl|escape}" class="rc-uploader-preview" style="display:inline-block">
				{else}
					<img id="logoPreview" src="" class="rc-uploader-preview" style="display:none">
				{/if}
			</div>
			<div id="logoStatus" class="rc-status"></div>
			<div style="margin-top:.75rem;">
				<label for="logoSize" style="font-size:13px;font-weight:bold;">
					{translate key="plugins.generic.reviewerCertificate.settings.imageSize"}
				</label>
				<input type="number" id="logoSize" name="logoSize"
					value="{$logoSize|escape}" min="20" max="300" step="5"
					style="width:80px;padding:.35rem .5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;margin-left:.5rem;">
				<span style="font-size:12px;color:#888;margin-left:.3rem;">px (20–300)</span>
			</div>
		{/fbvFormSection}
		</div>

		{* ── Background image ────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.backgroundSection"}
			<input type="hidden" id="backgroundTemporaryFileId" name="backgroundTemporaryFileId" value="">

			{* Optimal size notice *}
			<div style="display:flex;align-items:flex-start;gap:.75rem;background:#f0f6fb;border:1px solid #b8d4ed;border-radius:4px;padding:.75rem 1rem;margin-bottom:.85rem;">
				<span style="font-size:1.3rem;line-height:1;flex-shrink:0;">&#128210;</span>
				<div style="font-family:Arial,sans-serif;font-size:13px;line-height:1.5;">
					<strong style="color:#1a3a5c;">{translate key="plugins.generic.reviewerCertificate.settings.backgroundSizeTitle"}</strong><br>
					<span style="color:#333;">
						{translate key="plugins.generic.reviewerCertificate.settings.backgroundSizeRecommended"}
						<code style="background:#dde9f5;padding:1px 5px;border-radius:3px;font-size:12px;font-family:monospace;">1920 &times; 1357 px</code>
						{translate key="plugins.generic.reviewerCertificate.settings.backgroundSizeRatio"}
					</span><br>
					<span style="color:#888;font-size:11px;margin-top:2px;display:block;">
						{translate key="plugins.generic.reviewerCertificate.settings.backgroundSizeHint"}
						<code style="background:#eee;padding:1px 4px;border-radius:3px;font-size:11px;font-family:monospace;">960 &times; 678</code>
						&rarr; &times;2 retina &rarr; &times;3 PNG export
					</span>
				</div>
			</div>

			{fbvElement type="text" id="backgroundImageUrl" value=$backgroundImageUrl
				label="plugins.generic.reviewerCertificate.settings.backgroundImageUrl"
				maxlength="500" size=$fbvStyles.size.LARGE}
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.backgroundImageUrlHelp"}</p>

			<div id="rcBackgroundUploader" class="rc-uploader" role="button" tabindex="0"
				aria-label="{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}">
				<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
				<span class="rc-uploader-icon">&#128247;</span>
				<div class="rc-uploader-text">
					<strong>{translate key="plugins.generic.reviewerCertificate.settings.uploadImage"}</strong>
					<small>
						{translate key="plugins.generic.reviewerCertificate.settings.uploadHelp"}
						&mdash; {translate key="plugins.generic.reviewerCertificate.settings.backgroundSizeRecommended"}
						<code style="font-size:10px;">1920&times;1357 px</code>
					</small>
				</div>
				{if $backgroundImageUrl}
					<img id="backgroundPreview" src="{$backgroundImageUrl|escape}" class="rc-uploader-preview" style="display:inline-block;object-fit:cover;">
				{else}
					<img id="backgroundPreview" src="" class="rc-uploader-preview" style="display:none">
				{/if}
			</div>
			<div id="backgroundStatus" class="rc-status"></div>
		{/fbvFormSection}

	{/fbvFormArea}

	<script>
	var RC_TOGGLE_MAP = {ldelim}
		'showJournalName':      ['rc-override-journalNameText', 'rc-section-journalName'],
		'showHeading':          ['rc-override-headingText'],
		'showSubheading':       ['rc-override-subheadingText'],
		'showPresentedTo':      ['rc-override-presentedToText'],
		'showBody':             ['rc-override-completedOnText', 'rc-section-body'],
		'showDateLine':         ['rc-override-dateLabelText', 'rc-section-dateFormat'],
		'showLogo':             ['rc-section-logo'],
		'showSignatureSection': ['rc-section-signature', 'rc-section-position']
	{rdelim};

	function rcUpdateVisibility() {ldelim}
		Object.keys(RC_TOGGLE_MAP).forEach(function(cbId) {ldelim}
			var cb = document.getElementById(cbId);
			if (!cb) return;
			var visible = cb.checked;
			RC_TOGGLE_MAP[cbId].forEach(function(targetId) {ldelim}
				var el = document.getElementById(targetId);
				if (el) {ldelim}
					el.style.display = visible ? '' : 'none';
					var inputs = el.querySelectorAll('input, select, textarea');
					inputs.forEach(function(inp) {ldelim} inp.disabled = !visible; {rdelim});
				{rdelim}
			{rdelim});
		{rdelim});
	{rdelim}

	$(function() {ldelim}
		Object.keys(RC_TOGGLE_MAP).forEach(function(cbId) {ldelim}
			var cb = document.getElementById(cbId);
			if (cb) cb.addEventListener('change', rcUpdateVisibility);
		{rdelim});
		rcUpdateVisibility();
	{rdelim});
	</script>

	{fbvFormButtons}
</form>
