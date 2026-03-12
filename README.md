# HTC Dynamic Number (Lite)

WordPress plugin for dynamic phone number swapping based on URL parameters, click IDs, and referrer rules.

Current stable version: `1.4.3`

## What It Does

- Displays a default phone number through the `[htc_phone]` shortcode.
- Swaps that number on the frontend when a matching rule is found.
- Stores the matched rule in a cookie so the chosen number can persist across page views.
- Supports spreadsheet-generated promo rules through the bundled `generated-rules-from-csv.json` file.

## Installation

1. Upload the plugin folder to `wp-content/plugins/` or install it as a standard WordPress plugin zip.
2. Activate `HTC Dynamic Number (Lite)` in WordPress.
3. Open the plugin settings page in WordPress admin.
4. Confirm the default display number, default tel number, and rules JSON are correct.
5. If needed, use the bundled rules import action to reload the spreadsheet-based rules.

## Shortcode Usage

Use the shortcode anywhere the dynamic phone link should appear:

```text
[htc_phone]
```

Optional shortcode attributes:

```text
[htc_phone label="Call Hometown Contractors" class="htc-phone-link"]
```

The shortcode renders a clickable `tel:` link and the frontend script updates the displayed number when a rule matches.

## Rule Matching

Rules are evaluated by ascending `priority` value. Lower numbers win first.

Supported matching types include:

- `PROMO`
- `REGION`
- `CAMPAIGNID`
- `KEYWORD`
- `ADPOSITION`
- `UTM_SOURCE`
- `UTM_MEDIUM`
- `UTM_CAMPAIGN`
- `UTM_TERM`
- `UTM_CONTENT`
- `GCLID`
- `FBCLID`
- `MSCLKID`
- `TTCLID`
- `GBRAID`
- `WBRAID`
- `REFERRER`
- `PARAM`
- `CLICKID`

Example campaign-style URL:

```text
https://example.com/?promo=bingbathspcb
```

If the matching rule exists and is enabled, the shortcode output swaps to that rule's phone number.

## Testing Checklist

- Confirm the plugin is active and shows the expected version in WordPress admin.
- Visit a page containing `[htc_phone]`.
- Test a known matching URL parameter such as `?promo=...`.
- Confirm the campaign number appears.
- Test a clean direct visit with no matching parameter.
- Confirm the default number appears.
- Clear cache or CDN layers after plugin updates if an old script appears to persist.

## Included Files

- `htc-dynamic-number.php`: main plugin file
- `assets/js/htc-dn-frontend.js`: frontend number swap logic
- `generated-rules-from-csv.json`: bundled spreadsheet-generated rules
- `CHANGELOG.md`: release history

## Release Notes

- `1.4.3` is the current stable release validated in live testing.
- `1.4.2` should not be reused because it introduced a frontend performance issue.

See [CHANGELOG.md](./CHANGELOG.md) for details.
