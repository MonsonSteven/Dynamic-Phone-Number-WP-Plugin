# Dynamic Number Swap

Version: 1.4.5  (the rad edition)
Author: Steven Monson - Hometown Contractors

HTC Dynamic Number is a WordPress plugin for dynamic phone number insertion (DNI). It swaps the visible phone number and `tel:` link on marked phone elements based on URL parameters, click IDs, referrer matching, saved visitor cookies, and configured rule priority.

The plugin is intended for internal use on Hometown Contractors sites, especially pages using Divi headers and campaign traffic from Google Ads, Microsoft Ads, Meta, TikTok, and related paid media sources.

## What It Does

- Provides the `[htc_phone]` shortcode for rendering a managed phone link.
- Evaluates enabled rules by ascending `priority`; the lowest priority number wins first.
- Supports wildcard pattern matching with `*`.
- Persists the selected rule in a visitor cookie so the phone number remains consistent during the session window.
- Captures attribution values such as `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `ttclid`, `utm_promo`, and `utm_region`.
- Populates matching hidden form fields when those fields exist on the page.
- Adds Fluent Forms submission support for stored attribution values.
- Includes admin tools for rule backup, bundled rule import, visual rule management, and admin cookie clearing.

## Files

- `htc-dynamic-number.php` - Main WordPress plugin file.
- `assets/js/htc-dn-frontend.js` - Frontend DNI, attribution persistence, form-field population, and GTM coordination logic.
- `generated-rules-from-csv.json` - Bundled rule set generated from the campaign spreadsheet.
- `CHANGELOG.md` - Release notes.

## Installation

1. Package the plugin folder as a ZIP.
2. In WordPress, go to Plugins > Add New > Upload Plugin.
3. Upload and activate the ZIP.
4. Go to Dynamic Numbers in the WordPress admin menu.
5. Confirm the default display number, default `tel:` number, cookie duration, and rule count.

Important: keep the plugin folder name stable between releases. If WordPress sees two different plugin folders containing the same plugin functions, activation can fail with a PHP redeclare error. Deactivate or replace the old copy before activating a separately named upload.

## Shortcode Usage

Use this shortcode where the managed phone number should appear:

```text
[htc_phone]
```

The shortcode outputs an anchor similar to:

```html
<a href="tel:+18502035529" class="htc-phone-link" aria-label="Call Hometown Contractors" data-htc-dn-phone>
  (850) 203-5529
