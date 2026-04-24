<?php
/**
 * Plugin Name: HTC Dynamic Number (Lite)
 * Description: Dynamic phone number swapping with an admin settings page. Shortcode: [htc_phone]
 * Version: 1.4.4
 * Author: Steven Monson - Hometown Contractors - Internal Use Only
 */

defined('ABSPATH') || exit;

define('HTC_DN_OPT', 'htc_dn_options_lite');
define('HTC_DN_COOKIE', 'htc_dn_rule_id');
define('HTC_DN_ATTR_COOKIE_PREFIX', 'htc_dn_attr_');
define('HTC_DN_PATH', plugin_dir_path(__FILE__));
define('HTC_DN_URL', plugin_dir_url(__FILE__));
define('HTC_DN_RULES_FILE', HTC_DN_PATH . 'generated-rules-from-csv.json');

function htc_dn_default_rules_json(): string {
  if (file_exists(HTC_DN_RULES_FILE) && is_readable(HTC_DN_RULES_FILE)) {
    $json = file_get_contents(HTC_DN_RULES_FILE);
    if (is_string($json) && trim($json) !== '') {
      return $json;
    }
  }

  return '[]';
}

function htc_dn_rule_type_param_map(): array {
  return [
    'UTM_SOURCE'   => 'utm_source',
    'UTM_MEDIUM'   => 'utm_medium',
    'UTM_CAMPAIGN' => 'utm_campaign',
    'UTM_TERM'     => 'utm_term',
    'UTM_CONTENT'  => 'utm_content',
    'CAMPAIGNID'   => 'campaignid',
    'KEYWORD'      => 'keyword',
    'ADPOSITION'   => 'adpos',
    'ADPOS'        => 'adpos',
    'PROMO'        => 'promo',
    'REGION'       => 'region',
    'GCLID'        => 'gclid',
    'FBCLID'       => 'fbclid',
    'MSCLKID'      => 'msclkid',
    'TTCLID'       => 'ttclid',
    'GBRAID'       => 'gbraid',
    'WBRAID'       => 'wbraid',
  ];
}

function htc_dn_tracking_param_sources(): array {
  return [
    'utm_promo'  => ['utm_promo', 'promo'],
    'utm_region' => ['utm_region', 'region'],
    'gclid'      => ['gclid'],
    'fbclid'     => ['fbclid'],
    'msclkid'    => ['msclkid'],
    'ttclid'     => ['ttclid'],
    'gbraid'     => ['gbraid'],
    'wbraid'     => ['wbraid'],
  ];
}

function htc_dn_tracking_field_targets(): array {
  return [
    'utm_promo'  => ['utm_promo', 'promo'],
    'utm_region' => ['utm_region', 'region'],
    'gclid'      => ['gclid'],
    'fbclid'     => ['fbclid'],
    'msclkid'    => ['msclkid'],
    'ttclid'     => ['ttclid'],
    'gbraid'     => ['gbraid'],
    'wbraid'     => ['wbraid'],
  ];
}

function htc_dn_tracking_cookie_name(string $key): string {
  return HTC_DN_ATTR_COOKIE_PREFIX . sanitize_key($key);
}

function htc_dn_tracking_cookie_map(): array {
  $cookies = [];

  foreach (array_keys(htc_dn_tracking_param_sources()) as $key) {
    $cookies[$key] = htc_dn_tracking_cookie_name($key);
  }

  return $cookies;
}

function htc_dn_read_request_value(array $source_names): ?string {
  foreach ($source_names as $name) {
    $key = (string) $name;

    if (isset($_COOKIE[$key])) {
      $value = sanitize_text_field(wp_unslash($_COOKIE[$key]));
      if ($value !== '') {
        return $value;
      }
    }

    if (isset($_GET[$key])) {
      $value = sanitize_text_field(wp_unslash($_GET[$key]));
      if ($value !== '') {
        return $value;
      }
    }
  }

  return null;
}

function htc_dn_get_tracking_request_values(): array {
  $values = [];

  foreach (htc_dn_tracking_param_sources() as $key => $sources) {
    $cookie_name = htc_dn_tracking_cookie_name($key);
    $cookie_value = isset($_COOKIE[$cookie_name]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) : '';

    if ($cookie_value !== '') {
      $values[$key] = $cookie_value;
      continue;
    }

    $request_value = htc_dn_read_request_value($sources);
    if ($request_value !== null) {
      $values[$key] = $request_value;
    }
  }

  return $values;
}

function htc_dn_collect_ff_input_names($node, array &$names): void {
  if (is_array($node)) {
    if (isset($node['attributes']['name']) && is_string($node['attributes']['name'])) {
      $name = sanitize_key($node['attributes']['name']);
      if ($name !== '') {
        $names[$name] = true;
      }
    }

    if (isset($node['name']) && is_string($node['name'])) {
      $name = sanitize_key($node['name']);
      if ($name !== '') {
        $names[$name] = true;
      }
    }

    foreach ($node as $value) {
      htc_dn_collect_ff_input_names($value, $names);
    }

    return;
  }

  if (is_object($node)) {
    htc_dn_collect_ff_input_names(get_object_vars($node), $names);
  }
}

function htc_dn_extract_ff_input_names($input_configs): array {
  $names = [];
  htc_dn_collect_ff_input_names($input_configs, $names);

  return array_keys($names);
}

