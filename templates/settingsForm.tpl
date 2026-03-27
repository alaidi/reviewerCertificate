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

		{* ── Editor-in-Chief ─────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.editorSection"}
			{fbvElement type="text" id="editorName" value=$editorName
				label="plugins.generic.reviewerCertificate.settings.editorName"
				maxlength="255" size=$fbvStyles.size.LARGE}
			{fbvElement type="text" id="editorTitle" value=$editorTitle
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

		{* ── Journal Name ────────────────────────────────────────────────────── *}
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
			</div>
		{/fbvFormSection}

		{* ── Certificate Body Text ────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.bodySection"}
			<label for="certificateBody" style="display:block;font-size:13px;font-weight:bold;margin-bottom:.35rem;">
				{translate key="plugins.generic.reviewerCertificate.settings.certificateBody"}
			</label>
			<textarea id="certificateBody" name="certificateBody" rows="4"
				style="width:100%;padding:.5rem;border:1px solid #ccc;border-radius:3px;font-size:14px;font-family:Arial,sans-serif;resize:vertical;"
				placeholder="{translate key="plugins.generic.reviewerCertificate.settings.certificateBodyPlaceholder"}">{$certificateBody|escape}</textarea>
			<p class="pkp_help">{translate key="plugins.generic.reviewerCertificate.settings.certificateBodyHelp"}</p>
		{/fbvFormSection}

		{* ── QR Code ──────────────────────────────────────────────────────────── *}
		{fbvFormSection title="plugins.generic.reviewerCertificate.settings.qrSection"}
			<label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:14px;">
				<input type="checkbox" name="enableQrCode" value="1" {if $enableQrCode}checked{/if}
					style="width:16px;height:16px;cursor:pointer;">
				{translate key="plugins.generic.reviewerCertificate.settings.enableQrCode"}
			</label>
			<p class="pkp_help" style="margin-top:.35rem;">{translate key="plugins.generic.reviewerCertificate.settings.qrHelp"}</p>
		{/fbvFormSection}

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

		{* ── Logo ────────────────────────────────────────────────────────────── *}
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

	{fbvFormButtons}
</form>
