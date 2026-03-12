# Changelog

All notable changes to `HTC Dynamic Number (Lite)` will be documented in this file.

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