function htc_dn_normalize_rule(array $rule): array {
  $type = strtoupper(trim((string) ($rule['type'] ?? 'PARAM')));
  $map = htc_dn_rule_type_param_map();

  if (isset($map[$type]) && empty($rule['param'])) {
    $rule['param'] = $map[$type];
  }

  if (in_array($type, ['GCLID', 'FBCLID', 'MSCLKID', 'TTCLID', 'GBRAID', 'WBRAID'], true)) {
    $rule['type'] = 'CLICKID';
  } else {
    $rule['type'] = $type;
  }

  if (!isset($rule['pattern']) || $rule['pattern'] === '') {
    $rule['pattern'] = '*';
  }

  return $rule;
}

function htc_dn_defaults(): array {
  return [
    'default_display' => '(850) 203-5529',
    'default_tel'     => '+18502035529',
    'cookie_days'     => 30,
    'rules_json'      => htc_dn_default_rules_json(),
  ];
}

function htc_dn_get_options(): array {
  $opt = get_option(HTC_DN_OPT);
  if (!is_array($opt)) {
    $opt = [];
  }
  return wp_parse_args($opt, htc_dn_defaults());
}

/** ---------------------------
 * Admin: Menu + Settings
 * --------------------------- */
add_action('admin_menu', 'htc_dn_admin_menu');
add_action('network_admin_menu', 'htc_dn_admin_menu');
add_action('admin_init', 'htc_dn_admin_init');
add_action('admin_bar_menu', 'htc_dn_admin_bar_menu', 999);
add_action('init', 'htc_dn_maybe_seed_rules');
add_action('admin_init', 'htc_dn_download_current_rules');

function htc_dn_maybe_seed_rules(): void {
  $opt = get_option(HTC_DN_OPT);

  if (!is_array($opt)) {
    return;
  }

  $rules_json = $opt['rules_json'] ?? '';
  $decoded = is_string($rules_json) ? json_decode($rules_json, true) : null;
  $is_empty_ruleset = !is_array($decoded) || count($decoded) === 0;

  if (!$is_empty_ruleset) {
    return;
  }

  $default_rules = htc_dn_default_rules_json();
  if ($default_rules === '[]') {
    return;
  }

  $opt['rules_json'] = $default_rules;
  update_option(HTC_DN_OPT, $opt);
}

function htc_dn_replace_rules_with_bundled_rules(): bool {
  $default_rules = htc_dn_default_rules_json();
  if ($default_rules === '[]') {
    return false;
  }

  $opt = htc_dn_get_options();
  $opt['rules_json'] = $default_rules;

  return update_option(HTC_DN_OPT, $opt);
}

function htc_dn_download_current_rules(): void {
  if (!current_user_can('manage_options')) {
    return;
  }

  if (!isset($_GET['htc_dn_download_rules'])) {
    return;
  }

  if (!wp_verify_nonce($_GET['htc_dn_download_rules'], 'htc_dn_download_rules')) {
    return;
  }

  $opt = htc_dn_get_options();
  $rules_json = $opt['rules_json'] ?? '[]';
  $filename = 'htc-dynamic-rules-' . gmdate('Y-m-d-His') . '.json';

  nocache_headers();
  header('Content-Type: application/json; charset=' . get_option('blog_charset'));
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo is_string($rules_json) ? $rules_json : '[]';
  exit;
}

function htc_dn_admin_menu(): void {
  $cap = 'manage_options';

  add_menu_page(
    'Dynamic Numbers',
    'Dynamic Numbers',
    $cap,
    'htc-dynamic-number',
    'htc_dn_render_settings_page',
    'dashicons-phone',
    58
  );

  add_options_page(
    'Dynamic Numbers',
    'Dynamic Numbers',
    $cap,
    'htc-dynamic-number',
    'htc_dn_render_settings_page'
  );
}

function htc_dn_admin_init(): void {
  register_setting('htc_dn_group', HTC_DN_OPT, [
    'type'              => 'array',
    'sanitize_callback' => 'htc_dn_sanitize_options',
    'default'           => htc_dn_defaults(),
  ]);

  add_settings_section(
    'htc_dn_section_main',
    'Dynamic Number Settings',
    '__return_false',
    'htc-dynamic-number'
  );

  add_settings_field('default_display', 'Default display', 'htc_dn_field_default_display', 'htc-dynamic-number', 'htc_dn_section_main');
  add_settings_field('default_tel', 'Default tel (E.164)', 'htc_dn_field_default_tel', 'htc-dynamic-number', 'htc_dn_section_main');
  add_settings_field('cookie_days', 'Cookie duration (days)', 'htc_dn_field_cookie_days', 'htc-dynamic-number', 'htc_dn_section_main');
  add_settings_field('rules_json', 'Rules (JSON)', 'htc_dn_field_rules_json', 'htc-dynamic-number', 'htc_dn_section_main');
}

function htc_dn_sanitize_options($raw): array {
  $out = htc_dn_get_options();

  $out['default_display'] = sanitize_text_field($raw['default_display'] ?? $out['default_display']);
  $out['default_tel']     = sanitize_text_field($raw['default_tel'] ?? $out['default_tel']);

  $days = intval($raw['cookie_days'] ?? $out['cookie_days']);
  $out['cookie_days'] = max(1, min(365, $days));

  $rules_json = $raw['rules_json'] ?? $out['rules_json'];
  $rules_json = is_string($rules_json) ? $rules_json : '[]';

  $decoded = json_decode($rules_json, true);
  if (!is_array($decoded)) {
    add_settings_error(HTC_DN_OPT, 'htc_dn_rules_json', 'Rules JSON is invalid. Please fix the JSON and save again.', 'error');
    $rules_json = '[]';
  }

  $out['rules_json'] = $rules_json;

  return $out;
}

