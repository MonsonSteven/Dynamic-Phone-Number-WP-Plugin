# HTC Dynamic Number Changelog

## 1.4.5 - 2026-05-20

### Changed
- Improved compatibility with Google Tag Manager and Google Ads website call conversion tracking.
- DNI now marks phone elements it manages and avoids repeatedly rewriting elements that already contain the selected DNI number.
- Reduced delayed frontend rechecks from three passes to two lighter passes at 250ms and 1000ms.

### Added
- Added a cooperative yield behavior for phone links changed by Google forwarding numbers or another call-tracking layer.
- Added browser events for integration/debugging:
  - `htc-dn-applied`
  - `htc-dn-yielded`
- Added GTM-friendly `dataLayer` events when `window.dataLayer` already exists:
  - `htc_dn_applied`
  - `htc_dn_yielded`

### Notes
- This release does not change rule matching, cookie names, tracking-field capture, shortcode output, or Fluent Forms submission handling.
- The intended behavior is: DNI selects and applies the correct base number first; if Google forwarding replaces that number, DNI detects the external change and leaves it in place.
