{**
 * plugins/generic/reviewerCertificate/templates/verify.tpl
 *
 * Public reviewer certificate verification page.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.reviewerCertificate.verify.title"}

{literal}
<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Outfit:wght@400;500;600&family=Amiri:wght@400;700&family=Cairo:wght@400;600&display=swap');

.rcv{
	--ink:#15191f;
	--ink-soft:#5a6271;
	--paper:#f4efe4;
	--paper-2:#fffdf8;
	--gold:#b8975a;
	--gold-deep:#8a6a33;
	--line:rgba(21,25,31,.10);
	--valid:#1f7a5c;
	--invalid:#b23a2c;
	--shadow:0 1px 1px rgba(21,25,31,.04),0 18px 40px -18px rgba(21,25,31,.28);
	box-sizing:border-box;
	position:relative;
	min-height:72vh;
	display:flex;
	align-items:center;
	justify-content:center;
	padding:clamp(2rem,6vw,5rem) 1.25rem;
	font-family:'Outfit',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
	color:var(--ink);
	overflow:hidden;
	isolation:isolate;
}
.rcv *{box-sizing:border-box;}

/* Atmospheric ivory canvas with a faint security guilloché */
.rcv__bg{
	position:absolute;inset:-20%;z-index:-1;
	background:
		radial-gradient(60% 50% at 18% 8%,rgba(184,151,90,.18),transparent 60%),
		radial-gradient(55% 60% at 92% 96%,rgba(31,122,92,.10),transparent 60%),
		var(--paper);
}
.rcv__bg::after{
	content:"";position:absolute;inset:0;opacity:.5;
	background-image:
		repeating-linear-gradient(60deg,rgba(21,25,31,.035) 0 1px,transparent 1px 9px),
		repeating-linear-gradient(-60deg,rgba(21,25,31,.035) 0 1px,transparent 1px 9px);
	-webkit-mask-image:radial-gradient(80% 80% at 50% 40%,#000,transparent 78%);
	mask-image:radial-gradient(80% 80% at 50% 40%,#000,transparent 78%);
}

.rcv__card{
	position:relative;
	width:min(100%,560px);
	background:var(--paper-2);
	border-radius:22px;
	padding:clamp(2rem,5vw,3.25rem) clamp(1.5rem,5vw,3.25rem) clamp(1.75rem,4vw,2.75rem);
	box-shadow:var(--shadow);
	text-align:center;
	overflow:hidden;
}
/* Double hairline certificate frame */
.rcv__card::before{
	content:"";position:absolute;inset:14px;border-radius:14px;
	border:1px solid var(--line);
	box-shadow:inset 0 0 0 4px var(--paper-2),inset 0 0 0 5px rgba(184,151,90,.32);
	pointer-events:none;
}
.rcv__card::after{
	content:"";position:absolute;left:0;right:0;top:0;height:4px;
	background:linear-gradient(90deg,transparent,var(--gold) 22%,var(--gold-deep) 50%,var(--gold) 78%,transparent);
}

.rcv__inner{position:relative;}

/* ── Wax seal ─────────────────────────────────────────── */
.rcv__seal{width:96px;height:96px;margin:0 auto 1.5rem;position:relative;}
.rcv__seal svg{width:100%;height:100%;display:block;overflow:visible;}
.rcv__ring{
	fill:none;stroke:var(--gold);stroke-width:3.5;
	stroke-dasharray:289;stroke-dashoffset:289;
	transform-origin:50% 50%;transform:rotate(-90deg);
	animation:rcv-draw 1s cubic-bezier(.65,0,.35,1) .15s forwards;
}
.rcv__ring-2{fill:none;stroke:rgba(184,151,90,.35);stroke-width:1;}
.rcv__check{
	fill:none;stroke:var(--valid);stroke-width:5.5;stroke-linecap:round;stroke-linejoin:round;
	stroke-dasharray:60;stroke-dashoffset:60;
	animation:rcv-draw .5s cubic-bezier(.65,0,.35,1) .9s forwards;
}
.rcv--invalid .rcv__ring{stroke:var(--invalid);}
.rcv--invalid .rcv__check{stroke:var(--invalid);}

.rcv__eyebrow{
	margin:0 0 .65rem;font-size:.7rem;font-weight:600;letter-spacing:.34em;
	text-transform:uppercase;color:var(--gold-deep);
}
.rcv__title{
	margin:0 0 1.6rem;
	font-family:'Fraunces','Outfit',serif;font-weight:500;
	font-size:clamp(1.65rem,4.5vw,2.3rem);line-height:1.15;letter-spacing:-.01em;
	color:var(--ink);
}
.rcv__title .rcv__rule{
	display:block;width:54px;height:2px;margin:1rem auto 0;
	background:linear-gradient(90deg,transparent,var(--gold),transparent);
}

/* ── Details ──────────────────────────────────────────── */
.rcv__details{margin:0 0 1.75rem;text-align:start;display:grid;gap:0;}
.rcv__row{
	display:grid;grid-template-columns:1fr auto;align-items:baseline;gap:1rem;
	padding:.85rem .25rem;border-top:1px solid var(--line);
}
.rcv__row:last-child{border-bottom:1px solid var(--line);}
.rcv__label{
	font-size:.72rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
	color:var(--ink-soft);white-space:nowrap;
}
.rcv__value{
	font-family:'Fraunces','Outfit',serif;font-size:1.05rem;font-weight:500;
	color:var(--ink);text-align:end;
}
.rcv__value--num{font-variant-numeric:tabular-nums;letter-spacing:.01em;}

/* ── Code chip ────────────────────────────────────────── */
.rcv__code{
	display:inline-flex;align-items:center;gap:.75rem;flex-wrap:wrap;justify-content:center;
	margin-top:.25rem;padding:.7rem 1rem .7rem 1.15rem;
	background:linear-gradient(180deg,rgba(184,151,90,.10),rgba(184,151,90,.05));
	border:1px solid rgba(184,151,90,.35);border-radius:12px;
}
.rcv__code-label{
	font-size:.66rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-deep);
}
.rcv__code code{
	font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace;
	font-size:1.02rem;font-weight:500;letter-spacing:.14em;color:var(--ink);
}
.rcv__copy{
	border:0;background:transparent;cursor:pointer;color:var(--gold-deep);
	display:inline-grid;place-items:center;width:30px;height:30px;border-radius:8px;
	transition:background .2s,color .2s,transform .15s;
}
.rcv__copy:hover{background:rgba(184,151,90,.16);color:var(--ink);}
.rcv__copy:active{transform:scale(.9);}
.rcv__copy svg{width:16px;height:16px;}
.rcv__copy.is-done{color:var(--valid);}

.rcv__note{
	margin:1.6rem auto 0;max-width:38ch;font-size:.92rem;line-height:1.6;color:var(--ink-soft);
}

/* ── Manual form state ────────────────────────────────── */
.rcv__form{margin:.5rem auto 0;max-width:30rem;text-align:start;}
.rcv__field{position:relative;margin:0 0 1rem;}
.rcv__field label{
	display:block;margin:0 0 .5rem;font-size:.72rem;font-weight:600;
	letter-spacing:.16em;text-transform:uppercase;color:var(--ink-soft);text-align:center;
}
.rcv__input{
	width:100%;padding:.95rem 1.1rem;border:1px solid var(--line);border-radius:12px;
	background:var(--paper-2);color:var(--ink);
	font-family:'JetBrains Mono',ui-monospace,monospace;font-size:1.05rem;letter-spacing:.2em;
	text-align:center;text-transform:uppercase;transition:border-color .2s,box-shadow .2s;
}
.rcv__input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 4px rgba(184,151,90,.16);}
.rcv__btn{
	display:block;width:100%;margin-top:.25rem;padding:.95rem 1.5rem;cursor:pointer;
	border:0;border-radius:12px;background:var(--ink);color:#fdfaf3;
	font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;
	letter-spacing:.18em;text-transform:uppercase;
	transition:transform .15s,box-shadow .25s,background .25s;
	box-shadow:0 10px 22px -12px rgba(21,25,31,.6);
}
.rcv__btn:hover{background:#0d1117;transform:translateY(-1px);box-shadow:0 16px 28px -14px rgba(21,25,31,.7);}
.rcv__btn:active{transform:translateY(0);}

/* ── Entrance choreography ────────────────────────────── */
.rcv__reveal{opacity:0;transform:translateY(14px);animation:rcv-rise .7s cubic-bezier(.2,.7,.2,1) forwards;}
.rcv__seal{opacity:0;transform:scale(.85);animation:rcv-pop .7s cubic-bezier(.2,.8,.2,1) .05s forwards;}
.rcv__d1{animation-delay:.18s}.rcv__d2{animation-delay:.26s}.rcv__d3{animation-delay:.34s}
.rcv__d4{animation-delay:.42s}.rcv__d5{animation-delay:.5s}.rcv__d6{animation-delay:.58s}

@keyframes rcv-draw{to{stroke-dashoffset:0}}
@keyframes rcv-rise{to{opacity:1;transform:none}}
@keyframes rcv-pop{to{opacity:1;transform:none}}

/* RTL: elegant Arabic faces */
html[dir="rtl"] .rcv__title,
html[dir="rtl"] .rcv__value{font-family:'Amiri','Fraunces',serif;}
html[dir="rtl"] .rcv,
html[dir="rtl"] .rcv__eyebrow,
html[dir="rtl"] .rcv__label,
html[dir="rtl"] .rcv__btn{font-family:'Cairo','Outfit',sans-serif;}

@media (prefers-reduced-motion:reduce){
	.rcv__reveal,.rcv__seal{animation:none;opacity:1;transform:none;}
	.rcv__ring,.rcv__check{animation:none;stroke-dashoffset:0;}
}
@media (max-width:420px){
	.rcv__row{grid-template-columns:1fr;gap:.2rem;}
	.rcv__value{text-align:start;}
}
</style>
{/literal}

<div class="rcv">
	<div class="rcv__bg" aria-hidden="true"></div>

	<main class="rcv__card{if $certificateCode}{if $isValid} rcv--valid{else} rcv--invalid{/if}{/if}">
		<div class="rcv__inner">

			{if $certificateCode}
				{if $isValid}
					<div class="rcv__seal" aria-hidden="true">
						<svg viewBox="0 0 100 100" role="img">
							<circle class="rcv__ring-2" cx="50" cy="50" r="40"></circle>
							<circle class="rcv__ring" cx="50" cy="50" r="46"></circle>
							<path class="rcv__check" d="M31 51 L44 64 L70 37"></path>
						</svg>
					</div>
					<p class="rcv__eyebrow rcv__reveal rcv__d1">{translate key="plugins.generic.reviewerCertificate.verify.title"}</p>
					<h1 class="rcv__title rcv__reveal rcv__d2">
						{translate key="plugins.generic.reviewerCertificate.verify.valid"}
						<span class="rcv__rule"></span>
					</h1>

					<dl class="rcv__details">
						<div class="rcv__row rcv__reveal rcv__d3">
							<dt class="rcv__label">{translate key="plugins.generic.reviewerCertificate.verify.reviewerName"}</dt>
							<dd class="rcv__value">{$reviewerName|escape}</dd>
						</div>
						<div class="rcv__row rcv__reveal rcv__d4">
							<dt class="rcv__label">{translate key="plugins.generic.reviewerCertificate.verify.journalName"}</dt>
							<dd class="rcv__value">{$journalName|escape}</dd>
						</div>
						<div class="rcv__row rcv__reveal rcv__d5">
							<dt class="rcv__label">{translate key="plugins.generic.reviewerCertificate.verify.dateCompleted"}</dt>
							<dd class="rcv__value rcv__value--num">{$dateCompleted|escape}</dd>
						</div>
					</dl>

					<div class="rcv__code rcv__reveal rcv__d6">
						<span class="rcv__code-label">{translate key="plugins.generic.reviewerCertificate.verify.code"}</span>
						<code id="rcv-code">{$certificateCode|escape}</code>
						<button type="button" class="rcv__copy" data-code="{$certificateCode|escape}" aria-label="Copy" title="Copy">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15V5a2 2 0 0 1 2-2h10"></path></svg>
						</button>
					</div>
				{else}
					<div class="rcv__seal" aria-hidden="true">
						<svg viewBox="0 0 100 100" role="img">
							<circle class="rcv__ring-2" cx="50" cy="50" r="40"></circle>
							<circle class="rcv__ring" cx="50" cy="50" r="46"></circle>
							<path class="rcv__check" d="M37 37 L63 63 M63 37 L37 63"></path>
						</svg>
					</div>
					<p class="rcv__eyebrow rcv__reveal rcv__d1">{translate key="plugins.generic.reviewerCertificate.verify.title"}</p>
					<h1 class="rcv__title rcv__reveal rcv__d2">
						{translate key="plugins.generic.reviewerCertificate.verify.invalid"}
						<span class="rcv__rule"></span>
					</h1>
					<p class="rcv__note rcv__reveal rcv__d3">{translate key="plugins.generic.reviewerCertificate.verify.invalidDescription"}</p>
				{/if}
			{else}
				<p class="rcv__eyebrow rcv__reveal rcv__d1">{translate key="plugins.generic.reviewerCertificate.verify.title"}</p>
				<h1 class="rcv__title rcv__reveal rcv__d2">
					{translate key="plugins.generic.reviewerCertificate.verify.title"}
					<span class="rcv__rule"></span>
				</h1>
				<p class="rcv__note rcv__reveal rcv__d3">{translate key="plugins.generic.reviewerCertificate.verify.description"}</p>

				<form class="rcv__form rcv__reveal rcv__d4" method="get" action="{url page="reviewerCertificateVerify" op="verify"}">
					<div class="rcv__field">
						<label for="code">{translate key="plugins.generic.reviewerCertificate.verify.code"}</label>
						<input type="text" id="code" name="code" class="rcv__input" required maxlength="32" autocomplete="off" spellcheck="false" pattern="[A-Fa-f0-9]{ldelim}8,32{rdelim}" placeholder="••••••••••••••••" />
					</div>
					<button type="submit" class="rcv__btn">{translate key="plugins.generic.reviewerCertificate.verify.button"}</button>
				</form>
			{/if}

		</div>
	</main>
</div>

{literal}
<script>
(function(){
	var btn=document.querySelector('.rcv__copy');
	if(!btn||!navigator.clipboard)return;
	var ico=btn.querySelector('svg');
	btn.addEventListener('click',function(){
		navigator.clipboard.writeText(btn.getAttribute('data-code')).then(function(){
			btn.classList.add('is-done');
			ico.innerHTML='<path d="M20 6 9 17l-5-5"></path>';
			setTimeout(function(){
				btn.classList.remove('is-done');
				ico.innerHTML='<rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15V5a2 2 0 0 1 2-2h10"></path>';
			},1600);
		});
	});
})();
</script>
{/literal}

{include file="frontend/components/footer.tpl"}