</a>
```

The frontend script only swaps elements marked with:

```html
data-htc-dn-phone
```

## Admin Settings

The Dynamic Numbers admin page includes:

- Default display number
- Default `tel:` number in E.164 format
- Cookie duration, from 1 to 365 days
- Live Rules JSON editor
- Current rules backup preview
- Download Current Rules JSON
- Import Bundled Rules
- Clear Admin Cookie
- Visual Rule Builder
- Visual rules table with edit, duplicate, enable/disable, reorder, and delete actions

## Rule Format

Rules are stored as JSON. A typical rule looks like this:

```json
{
  "id": "google_baths",
  "enabled": true,
  "priority": 50,
  "type": "PROMO",
  "pattern": "googlebaths",
  "display": "(850) 555-5555",
  "tel": "+18505555555",
  "label": "[Google] Baths",
  "location": "Main Office"
}
```

Required fields:

- `id` - Unique rule identifier.
- `enabled` - `true` or `false`.
- `priority` - Lower numbers evaluate first.
- `type` - Matching type.
- `pattern` - Matching pattern; supports `*`.
- `display` - Human-readable phone number.
- `tel` - Click-to-call number, usually E.164 format.

Optional fields:

- `param` - Override URL parameter for `PARAM`, `CLICKID`, or `REFERRER` style rules.
- `label` - Admin-facing label.
- `location` - Admin-facing location or call source note.

## Supported Rule Types

Shortcut rule types map to known URL parameters:

- `PROMO` -> `promo`
- `REGION` -> `region`
- `CAMPAIGNID` -> `campaignid`
- `KEYWORD` -> `keyword`
- `ADPOSITION` / `ADPOS` -> `adpos`
- `UTM_SOURCE` -> `utm_source`
- `UTM_MEDIUM` -> `utm_medium`
- `UTM_CAMPAIGN` -> `utm_campaign`
- `UTM_TERM` -> `utm_term`
- `UTM_CONTENT` -> `utm_content`
- `GCLID` -> `gclid`
- `GBRAID` -> `gbraid`
- `WBRAID` -> `wbraid`
- `FBCLID` -> `fbclid`
- `MSCLKID` -> `msclkid`
- `TTCLID` -> `ttclid`

General rule types:

- `PARAM` - Match a named URL parameter using `param` and `pattern`.
- `CLICKID` - Match when a click ID parameter is present.
- `REFERRER` - Match against the referring host.

## Attribution Cookies

The plugin stores its own cookies and does not overwrite Google, Microsoft, Meta, or TikTok platform cookies.

Primary DNI cookie:

```text
htc_dn_rule_id
```

Attribution cookies use this prefix:

```text
htc_dn_attr_
```

Examples:

- `htc_dn_attr_gclid`
- `htc_dn_attr_gbraid`
- `htc_dn_attr_wbraid`
- `htc_dn_attr_fbclid`
- `htc_dn_attr_msclkid`

## Form Field Capture

When matching fields are present on the page, the frontend script populates them from URL parameters or stored attribution cookies.

Supported field names include:

- `utm_promo`
- `promo`
- `utm_region`
- `region`
- `gclid`
- `gbraid`
- `wbraid`
- `fbclid`
- `msclkid`
- `ttclid`

For Fluent Forms, the plugin also attempts to fill empty submission values server-side when matching fields exist in the form configuration.

## GTM And Google Ads Call Tracking

Version 1.4.5 adds cooperative behavior for Google Tag Manager and Google Ads website call conversion tracking.

The intended order is:

1. DNI selects the correct base number from the active rules.
2. DNI applies that number to `[data-htc-dn-phone]`.
3. GTM/Google Ads may then replace that number with a Google forwarding number.
4. If a later DNI recheck detects that another system changed the phone text or `tel:` value, DNI yields and does not overwrite the forwarded number.

Frontend markers added by DNI:

- `data-htc-dn-applied="1"`
- `data-htc-dn-display`
- `data-htc-dn-tel`
- `data-htc-dn-yielded="1"` when another phone writer has taken over

Browser events:

- `htc-dn-applied`
- `htc-dn-yielded`

GTM `dataLayer` events, only when `window.dataLayer` already exists:

- `htc_dn_applied`
- `htc_dn_yielded`

Example `dataLayer` payload:

```js
{
  event: 'htc_dn_applied',
  display: '(850) 555-5555',
  tel: '+18505555555',
  ruleId: 'google_baths',
  yielded: 0,
  applied: 1
}
```

This release does not create `window.dataLayer`, load GTM, alter consent settings, or modify Google Ads scripts.

## Testing Checklist

After upload and activation:

1. Visit a page with `[htc_phone]` and confirm the default number appears.
2. Add a campaign URL parameter, such as `?promo=googlebaths`, and confirm the expected rule number appears.
3. Refresh the page without the parameter and confirm the selected number persists from the cookie.
4. Use the Clear Admin Cookie tool and confirm the number returns to default.
5. Submit a test form and confirm expected attribution fields are captured.
6. With GTM preview enabled, confirm `htc_dn_applied` appears after DNI writes a number.
7. If Google forwarding numbers are active, confirm a forwarded number is not overwritten by later DNI rechecks.

## Updating Rules

Recommended workflow:

1. Download Current Rules JSON before making larger edits.
2. Use the Rule Builder or visual rules table for routine changes.
3. Use Import Bundled Rules only when intentionally replacing the live rules with the bundled spreadsheet-generated rules.
4. Save settings after rule edits.
5. Test with a clean cookie state.

## Version 1.4.5 Notes

- Improved compatibility with GTM and Google Ads website call conversion tracking.
- Added cooperative yield behavior when Google forwarding or another call-tracking layer changes a phone link.
- Reduced delayed frontend rechecks to two lighter passes at 250ms and 1000ms.
- Added browser and GTM-friendly events for debugging/integration.
- No changes were made to rule matching, cookie names, shortcode output, tracking-field capture, or Fluent Forms handling.
