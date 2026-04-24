# Changelog

All notable changes to `HTC Dynamic Number (Lite)` will be documented in this file.

## [1.4.4] - 2026-04-23

Added Fluent Forms attribution persistence support so hidden form fields can retain first-touch campaign data even when a visitor submits the form on a later page.

New
-Added first-touch persistence for utm_promo, utm_region, gclid, fbclid, msclkid, ttclid, gbraid, and wbraid
-Added automatic population of matching Fluent Forms hidden fields on the frontend
-Added a server-side Fluent Forms fallback so persisted values are still inserted during submission when matching fields exist

Result
Visitors can land on a tagged URL, navigate deeper into the site, and still submit a Fluent Form with the original attribution data preserved
This aligns Fluent Forms submission tracking with the plugin’s existing DNI-style cookie persistence behavior

Notes
Hidden field names should match the supported parameter names exactly, such as utm_promo, utm_region, gclid, fbclid, msclkid, and ttclid
Attribution values are stored as first-touch values for the cookie duration already configured in the plugin

## [1.4.3] - 2026-03-12

### Fixed
- Applied matched campaign numbers immediately from the live URL instead of depending on an immediate cookie reread.
- Restored stable frontend performance by removing the broad DOM observer that could repeatedly retrigger swaps.
- Improved support for late-rendered header content by rechecking the swap a few times after page load.

### Notes
- `1.4.3` is the stable release validated in live testing.

## [1.4.2] - 2026-03-12

### Changed
- Added immediate URL-match swapping and attempted support for late-rendered header markup.

### Known Issue
- This release introduced a broad DOM observer that could severely degrade frontend performance on live pages.
- Do not reuse this version in production.

## [1.4.1] - 2026-03-12

### Changed
- Bumped plugin and frontend asset versions for release packaging and cache busting.
