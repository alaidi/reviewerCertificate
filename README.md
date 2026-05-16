# Reviewer Certificate Plugin for OJS 3.5

A generic plugin for Open Journal Systems (OJS) 3.5 that automatically generates and delivers elegant reviewer appreciation certificates.

## Features

- **Automatic certificate generation** — triggered when an editor clicks "Thank Reviewer" on a completed review
- **Saved static HTML** — certificate is saved to `public/site/images/{username}/` and a direct link is included in the thank-you email
- **Download as Image** — reviewers can download a high-resolution PNG (2880 × 2034 px) of their certificate directly from their submission dashboard
- **Server-side PDF** — full-bleed single-page PDF generated with `wkhtmltopdf` (exact 297 × 210 mm, no page scaling or trailing whitespace)
- **Print / Save as PDF** — browser print dialog with landscape layout
- **My Certificates page** — central list of every certificate a reviewer has earned, with search and pagination, linked from the reviewer's side navigation
- **QR code verification** — optional QR code linking back to the live certificate page
- **Reviewer dashboard button** — "Download Your Certificate" button injected into Step 3 of the review workflow
- **Multilingual content** — Editor name, Editor title and certificate body text each accept a separate value per supported journal language; the certificate renders in the requested language with graceful fallback to the primary locale
- **Locale-aware date formatting** — uses PHP `intl` extension when available; configurable date format with an optional date-language override (Automatic by default, follows the certificate language)
- **Customizable appearance**
  - Accent color with preset themes (Gold, Blue, Dark, Emerald)
  - **Text color** for the heading, recipient name and body
  - Journal name font size and color
  - Editor name font size and color
  - Custom certificate body text (per language)
  - Signature image upload with size control
  - Logo image upload with size control
  - Background image upload (recommended size: **1920 × 1357 px**, A4 landscape ratio)
- **Arabic / RTL support** — full right-to-left layout with Amiri and Cairo fonts; journal and manuscript titles rendered in italic

## Certificate Dimensions

| | Size | Notes |
|---|---|---|
| CSS display | 960 × 678 px | Exact A4 landscape ratio (297:210) |
| Recommended background | **1920 × 1357 px** | 2× retina quality |
| PNG download | 2880 × 2034 px | 3× scale, ~246 dpi at A4 print size |

## Installation

1. Copy the `reviewerCertificate` folder to `plugins/generic/` in your OJS installation.
2. Log in as Journal Manager → Settings → Website → Plugins → Generic Plugins.
3. Enable **Reviewer Certificate** and click **Settings** to configure.

## Requirements

- OJS 3.5.0 or later
- PHP 7.4+ (PHP `intl` extension recommended for locale-aware dates)
- Internet access on the client browser (CDN libraries: html2canvas, qrcodejs)

## Configuration

Navigate to **Settings → Website → Plugins → Reviewer Certificate → Settings** to configure:

- Editor name and title — one input per supported journal language
- Editor name font size and color
- Journal name font size and color
- Color theme preset or custom accent color
- Text color (heading, recipient name and body)
- Custom certificate body text — one input per language, supports basic HTML
- Date format and optional date-language override
- QR code enable/disable
- Path to the `wkhtmltopdf` binary (auto-detected if on a common system path)
- Background, logo, and signature image uploads

> **Multilingual note:** when upgrading from a single-language version, any
> previously saved Editor name / title / body value is shown in **every**
> language box so it is never silently mislabeled — review each language box
> and correct it before saving.

## Changelog

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

**Abdul Hadi Mohammed Alaidi**
Created: 2026-03-27

## License

This plugin is released under the GNU General Public License v3.0, consistent with the OJS platform license.