/**
 * Get debug info visible in wp-admin only
 */
function htc_dn_get_debug_info(): array {
  $opt = htc_dn_get_options();
  $rules = json_decode($opt['rules_json'], true);
  $rules = is_array($rules) ? $rules : [];
  $rules = array_values(array_map('htc_dn_normalize_rule', array_filter($rules, 'is_array')));

  $cookie_value = isset($_COOKIE[HTC_DN_COOKIE]) ? sanitize_text_field($_COOKIE[HTC_DN_COOKIE]) : null;
  $matched_rule = null;

  if ($cookie_value) {
    foreach ($rules as $rule) {
      if (is_array($rule) && ($rule['id'] ?? null) === $cookie_value) {
        $matched_rule = $rule;
        break;
      }
    }
  }

  return [
    'cookie_name'     => HTC_DN_COOKIE,
    'cookie_value'    => $cookie_value,
    'matched_rule_id' => $matched_rule['id'] ?? null,
    'display_number'  => $matched_rule['display'] ?? $opt['default_display'],
    'tel_number'      => $matched_rule['tel'] ?? $opt['default_tel'],
    'is_default'      => $matched_rule === null,
  ];
}

function htc_dn_get_rule_type_options(): array {
  return [
    'PROMO',
    'REGION',
    'CAMPAIGNID',
    'KEYWORD',
    'ADPOSITION',
    'UTM_CAMPAIGN',
    'UTM_SOURCE',
    'UTM_MEDIUM',
    'UTM_TERM',
    'UTM_CONTENT',
    'GCLID',
    'FBCLID',
    'MSCLKID',
    'TTCLID',
    'GBRAID',
    'WBRAID',
    'PARAM',
    'CLICKID',
    'REFERRER',
  ];
}

