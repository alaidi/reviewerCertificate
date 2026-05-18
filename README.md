# Reviewer Certificate Plugin for OJS 3.5

A generic plugin for Open Journal Systems (OJS) 3.5 that automatically generates and delivers elegant reviewer appreciation certificates.

## Screenshot

<!-- Add a screenshot of a generated certificate here.
     Place the image file under docs/ and keep the path below. -->
![Reviewer certificate example](docs/certificate-example.png)

## Features

- **Automatic certificate generation** — triggered when an editor clicks "Thank Reviewer" on a completed review
- **Optional email notification** — the thank-you email carrying the certificate link can be turned off; the certificate is still generated and remains reachable from the reviewer's review page and the My Certificates page
- **Saved static HTML** — certificate is saved to `public/site/images/{username}/` and a direct link is included in the thank-you email
- **Download as Image** — reviewers can download a high-resolution PNG (2880 × 2034 px) of their certificate directly from their submission dashboard
- **Server-side PDF** — full-bleed single-page PDF generated with `wkhtmltopdf` (exact 297 × 210 mm, no page scaling or trailing whitespace)
- **Print / Save as PDF** — browser print dialog with landscape layout
- **My Certificates page** — central list of every certificate a reviewer has earned, with search and pagination, linked from the reviewer's side navigation
- **QR code verification** — optional QR code linking back to the live certificate page, with configurable size (20–300 px) and pixel-precise X/Y positioning
- **Reviewer dashboard button** — "Download Your Certificate" button injected into Step 3 of the review workflow
- **Multilingual content** — Editor name, Editor title and certificate body text each accept a separate value per supported journal language; the certificate renders in the requested language with graceful fallback to the primary locale
- **Locale-aware date formatting** — uses PHP `intl` extension when available; configurable date format with an optional date-language override (Automatic by default, follows the certificate language)
- **Show / Hide & Move controls** — toggle individual certificate elements on/off (journal name, heading, subheading, presented-to label, reviewer name, body text, date line, QR code, logo, signature); text-override inputs and related sections hide automatically when unchecked
- **Content offset** — shift all certificate content up or down by pixel value
- **Customizable appearance**
  - Accent color with preset themes (Gold, Blue, Dark, Emerald)
  - **Text color** for the heading, recipient name and body
  - Journal name font size and color
  - Editor name font size and color
  - Custom certificate body text (per language)
  - Signature image upload with size control
  - Logo image upload with size control
  - Background image upload (recommended size: **1920 × 1357 px**, A4 landscape ratio)
- **Reviewer affiliation** — displays the reviewer's institutional affiliation below their name (shown whenever the reviewer name is visible and affiliation data exists)
- **Arabic / RTL support** — full right-to-left layout with Amiri and Cairo fonts; journal and manuscript titles rendered in italic

## Certificate Dimensions

| | Size | Notes |
|---|---|---|
| CSS display | 960 × 678 px | Exact A4 landscape ratio (297:210) |
| Recommended background | **1920 × 1357 px** | 2× retina quality |
| PNG download | 2880 × 2034 px | 3× scale, ~246 dpi at A4 print size |

## Installation

The plugin folder **must** be named `reviewerCertificate` and live in
`plugins/generic/` of your OJS installation (final path:
`plugins/generic/reviewerCertificate/`). No database changes are made on
install — the plugin only registers a gateway and a few hooks while enabled.

### Option A — release archive (recommended)

