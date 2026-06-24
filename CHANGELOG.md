# Changelog

All notable changes to `reviewerCertificate` are documented in this file.

## 1.5.0.0 - 2026-06-24

- New: reviewer affiliation can now be dragged independently in the certificate preview, so its final placement is no longer locked to the reviewer-name position.
- Tests: added a focused regression test that keeps the element-offset key list stable, including the new `reviewerAffiliation` entry.

## 1.4.0.3 - 2026-05-22

- Docs: added OJS setup screenshots to the English and Arabic READMEs.
- Docs: synced release examples to the current release tag/archive version.
- License: added the root GPLv3 `LICENSE` file and documented third-party component licenses.
- Tests: added a standalone PHPUnit harness plus focused plugin unit tests.
- CI: added a GitHub Actions workflow that runs PHPUnit on pushes and pull requests.

## 1.4.0.2 - 2026-05-19

- Fixed: mobile rendering no longer reflowed the certificate to a different layout than desktop. The certificate now keeps its native `960x678` canvas and scales down as a whole on narrow screens, so phones match desktop exactly. Print/PDF output neutralises the on-screen scale and renders at full page size.

## 1.4.0.1 - 2026-05-18

- Fixed: re-uploaded signature, logo, and background images were cached by the browser and `wkhtmltopdf` because every upload reused a fixed filename. Uploads now get a unique filename, and the previously stored managed file is removed to avoid orphans.
- Docs: expanded the README with detailed installation and upgrading instructions, a screenshot placeholder, developer contact, and a free plus sponsor support model.

## 1.4.0.0 - 2026-05-18

- New: added an Email Notification toggle so the thank-you email carrying the certificate link can be turned off while certificate generation and storage remain enabled.

## 1.3.0.1 - 2026-05-18

- Docs: corrected the certificate-body placeholder tokens in the README to `{journalName}` and `{submissionTitle}`.

## 1.3.0.0 - 2026-05-18

- New: reviewer affiliation is displayed below the reviewer name on the certificate.
- New: added Show / Hide toggle checkboxes for certificate elements, with related settings sections hiding automatically when unchecked.
- New: added content offset control to move certificate content up or down by pixels.
- New: added QR code size and X/Y position controls for precise placement.
- Fixed: the `textColor` setting now persists correctly instead of reverting to the default `#1a1a2e`.

## 1.2.0.0 - 2026-05-16

- New: added signature and date position controls for pixel-precise layout adjustments.
- New: live preview now reflects unsaved style and layout changes without saving.
- Fixed: the plugin settings page no longer returns HTTP 500 because of the broken locale import / lookup path.

## 1.1.0.1 - 2026-05-16

- Fixed: plugin registration no longer fails on some OJS installs with a missing `ReviewerCertificateGatewayPlugin` class error.

## 1.1.0.0 - 2026-05-16

- New: added multilingual editor name, editor title, and certificate body fields with locale-aware rendering and fallback.
- New: added configurable text color for the heading, recipient name, and body.
- New: server-side PDF always regenerates from the current template and settings.
- Fixed: PDF output now renders as a single full-bleed `297 x 210 mm` page without top whitespace or screen-responsive leakage.
- Fixed: journal and manuscript titles render in italic in the certificate body.
- Fixed: legacy single-language settings are no longer guessed onto the wrong locale on upgrade.

## 1.0.0.0 - 2026-03-22

- Initial release.
