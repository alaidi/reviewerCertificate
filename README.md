# Reviewer Certificate Plugin for OJS 3.5

A generic plugin for Open Journal Systems (OJS) 3.5 that automatically generates and delivers elegant reviewer appreciation certificates.

## Features

- **Automatic certificate generation** — triggered when an editor clicks "Thank Reviewer" on a completed review
- **Saved static HTML** — certificate is saved to `public/site/images/{username}/` and a direct link is included in the thank-you email
- **Download as Image** — reviewers can download a high-resolution PNG (2880 × 2034 px) of their certificate directly from their submission dashboard
- **Print / Save as PDF** — browser print dialog with A4 landscape layout
- **QR code verification** — optional QR code linking back to the live certificate page
- **Reviewer dashboard button** — "Download Your Certificate" button injected into Step 3 of the review workflow
- **Locale-aware date formatting** — uses PHP `intl` extension when available, falls back gracefully
- **Customizable appearance**
  - Accent color with preset themes (Gold, Blue, Dark, Emerald)
  - Journal name font size and color
  - Editor name font size and color
  - Custom certificate body text
  - Signature image upload with size control
  - Logo image upload with size control
  - Background image upload (recommended size: **1920 × 1357 px**, A4 landscape ratio)
- **Arabic / RTL support** — full right-to-left layout with Amiri and Cairo fonts

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

- Editor name, title, font size, and color
- Journal name font size and color
- Color theme preset or custom accent color
- Custom certificate body text (supports basic HTML)
- QR code enable/disable
- Background, logo, and signature image uploads

## Author

**Abdul Hadi Mohammed Alaidi**
Created: 2026-03-27

## License

This plugin is released under the GNU General Public License v3.0, consistent with the OJS platform license.