1. Download the latest archive from the
   [Releases page](https://github.com/alaidi/reviewerCertificate/releases).
2. Extract it into `plugins/generic/`:

   ```bash
   cd /path/to/ojs/plugins/generic
   unzip ~/Downloads/reviewerCertificate-1.4.0.0.zip
   # ensure the extracted folder is exactly: reviewerCertificate/
   ```

3. Give the web-server user access (it must read the plugin and write to
   `public/` for image uploads and saved certificates):

   ```bash
   chown -R www-data:www-data reviewerCertificate
   ```

4. Log in as **Journal Manager → Settings → Website → Plugins → Generic Plugins**.
5. Tick **Reviewer Certificate** to enable it, then click **Settings** to configure.

### Option B — Git clone

```bash
cd /path/to/ojs/plugins/generic
git clone https://github.com/alaidi/reviewerCertificate.git
git -C reviewerCertificate checkout v1.4.0.0
```

Then enable it from the Plugins page as in Option A.

> Optional: install `wkhtmltopdf` on the server for one-click PDF download
> (auto-detected, or set the path in Settings → PDF Generation).

## Upgrading

Settings are stored as per-journal plugin settings, **not** in the plugin
files, so they survive an in-place replacement — you do not need to
reconfigure after upgrading.

1. **Back up** the existing `plugins/generic/reviewerCertificate/` folder
   and your database.
2. Replace the plugin files with the new version:
   - **Release archive:** delete the old folder, extract the new one in
     its place.
   - **Git:** `cd plugins/generic/reviewerCertificate && git pull && git checkout vX.Y.Z`
3. Restore ownership/permissions if your deployment resets them
   (`chown -R www-data:www-data reviewerCertificate`).
4. Open **Settings → Website → Plugins** in OJS. The new `<release>` from
   `version.xml` is picked up automatically — no `tools/upgrade.php` run
   or manual upgrade step is needed for this generic plugin.
5. Hard-refresh the settings page once (Ctrl/Cmd + Shift + R) so updated
   form assets are reloaded instead of served from browser cache.

> **Upgrading from a pre-1.1.0 single-language version:** previously saved
> Editor name / title / body values are shown in **every** language box so
> they are never silently mislabeled — review each box and correct it
> before saving (see the note under [Configuration](#configuration)).

## Requirements

- OJS 3.5.0 or later
- PHP 7.4+ (PHP `intl` extension recommended for locale-aware dates)
- Internet access on the client browser (CDN libraries: html2canvas, qrcodejs)

## Configuration

Navigate to **Settings → Website → Plugins → Reviewer Certificate → Settings**.
The settings form is organized into the sections below; the diagram shows where
each setting appears on the rendered certificate.

### Certificate layout map

Every numbered callout maps to a row in the [Settings reference](#settings-reference)
table. Letters mark whole-card settings that are not tied to one element.

```
        whole card ⇒ (B) background image      whole content block ⇒ (C) contentOffsetY
                       │                                              │
   (A) ┌───────────────┼──────────────────────────────────────────────┼────────────┐
border │  ┌─┐corner                                              corner┌─┐          │
ornament│ └─┘ ╔══════════════════════════════════════════════════════╗ └─┘         │
& accent│     ║              ╭───────────────╮                        ║             │
color   │     ║              │   [ LOGO ]    │ ───────────────────▶ (1) showLogo    │
        │     ║              ╰───────────────╯                        ║             │
        │     ║          J O U R N A L   N A M E  ──────────────▶ (2) showJournalName│
        │     ║          ───────── ✦ ─────────  (divider) ──────▶ (3) showDividers  │
        │     ║          CERTIFICATE OF APPRECIATION ──────────▶ (4) showHeading    │
        │     ║          FOR PEER REVIEW (subheading) ─────────▶ (5) showSubheading │
        │     ║          ───────── ✦ ─────────  (divider) ──────▶ (3) showDividers  │
        │     ║          P R E S E N T E D   T O ───────────────▶ (6) showPresentedTo│
        │     ║              Reviewer Name ────────────────────▶ (7) showReviewerName│
        │     ║              Reviewer Affiliation ─────────────▶ (8) auto w/ (7)     │
        │     ║      In recognition of the review of "Title"… ─▶ (9) showBody        │
        │     ║          Completed on 18 May 2026 ─────────────▶ (10) showDateLine   │
        │     ║                                                  ║                   │
        │     ║   ____________          ____________             ║                   │
        │     ║   Editor-in-Chief       Date  ───────────────▶ (11) showSignatureSec.│
        │     ║   Editor Name                              ┌────┐║                   │
        │     ║                                            │ QR │─▶ (12) enableQrCode│
        │     ║                                            └────┘║                   │
        │     ╚══════════════════════════════════════════════════╝                   │
        └─────────────────────────────────────────────────────────────────────────┘
              ▲ signature row position ⇒ signatureSectionOffsetY / PaddingTop / Gap,
                editorBlockOffsetX/Y, dateBlockOffsetX/Y   (callout 11)
```

### Settings reference

Defaults, ranges and validation are enforced server-side in
`ReviewerCertificateSettingsForm::execute()`; invalid values silently fall back
to the default. "Localized" fields store one value per supported journal
language.

| # / Sec. | Setting (form key) | Form section | Values / range | Default | Controls on certificate |
|---|---|---|---|---|---|
| 1 | `showLogo` | Show / Hide & Move | on / off | on | Show the logo image |
| — | `customLogoUrl` | Logo | URL or upload | journal logo | Logo image source |
| — | `logoSize` | Logo | 20–300 px | 70 | Logo max height (width = 3×) |
| 2 | `showJournalName` | Show / Hide & Move | on / off | on | Show the journal-name line |
| — | `journalNameText` | Element Text Overrides | localized text | journal name | Override journal-name text |
| — | `journalNameFontSize` | Journal Name | 8–72 px | 12 | Journal-name size |
| — | `journalNameColor` | Journal Name | hex `#rrggbb` | `#7a6030` | Journal-name color |
| 3 | `showDividers` | Show / Hide & Move | on / off | on | Show both ornament dividers |
| 4 | `showHeading` | Show / Hide & Move | on / off | on | Show main heading |
| — | `headingText` | Element Text Overrides | localized text | "Certificate of Appreciation" | Override heading |
| 5 | `showSubheading` | Show / Hide & Move | on / off | on | Show subheading line |
| — | `subheadingText` | Element Text Overrides | localized text | "For Peer Review" | Override subheading |
| 6 | `showPresentedTo` | Show / Hide & Move | on / off | on | Show "presented to" label |
| — | `presentedToText` | Element Text Overrides | localized text | "Presented To" | Override that label |
| 7 | `showReviewerName` | Show / Hide & Move | on / off | on | Show reviewer name |
| 8 | *(automatic)* | — | — | — | Affiliation shows when 7 is on and profile has one |
| 9 | `showBody` | Show / Hide & Move | on / off | on | Show body paragraph |
| — | `certificateBody` | Certificate Body Text | localized HTML | translated default | Body text; `{journalName}` / `{submissionTitle}` placeholders (replaced with the live values), basic HTML |
| 10 | `showDateLine` | Show / Hide & Move | on / off | on | Show "Completed on …" line |
| — | `completedOnText` | Element Text Overrides | localized text | "Completed on" | Prefix before the date |
| — | `dateFormat` | Date Format | long / medium / short / `Y-m-d` / `d-m-Y` / `d/m/Y` / `m/d/Y` / `Y/m/d` / `d.m.Y` / `Y.m.d` / `d F Y` / `F d, Y` / `j F Y` / `d M Y` / `M d, Y` | long | Date display format |
| — | `dateLocale` | Date Format | Automatic or a language code (ar, ar_IQ, en, en_US, fr, de, es, tr, fa, ku, ckb, …) | Automatic | Language used to render the date |
| 11 | `showSignatureSection` | Show / Hide & Move | on / off | on | Show signature + date blocks |
| — | `editorName` | Editor-in-Chief | localized text | empty | Name under signature line |
| — | `editorTitle` | Editor-in-Chief | localized text | "Editor-in-Chief" | Label under signature line |
| — | `editorNameFontSize` | Editor-in-Chief | 8–72 px | 12 | Editor-name size |
| — | `editorNameColor` | Editor-in-Chief | hex `#rrggbb` | `#222222` | Editor-name color |
| — | `signatureUrl` | Signature | URL or upload | none | Signature image above the line |
| — | `signatureSize` | Signature | 20–300 px | 70 | Signature max height (width = 3×) |
| — | `dateLabelText` | Element Text Overrides | localized text | "Date" | Label under the date block |
| — | `signatureSectionOffsetY` | Signature Position | −400…400 px | 0 | Move whole signature row up (−) / down (+) |
| — | `signatureSectionPaddingTop` | Signature Position | 0–400 px | 0 | Extra space above the signature row |
| — | `signatureSectionGap` | Signature Position | 0–400 px | 80 | Horizontal gap between the two blocks |
| — | `editorBlockOffsetX` / `…OffsetY` | Signature Position | −400…400 px | 0 | Nudge editor block left/right, up/down |
| — | `dateBlockOffsetX` / `…OffsetY` | Signature Position | −400…400 px | 0 | Nudge date block left/right, up/down |
| — | `sendEmail` | Email Notification | on / off | on | Email the certificate link to the reviewer when thanked (off = certificate still generated, no email sent) |
| 12 | `enableQrCode` | QR Code | on / off | on | Show verification QR (bottom corner) |
| — | `qrSize` | QR Code | 20–300 px | 68 | QR code width & height |
| — | `qrOffsetX` | QR Code | −400…400 px | 0 | Move QR left (−) / right (+) |
| — | `qrOffsetY` | QR Code | −400…400 px | 0 | Move QR up (−) / down (+) |
| C | `contentOffsetY` | Show / Hide & Move | −400…400 px | 0 | Shift all content up (−) / down (+) |
| A | `accentColor` | Color Theme | hex `#rrggbb` | `#b8975a` | Borders, corners, seal, dividers, QR |
| — | `textColor` | Color Theme | hex `#rrggbb` | `#1a1a2e` | Heading, reviewer name and body text |
| — | *theme presets* | Color Theme | Gold / Blue / Dark / Emerald | Gold | One-click set of accent + journal + editor colors |
| B | `backgroundImageUrl` | Background image | URL or upload | none | Full-bleed background (rec. **1920 × 1357 px**) |
| — | `wkhtmltopdfPath` | PDF Generation | filesystem path | auto-detected | Path to `wkhtmltopdf` for server-side PDF |

> **Note:** the **Certificate Preview** section at the top of the form has no
> stored settings — it renders a live sample (using a real completed-review ID)
> and reflects unsaved style/layout changes without saving anything.

> **Multilingual note:** when upgrading from a single-language version, any
> previously saved Editor name / title / body value is shown in **every**
> language box so it is never silently mislabeled — review each language box
> and correct it before saving.

## Changelog

### 1.4.0.1 — 2026-05-18

- **Fixed:** re-uploaded signature / logo / background images were cached by the browser and wkhtmltopdf because every upload reused a fixed filename — uploads now get a unique filename (cache-busted) and the previously stored managed file is removed to avoid orphans
- **Docs:** expanded README with detailed Installation and Upgrading instructions, a screenshot placeholder, developer contact, and a free + sponsor License/Support model

### 1.4.0.0 — 2026-05-18

- **New:** Email Notification toggle — the thank-you email carrying the certificate link can be turned off; the certificate is still generated and saved, and stays reachable from the reviewer's review page and the My Certificates page (defaults to on, preserving existing behavior)

### 1.3.0.1 — 2026-05-18

- **Docs:** corrected the certificate-body placeholder tokens in the README — they are `{journalName}` and `{submissionTitle}` (no `$`), matching the form help text and the actual replacement code

### 1.3.0.0 — 2026-05-18

- **New:** reviewer affiliation displayed below the reviewer name on the certificate (uses the user's localized affiliation from their OJS profile)
- **New:** Show / Hide toggle checkboxes — each certificate element (journal name, heading, subheading, presented-to, reviewer name, body text, date line, QR code, logo, signature) can be toggled on/off; related text-override inputs and settings sections hide automatically when unchecked
- **New:** Content offset control — shift all certificate content up or down by a pixel value (−400 to +400)
- **New:** QR code size (20–300 px) and position controls (X/Y offset, −400 to +400 px) for pixel-precise placement
- **Fixed:** Text color setting was never persisted — `textColor` is now read from the form and pre-filled with the saved value (was always reverting to the default `#1a1a2e`)

### 1.2.0.0 — 2026-05-16

- **New:** Signature & Date position controls — nudge the Editor-in-Chief and Date blocks up/down/left/right, adjust the space above the signature row and the gap between blocks (7 pixel-precise settings, clamped server-side)
- **New:** live preview — clicking **Preview** now reflects unsaved style/layout changes (position, sizes, fonts, colors) without saving; values are re-validated and clamped, and only privileged users may override
- **Fixed:** plugin settings page returned HTTP 500 (`Class "…Locale" not found`, then `undefined method getLocaleNames()`) — added the correct `PKP\facades\Locale` import and removed an unused, broken locale-names lookup

### 1.1.0.1 — 2026-05-16

- **Fixed:** plugin failed to register on some OJS installs with `Class "…ReviewerCertificateGatewayPlugin" not found` — sibling plugin classes are now loaded explicitly instead of relying on namespace autoloading (also fixes the scheduler error)

### 1.1.0.0 — 2026-05-16

- **New:** multilingual Editor name, Editor title and certificate body (one input per supported language) with locale-aware rendering and fallback
- **New:** configurable **Text color** for heading, recipient name and body
- **New:** server-side PDF always regenerates from the current template/settings
- **Fixed:** PDF no longer renders small at the top of the page with trailing whitespace — output is now a single full-bleed 297 × 210 mm page (screen-only responsive rules no longer leak into print)
- **Fixed:** journal and manuscript titles render in italic in the certificate body
- **Fixed:** legacy single-language settings are no longer guessed onto the wrong locale on upgrade

### 1.0.0.0 — 2026-03-22

- Initial release

## Author

**Abdul Hadi Mohammed Alaidi** — developer & maintainer
Email: <alaidi@uowasit.edu.iq>
GitHub: <https://github.com/alaidi/reviewerCertificate>
Created: 2026-03-27

## License

This plugin is free and open-source software, released under the
**GNU General Public License v3.0 (GPLv3)**, consistent with the OJS
platform license. You may use, modify and redistribute it under the GPLv3
terms. It is provided **with no warranty**.

The full plugin is — and stays — **free for everyone**: free to download,
test, and run in production, for journals of any size. There are no paid
tiers, usage limits, or feature locks; nothing in the plugin counts or
caps certificate generation.

## Support the project

This plugin is developed and maintained on a voluntary basis. If it is
useful to your journal, please consider **sponsoring** its development —
sponsorship is entirely optional and funds ongoing maintenance, OJS
compatibility updates and new features, keeping the plugin free and open
for the whole community.

- 💖 **Sponsor:** [GitHub Sponsors](https://github.com/sponsors/alaidi)
- ⭐ **Star** the [repository](https://github.com/alaidi/reviewerCertificate)
  so others can find it
- 🐛 **Contribute:** report issues or send pull requests on
  [GitHub](https://github.com/alaidi/reviewerCertificate/issues)
- ✉️ **Contact:** Abdul Hadi Mohammed Alaidi — <alaidi@uowasit.edu.iq>

Sponsorship is a thank-you, not a license condition — it does not modify
or add any terms to the GPLv3 grant above.
