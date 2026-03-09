<?php
/**
 * Plugin Name: HTC Dynamic Number (Lite)
 * Description: Dynamic phone number swapping with an admin settings page. Shortcode: [htc_phone]
 * Version: 1.3.0
 * Author: Steven Monson - Hometown Contractors - Internal Use Only
 */

defined('ABSPATH') || exit;

define('HTC_DN_OPT', 'htc_dn_options_lite');
define('HTC_DN_COOKIE', 'htc_dn_rule_id');
define('HTC_DN_PATH', plugin_dir_path(__FILE__));
define('HTC_DN_URL', plugin_dir_url(__FILE__));

function htc_dn_defaults(): array {
  return [
    'default_display' => '(850) 203-5529',
    'default_tel'     => '+18502035529',
    'cookie_days'     => 30,
    'rules_json'      => '[]',
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

function htc_dn_render_settings_page(): void {
  if (!current_user_can('manage_options')) {
    return;
  }

  $debug = htc_dn_get_debug_info();

  // Handle cookie purge
  if (isset($_POST['htc_dn_purge_cookies']) && check_admin_referer('htc_dn_purge_nonce')) {
    setcookie(HTC_DN_COOKIE, '', time() - 3600, '/', '', false, true);
    echo '<div class="notice notice-success is-dismissible"><p>✓ Cookie purged successfully.</p></div>';
  }
  ?>
  <div class="wrap">
    <h1>Dynamic Numbers</h1>
    <p>
      Use <code>[htc_phone]</code> in your Divi header. Rules are evaluated in ascending <code>priority</code> (lowest wins).
      Patterns support <code>*</code> wildcards.
    </p>

    <!-- DEBUG PANEL -->
    <div style="background:#f5f5f5; border-left:4px solid #0073aa; padding:15px; margin:15px 0; border-radius:4px;">
      <h3 style="margin-top:0; color:#0073aa;">🔍 Debug Panel</h3>
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
        💡 This panel shows what your current admin session sees. Visitor sessions may differ based on their traffic source.
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
    <form method="post" style="margin-bottom: 20px;">
      <?php wp_nonce_field('htc_dn_purge_nonce'); ?>
      <input type="hidden" name="htc_dn_purge_cookies" value="1" />
      <button type="submit" class="button button-secondary" onclick="return confirm('This will clear the tracking cookie. Continue?');">
        🔄 Clear Admin Session Cookie
      </button>
      <p style="font-size:0.9em; color:#666; margin:8px 0 0 0;">
        Clears your admin session's cookie. Visitor cookies are unaffected.
      </p>
    </form>

    <hr />
    <h2>Rules JSON example</h2>
    <pre style="background:#fff; padding:12px; border:1px solid #ccd0d4; border-radius:8px; overflow:auto;">[
  {
    "id": "windows_brand",
    "enabled": true,
    "priority": 10,
    "type": "PARAM",
    "param": "utm_campaign",
    "pattern": "windows_brand",
    "display": "(850) 555-1001",
    "tel": "+18505551001"
  },
  {
    "id": "google_any",
    "enabled": true,
    "priority": 50,
    "type": "CLICKID",
    "param": "gclid",
    "pattern": "*",
    "display": "(850) 555-2001",
    "tel": "+18505552001"
  },
  {
    "id": "meta_any",
    "enabled": true,
    "priority": 60,
    "type": "CLICKID",
    "param": "fbclid",
    "pattern": "*",
    "display": "(850) 555-3001",
    "tel": "+18505553001"
  }
]</pre>
    <p><strong>Type values:</strong> <code>PARAM</code> (match URL param value), <code>CLICKID</code> (param presence), <code>REFERRER</code> (match referrer host).</p>
  </div>
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
    'title'  => '🔄 Clear HTC Cookies',
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
    '<textarea name="%s[rules_json]" rows="14" class="large-text code">%s</textarea>',
    esc_attr(HTC_DN_OPT),
    esc_textarea($opt['rules_json'])
  );
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

function htc_dn_enqueue_frontend(): void {
  if (is_admin()) {
    return;
  }

  $opt = htc_dn_get_options();
  $rules = json_decode($opt['rules_json'], true);

  if (!is_array($rules)) {
    $rules = [];
  }

  $rules = array_values(array_filter($rules, function ($r) {
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
    'rules'      => $rules,
  ];

  wp_enqueue_script(
    'htc-dn-lite',
    HTC_DN_URL . 'assets/js/htc-dn-frontend.js',
    [],
    '1.3.0',
    true
  );

  wp_localize_script('htc-dn-lite', 'HTC_DN_LITE', $payload);
}