function htc_dn_render_settings_page(): void {
  if (!current_user_can('manage_options')) {
    return;
  }

  if (isset($_POST['htc_dn_purge_cookies']) && check_admin_referer('htc_dn_purge_nonce')) {
    setcookie(HTC_DN_COOKIE, '', time() - 3600, '/', '', false, true);
    echo '<div class="notice notice-success is-dismissible"><p>Cookie purged successfully.</p></div>';
  }

  if (isset($_POST['htc_dn_import_bundled_rules']) && check_admin_referer('htc_dn_import_bundled_rules_nonce')) {
    $imported = htc_dn_replace_rules_with_bundled_rules();
    $message = $imported
      ? 'Bundled rules imported successfully.'
      : 'Bundled rules could not be imported. Check that the JSON file exists and is readable.';
    $class = $imported ? 'notice-success' : 'notice-error';

    echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
  }

  $debug = htc_dn_get_debug_info();
  $opt = htc_dn_get_options();
  $rules_json = is_string($opt['rules_json'] ?? null) ? $opt['rules_json'] : '[]';
  $rules = json_decode($opt['rules_json'], true);
  $rules = is_array($rules) ? array_values(array_filter($rules, 'is_array')) : [];
  $enabled_rules = count(array_filter($rules, function ($rule) {
    return !empty($rule['enabled']);
  }));
  $download_url = wp_nonce_url(
    add_query_arg(['htc_dn_download_rules' => '1'], admin_url('admin.php?page=htc-dynamic-number')),
    'htc_dn_download_rules',
    'htc_dn_download_rules'
  );
  ?>
  <div class="wrap">
    <h1>Dynamic Numbers</h1>
    <p>
      Use <code>[htc_phone]</code> in your Divi header. Rules are evaluated in ascending <code>priority</code> (lowest wins).
      Patterns support <code>*</code> wildcards.
    </p>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin:18px 0;">
      <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:14px;">
        <div style="font-size:12px; text-transform:uppercase; color:#646970; letter-spacing:0.04em;">Total Rules</div>
        <div style="font-size:28px; font-weight:700; line-height:1.2;"><?php echo esc_html((string) count($rules)); ?></div>
      </div>
      <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:14px;">
        <div style="font-size:12px; text-transform:uppercase; color:#646970; letter-spacing:0.04em;">Enabled Rules</div>
        <div style="font-size:28px; font-weight:700; line-height:1.2;"><?php echo esc_html((string) $enabled_rules); ?></div>
      </div>
      <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:14px;">
        <div style="font-size:12px; text-transform:uppercase; color:#646970; letter-spacing:0.04em;">Cookie Duration</div>
        <div style="font-size:28px; font-weight:700; line-height:1.2;"><?php echo esc_html((string) intval($opt['cookie_days'])); ?> <span style="font-size:16px; font-weight:400;">days</span></div>
      </div>
    </div>

    <div style="background:#f5f5f5; border-left:4px solid #0073aa; padding:15px; margin:15px 0; border-radius:4px;">
      <h3 style="margin-top:0; color:#0073aa;">Debug Panel</h3>
      <table style="width:100%; border-collapse:collapse;">
        <tr style="border-bottom:1px solid #ddd;">
          <td style="padding:8px; font-weight:bold; width:200px;">Cookie Name:</td>
          <td style="padding:8px;"><code><?php echo esc_html($debug['cookie_name']); ?></code></td>
        </tr>
        <tr style="border-bottom:1px solid #ddd; background:#fff;">
          <td style="padding:8px; font-weight:bold;">Cookie Value:</td>
          <td style="padding:8px;">
            <?php if ($debug['cookie_value']): ?>
              <code style="background:#fffbdd; padding:4px 8px; border-radius:3px;">
                <?php echo esc_html($debug['cookie_value']); ?>
              </code>
            <?php else: ?>
              <em style="color:#999;">No cookie set</em>
            <?php endif; ?>
          </td>
        </tr>
        <tr style="border-bottom:1px solid #ddd;">
          <td style="padding:8px; font-weight:bold;">Matched Rule ID:</td>
          <td style="padding:8px;">
            <?php if ($debug['matched_rule_id']): ?>
              <code style="background:#d4edda; padding:4px 8px; border-radius:3px; color:#155724;">
                <?php echo esc_html($debug['matched_rule_id']); ?>
              </code>
            <?php else: ?>
              <em style="color:#999;">None (using default)</em>
            <?php endif; ?>
          </td>
        </tr>
        <tr style="border-bottom:1px solid #ddd; background:#fff;">
          <td style="padding:8px; font-weight:bold;">Current Display:</td>
          <td style="padding:8px;">
            <code style="background:#e3f2fd; padding:4px 8px; border-radius:3px;">
              <?php echo esc_html($debug['display_number']); ?>
            </code>
            <?php if ($debug['is_default']): ?>
              <span style="color:#999; font-size:0.9em; margin-left:8px;">(default)</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr style="background:#fff;">
          <td style="padding:8px; font-weight:bold;">Current Tel:</td>
          <td style="padding:8px;">
            <code style="background:#e3f2fd; padding:4px 8px; border-radius:3px;">
              <?php echo esc_html($debug['tel_number']); ?>
            </code>
            <?php if ($debug['is_default']): ?>
              <span style="color:#999; font-size:0.9em; margin-left:8px;">(default)</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
      <p style="margin:12px 0 0 0; font-size:0.9em; color:#666;">
        This panel shows what your current admin session sees. Visitor sessions may differ based on their traffic source.
      </p>
    </div>

    <form method="post" action="options.php">
      <?php
        settings_fields('htc_dn_group');
        do_settings_sections('htc-dynamic-number');
        submit_button('Save Settings');
      ?>
    </form>

    <hr />

    <h2>Admin Tools</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-bottom:20px;">
      <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px;">
        <h3 style="margin-top:0;">Backup Current Rules</h3>
        <p style="margin-top:0; color:#50575e;">Download the exact live rules JSON before importing bundled rules or making larger edits.</p>
        <a href="<?php echo esc_url($download_url); ?>" class="button button-secondary">Download Current Rules JSON</a>
      </div>

      <form method="post" style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px;">
        <h3 style="margin-top:0;">Re-import Bundled Rules</h3>
        <p style="margin-top:0; color:#50575e;">Replace the current rules JSON with the bundled promo rules generated from your spreadsheet.</p>
        <?php wp_nonce_field('htc_dn_import_bundled_rules_nonce'); ?>
        <input type="hidden" name="htc_dn_import_bundled_rules" value="1" />
        <button type="submit" class="button button-primary" onclick="return confirm('This will replace the current Rules JSON with the bundled rules. Continue?');">
          Import Bundled Rules
        </button>
      </form>

      <form method="post" style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px;">
        <h3 style="margin-top:0;">Clear Admin Session Cookie</h3>
        <p style="margin-top:0; color:#50575e;">Clear your own browser cookie so you can test rule matching from a clean state. Visitor cookies are unaffected.</p>
        <?php wp_nonce_field('htc_dn_purge_nonce'); ?>
        <input type="hidden" name="htc_dn_purge_cookies" value="1" />
        <button type="submit" class="button button-secondary" onclick="return confirm('This will clear the tracking cookie. Continue?');">
          Clear Admin Cookie
        </button>
      </form>
    </div>

    <hr />
    <h2>Rule Setup Tips</h2>
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px; margin-bottom:20px;">
      <p style="margin-top:0;">The bundled spreadsheet rules use <code>PROMO</code> matching against the <code>promo</code> URL parameter. You can also create manual rules using types like <code>REGION</code>, <code>CAMPAIGNID</code>, <code>KEYWORD</code>, <code>UTM_CAMPAIGN</code>, <code>GCLID</code>, and <code>REFERRER</code>.</p>
      <p style="margin-bottom:0;">When multiple rules could match, the lowest <code>priority</code> number wins first.</p>
    </div>

    <hr />
    <h2>Current Rules Backup Preview</h2>
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px; margin-bottom:20px;">
      <p style="margin-top:0; color:#50575e;">This is a read-only snapshot of the currently saved rules. Use it for quick copy/paste backups.</p>
      <textarea readonly rows="10" class="large-text code"><?php echo esc_textarea($rules_json); ?></textarea>
    </div>

    <hr />
    <h2>Rule Builder</h2>
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px; margin-bottom:20px;">
      <p style="margin-top:0; color:#50575e;">Build one rule at a time, preview the JSON, then append it directly into the live Rules JSON editor below.</p>
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:12px;">
        <label>
          <strong>Rule ID</strong><br />
          <input type="text" id="htc-dn-builder-id" class="regular-text" placeholder="pace_promo_baths" style="width:100%;" />
        </label>
        <label>
          <strong>Priority</strong><br />
          <input type="number" id="htc-dn-builder-priority" value="100" min="0" step="1" style="width:100%;" />
        </label>
        <label>
          <strong>Type</strong><br />
          <select id="htc-dn-builder-type" style="width:100%;">
            <?php foreach (htc_dn_get_rule_type_options() as $type_option): ?>
              <option value="<?php echo esc_attr($type_option); ?>" <?php selected($type_option, 'PROMO'); ?>><?php echo esc_html($type_option); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <strong>Param Override</strong><br />
          <input type="text" id="htc-dn-builder-param" class="regular-text" placeholder="Only for PARAM / CLICKID / REFERRER" style="width:100%;" />
        </label>
        <label>
          <strong>Pattern</strong><br />
          <input type="text" id="htc-dn-builder-pattern" class="regular-text" placeholder="bingbaths or *" style="width:100%;" />
        </label>
        <label>
          <strong>Display Number</strong><br />
          <input type="text" id="htc-dn-builder-display" class="regular-text" placeholder="(850) 555-1234" style="width:100%;" />
        </label>
        <label>
          <strong>Tel Number</strong><br />
          <input type="text" id="htc-dn-builder-tel" class="regular-text" placeholder="+18505551234" style="width:100%;" />
        </label>
        <label>
          <strong>Label</strong><br />
          <input type="text" id="htc-dn-builder-label" class="regular-text" placeholder="[Pace] Google Baths" style="width:100%;" />
        </label>
        <label>
          <strong>Location</strong><br />
          <input type="text" id="htc-dn-builder-location" class="regular-text" placeholder="Pace Office - 0001" style="width:100%;" />
        </label>
      </div>
      <p style="margin:0 0 12px 0;">
        <label><input type="checkbox" id="htc-dn-builder-enabled" checked="checked" /> Enabled</label>
      </p>
      <input type="hidden" id="htc-dn-builder-edit-index" value="" />
      <p id="htc-dn-builder-mode" style="margin:0 0 12px 0; color:#50575e;">Builder mode: creating a new rule.</p>
      <p style="margin:0 0 12px 0; display:flex; flex-wrap:wrap; gap:8px;">
        <button type="button" class="button button-secondary" id="htc-dn-builder-fill-tel">Format Tel From Display</button>
        <button type="button" class="button button-secondary" id="htc-dn-builder-generate">Generate Preview</button>
        <button type="button" class="button button-primary" id="htc-dn-builder-append">Append New Rule</button>
        <button type="button" class="button button-secondary" id="htc-dn-builder-update">Update Selected Rule</button>
        <button type="button" class="button" id="htc-dn-builder-clear">Clear Builder</button>
      </p>
      <label for="htc-dn-builder-preview"><strong>Generated Rule Preview</strong></label>
      <textarea id="htc-dn-builder-preview" readonly rows="10" class="large-text code" style="margin-top:8px;"></textarea>
    </div>

    <hr />
    <h2>Rules Manager</h2>
    <div style="background:#fff; border:1px solid #ccd0d4; border-radius:8px; padding:16px; margin-bottom:20px;">
      <p style="margin-top:0; color:#50575e;">Review and manage the current rules visually. Edit loads a rule into the builder above; all actions stay synced with the live JSON field.</p>
      <p style="margin:0 0 12px 0; display:flex; flex-wrap:wrap; gap:8px;">
        <button type="button" class="button button-secondary" id="htc-dn-rules-sync">Refresh Table From JSON</button>
      </p>
      <div style="overflow:auto;">
        <table class="widefat striped" id="htc-dn-rules-table">
          <thead>
            <tr>
              <th style="width:60px;">#</th>
              <th>ID</th>
              <th>Type</th>
              <th>Pattern</th>
              <th>Display</th>
              <th>Priority</th>
              <th>Enabled</th>
              <th style="min-width:260px;">Actions</th>
            </tr>
          </thead>
          <tbody id="htc-dn-rules-table-body">
            <tr>
              <td colspan="8">Loading rules...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <hr />
    <h2>Rules JSON example</h2>
    <pre style="background:#fff; padding:12px; border:1px solid #ccd0d4; border-radius:8px; overflow:auto;">[
  {
    "id": "windows_brand",
    "enabled": true,
    "priority": 10,
    "type": "UTM_CAMPAIGN",
    "pattern": "windows_brand",
    "display": "(850) 555-1001",
    "tel": "+18505551001"
  },
  {
    "id": "google_any",
    "enabled": true,
    "priority": 50,
    "type": "GCLID",
    "pattern": "*",
    "display": "(850) 555-2001",
    "tel": "+18505552001"
  },
  {
    "id": "pace_promo",
    "enabled": true,
    "priority": 60,
    "type": "PROMO",
    "pattern": "bingbaths",
    "display": "(850) 555-3001",
    "tel": "+18505553001"
  },
  {
    "id": "robertsdale_region",
    "enabled": true,
    "priority": 70,
    "type": "REGION",
    "pattern": "0003",
    "display": "(251) 555-4001",
    "tel": "+12515554001"
  }
]</pre>
    <p><strong>Type values:</strong> <code>PARAM</code> (match any named URL parameter), <code>CLICKID</code> (param presence), <code>REFERRER</code> (match referrer host), plus shortcut types like <code>UTM_CAMPAIGN</code>, <code>CAMPAIGNID</code>, <code>KEYWORD</code>, <code>ADPOSITION</code>, <code>PROMO</code>, <code>REGION</code>, <code>GCLID</code>, <code>FBCLID</code>, and <code>MSCLKID</code>.</p>
    <p>From your spreadsheet template, links like <code>{lpurl}?campaignid={campaignid}&amp;keyword={keyword}&amp;adpos={adposition}&amp;promo={_promo}&amp;region={_region}</code> can now be matched without manually filling in <code>param</code> each time.</p>
  </div>
  <script>
    (() => {
      const byId = (id) => document.getElementById(id);
      const fields = {
        id: byId('htc-dn-builder-id'),
        priority: byId('htc-dn-builder-priority'),
        type: byId('htc-dn-builder-type'),
        param: byId('htc-dn-builder-param'),
        pattern: byId('htc-dn-builder-pattern'),
        display: byId('htc-dn-builder-display'),
        tel: byId('htc-dn-builder-tel'),
        label: byId('htc-dn-builder-label'),
        location: byId('htc-dn-builder-location'),
        enabled: byId('htc-dn-builder-enabled'),
        editIndex: byId('htc-dn-builder-edit-index'),
        mode: byId('htc-dn-builder-mode'),
        preview: byId('htc-dn-builder-preview'),
        rulesJson: byId('htc-dn-rules-json'),
        tableBody: byId('htc-dn-rules-table-body')
      };

      if (!fields.preview || !fields.rulesJson || !fields.tableBody) {
        return;
      }

      const parseRules = () => {
        const parsed = JSON.parse(fields.rulesJson.value || '[]');
        return Array.isArray(parsed) ? parsed : [];
      };

      const writeRules = (rules) => {
        fields.rulesJson.value = JSON.stringify(rules, null, 2);
      };

      const escapeHtml = (value) => (value ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const buildRule = () => {
        const rule = {
          id: (fields.id.value || '').trim(),
          enabled: !!fields.enabled.checked,
          priority: Number.parseInt(fields.priority.value || '100', 10),
          type: (fields.type.value || 'PROMO').trim().toUpperCase(),
          pattern: (fields.pattern.value || '*').trim(),
          display: (fields.display.value || '').trim(),
          tel: (fields.tel.value || '').trim()
        };

        const param = (fields.param.value || '').trim();
        const label = (fields.label.value || '').trim();
        const location = (fields.location.value || '').trim();

        if (param) rule.param = param;
        if (label) rule.label = label;
        if (location) rule.location = location;

        return rule;
      };

      const validateRule = (rule) => {
        if (!rule.id || !rule.display || !rule.tel) {
          window.alert('Rule ID, Display Number, and Tel Number are required.');
          return false;
        }
        return true;
      };

      const renderPreview = () => {
        const rule = buildRule();
        fields.preview.value = JSON.stringify(rule, null, 2);
        return rule;
      };

      const setBuilderMode = (index = '') => {
        fields.editIndex.value = index === '' ? '' : String(index);
        fields.mode.textContent = index === '' ? 'Builder mode: creating a new rule.' : `Builder mode: editing rule #${Number(index) + 1}.`;
      };

      const fillBuilderFromRule = (rule, index = '') => {
        fields.id.value = rule.id || '';
        fields.priority.value = String(rule.priority ?? 100);
        fields.type.value = (rule.type || 'PROMO').toUpperCase();
        fields.param.value = rule.param || '';
        fields.pattern.value = rule.pattern || '';
        fields.display.value = rule.display || '';
        fields.tel.value = rule.tel || '';
        fields.label.value = rule.label || '';
        fields.location.value = rule.location || '';
        fields.enabled.checked = !!rule.enabled;
        setBuilderMode(index);
        renderPreview();
        fields.id.focus();
      };

      const clearBuilder = () => {
        fields.id.value = '';
        fields.priority.value = '100';
        fields.type.value = 'PROMO';
        fields.param.value = '';
        fields.pattern.value = '';
        fields.display.value = '';
        fields.tel.value = '';
        fields.label.value = '';
        fields.location.value = '';
        fields.enabled.checked = true;
        fields.preview.value = '';
        setBuilderMode('');
      };

      const fillTelFromDisplay = () => {
        const digits = (fields.display.value || '').replace(/\D/g, '');
        if (!digits) return;
        fields.tel.value = digits.length === 10 ? `+1${digits}` : `+${digits}`;
        renderPreview();
      };

      const renderTable = () => {
        let rules;
        try {
          rules = parseRules();
        } catch (error) {
          fields.tableBody.innerHTML = '<tr><td colspan="8">Rules JSON is invalid. Fix it before using the visual manager.</td></tr>';
          return;
        }

        if (!rules.length) {
          fields.tableBody.innerHTML = '<tr><td colspan="8">No rules yet. Use the builder above to create the first one.</td></tr>';
          return;
        }

        fields.tableBody.innerHTML = rules.map((rule, index) => {
          const enabled = rule && rule.enabled ? 'Yes' : 'No';
          return `
            <tr>
              <td>${index + 1}</td>
              <td><code>${escapeHtml(rule.id || '')}</code></td>
              <td>${escapeHtml(rule.type || '')}</td>
              <td><code>${escapeHtml(rule.pattern || '')}</code></td>
              <td>${escapeHtml(rule.display || '')}</td>
              <td>${Number.parseInt(rule.priority ?? 9999, 10)}</td>
              <td>${enabled}</td>
              <td>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                  <button type="button" class="button button-small" data-action="edit" data-index="${index}">Edit</button>
                  <button type="button" class="button button-small" data-action="duplicate" data-index="${index}">Duplicate</button>
                  <button type="button" class="button button-small" data-action="toggle" data-index="${index}">${rule && rule.enabled ? 'Disable' : 'Enable'}</button>
                  <button type="button" class="button button-small" data-action="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>Up</button>
                  <button type="button" class="button button-small" data-action="down" data-index="${index}" ${index === rules.length - 1 ? 'disabled' : ''}>Down</button>
                  <button type="button" class="button button-small" data-action="delete" data-index="${index}">Delete</button>
                </div>
              </td>
            </tr>
          `;
        }).join('');
      };

      const appendRule = () => {
        const rule = buildRule();
        if (!validateRule(rule)) return;

        let rules;
        try {
          rules = parseRules();
        } catch (error) {
          window.alert('Rules JSON is not valid right now. Fix it before appending a new rule.');
          return;
        }

        rules.push(rule);
        writeRules(rules);
        fields.preview.value = JSON.stringify(rule, null, 2);
        renderTable();
        setBuilderMode(rules.length - 1);
      };

      const updateRule = () => {
        const rule = buildRule();
        if (!validateRule(rule)) return;

        const index = Number.parseInt(fields.editIndex.value || '', 10);
        if (!Number.isInteger(index)) {
          window.alert('Choose a rule from the table to update first.');
          return;
        }

        let rules;
        try {
          rules = parseRules();
        } catch (error) {
          window.alert('Rules JSON is not valid right now. Fix it before updating a rule.');
          return;
        }

        if (!rules[index]) {
          window.alert('That rule no longer exists. Refresh the table and try again.');
          return;
        }

        rules[index] = rule;
        writeRules(rules);
        renderTable();
        renderPreview();
      };

      const moveRule = (rules, fromIndex, toIndex) => {
        const [item] = rules.splice(fromIndex, 1);
        rules.splice(toIndex, 0, item);
      };

      const handleTableAction = (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        let rules;
        try {
          rules = parseRules();
        } catch (error) {
          window.alert('Rules JSON is not valid right now. Fix it before using the rules manager.');
          return;
        }

        const action = button.getAttribute('data-action');
        const index = Number.parseInt(button.getAttribute('data-index') || '', 10);
        if (!Number.isInteger(index) || !rules[index]) return;

        if (action === 'edit') {
          fillBuilderFromRule(rules[index], index);
          return;
        }

        if (action === 'duplicate') {
          const clone = { ...rules[index], id: `${rules[index].id || 'rule'}_copy` };
          rules.splice(index + 1, 0, clone);
          writeRules(rules);
          renderTable();
          fillBuilderFromRule(clone, index + 1);
          return;
        }

        if (action === 'toggle') {
          rules[index].enabled = !rules[index].enabled;
          writeRules(rules);
          renderTable();
          if (fields.editIndex.value === String(index)) fillBuilderFromRule(rules[index], index);
          return;
        }

        if (action === 'delete') {
          if (!window.confirm('Delete this rule from the current JSON?')) return;
          rules.splice(index, 1);
          writeRules(rules);
          renderTable();
          if (fields.editIndex.value === String(index)) clearBuilder();
          return;
        }

        if (action === 'up' && index > 0) {
          moveRule(rules, index, index - 1);
          writeRules(rules);
          renderTable();
          if (fields.editIndex.value === String(index)) setBuilderMode(index - 1);
          return;
        }

        if (action === 'down' && index < rules.length - 1) {
          moveRule(rules, index, index + 1);
          writeRules(rules);
          renderTable();
          if (fields.editIndex.value === String(index)) setBuilderMode(index + 1);
        }
      };

      byId('htc-dn-builder-fill-tel')?.addEventListener('click', fillTelFromDisplay);
      byId('htc-dn-builder-generate')?.addEventListener('click', renderPreview);
      byId('htc-dn-builder-append')?.addEventListener('click', appendRule);
      byId('htc-dn-builder-update')?.addEventListener('click', updateRule);
      byId('htc-dn-builder-clear')?.addEventListener('click', clearBuilder);
      byId('htc-dn-rules-sync')?.addEventListener('click', renderTable);
      fields.tableBody.addEventListener('click', handleTableAction);
      fields.rulesJson.addEventListener('input', renderTable);

      ['input', 'change'].forEach((eventName) => {
        Object.values(fields).forEach((field) => {
          if (field && ![fields.preview, fields.rulesJson, fields.tableBody, fields.mode].includes(field)) {
            field.addEventListener(eventName, renderPreview);
          }
        });
      });

      clearBuilder();
      renderTable();
      renderPreview();
    })();
  </script>
  <?php
}

/**
 * Admin bar menu for quick cookie purge
 */
function htc_dn_admin_bar_menu($admin_bar): void {
  if (!current_user_can('manage_options')) {
    return;
  }

  $admin_bar->add_menu([
    'id'     => 'htc-dn-purge',
    'parent' => 'top-secondary',
    'title'  => 'Clear HTC Cookies',
    'href'   => add_query_arg([
      'htc_dn_purge' => wp_create_nonce('htc_dn_purge_admin_bar'),
    ], admin_url('admin.php?page=htc-dynamic-number')),
  ]);
}

// Handle admin bar purge action
add_action('admin_init', function () {
  if (isset($_GET['htc_dn_purge']) && current_user_can('manage_options')) {
    if (wp_verify_nonce($_GET['htc_dn_purge'], 'htc_dn_purge_admin_bar')) {
      setcookie(HTC_DN_COOKIE, '', time() - 3600, '/', '', false, true);
    }
  }
});

function htc_dn_field_default_display(): void {
  $opt = htc_dn_get_options();
  printf(
    '<input class="regular-text" name="%s[default_display]" value="%s" />',
    esc_attr(HTC_DN_OPT),
    esc_attr($opt['default_display'])
  );
}

function htc_dn_field_default_tel(): void {
  $opt = htc_dn_get_options();
  printf(
    '<input class="regular-text" name="%s[default_tel]" value="%s" placeholder="+1850..." />',
    esc_attr(HTC_DN_OPT),
    esc_attr($opt['default_tel'])
  );
}

function htc_dn_field_cookie_days(): void {
  $opt = htc_dn_get_options();
  printf(
    '<input type="number" min="1" max="365" name="%s[cookie_days]" value="%d" />',
    esc_attr(HTC_DN_OPT),
    intval($opt['cookie_days'])
  );
}

function htc_dn_field_rules_json(): void {
  $opt = htc_dn_get_options();
  printf(
    '<textarea id="htc-dn-rules-json" name="%s[rules_json]" rows="18" class="large-text code">%s</textarea>',
    esc_attr(HTC_DN_OPT),
    esc_textarea($opt['rules_json'])
  );
  echo '<p class="description">You can edit rules manually here, or use "Import Bundled Rules" above to reload the spreadsheet-based promo rules.</p>';
}

/** ---------------------------
 * Shortcode
 * --------------------------- */
add_shortcode('htc_phone', 'htc_dn_shortcode_phone');

function htc_dn_shortcode_phone($atts): string {
  $opt = htc_dn_get_options();

  $atts = shortcode_atts([
    'label' => 'Call Hometown Contractors',
    'class' => 'htc-phone-link',
  ], (array) $atts, 'htc_phone');

  $display = $opt['default_display'];
  $tel     = $opt['default_tel'];

  return sprintf(
    '<a href="tel:%s" class="%s" aria-label="%s" data-htc-dn-phone>%s</a>',
    esc_attr($tel),
    esc_attr($atts['class']),
    esc_attr($atts['label']),
    esc_html($display)
  );
}

/** ---------------------------
 * Frontend swapping JS
 * --------------------------- */
add_action('wp_enqueue_scripts', 'htc_dn_enqueue_frontend');
add_filter('fluentform/insert_response_data', 'htc_dn_fluentform_insert_response_data', 10, 3);

function htc_dn_enqueue_frontend(): void {
  if (is_admin()) {
    return;
  }

  $opt = htc_dn_get_options();
  $rules = json_decode($opt['rules_json'], true);

  if (!is_array($rules)) {
    $rules = [];
  }

  $rules = array_values(array_filter(array_map('htc_dn_normalize_rule', $rules), function ($r) {
    return is_array($r) && !empty($r['enabled']);
  }));

  usort($rules, function ($a, $b) {
    return intval($a['priority'] ?? 9999) <=> intval($b['priority'] ?? 9999);
  });

  $payload = [
    'cookieName' => HTC_DN_COOKIE,
    'cookieDays' => intval($opt['cookie_days']),
    'default'    => [
      'display' => $opt['default_display'],
      'tel'     => $opt['default_tel'],
    ],
    'tracking'   => [
      'sources' => htc_dn_tracking_param_sources(),
      'targets' => htc_dn_tracking_field_targets(),
      'cookies' => htc_dn_tracking_cookie_map(),
    ],
    'rules'      => $rules,
  ];

  wp_enqueue_script(
    'htc-dn-lite',
    HTC_DN_URL . 'assets/js/htc-dn-frontend.js',
    [],
    '1.4.4',
    true
  );

  wp_localize_script('htc-dn-lite', 'HTC_DN_LITE', $payload);
}

function htc_dn_fluentform_insert_response_data($form_data, $form_id, $input_configs): array {
  if (!is_array($form_data)) {
    $form_data = [];
  }

  $tracking_values = htc_dn_get_tracking_request_values();
  if (!$tracking_values) {
    return $form_data;
  }

  $available_names = array_fill_keys(htc_dn_extract_ff_input_names($input_configs), true);

  foreach (htc_dn_tracking_field_targets() as $key => $targets) {
    $value = $tracking_values[$key] ?? '';
    if ($value === '') {
      continue;
    }

    foreach ($targets as $target) {
      $target_name = sanitize_key($target);
      if ($target_name === '' || !isset($available_names[$target_name])) {
        continue;
      }

      $existing = $form_data[$target_name] ?? '';
      if ($existing === '' || $existing === null) {
        $form_data[$target_name] = $value;
      }
    }
  }

  return $form_data;
}
