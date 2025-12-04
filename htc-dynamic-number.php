<?php
/**
 * Plugin Name: HTC Dynamic Number (Lite)
 * Description: Dynamic phone number swapping with an admin settings page. Shortcode: [htc_phone]
 * Version: 1.0.0
 * Author: Steven Monson - Hometown Contractors - Internal Use Only
 */

defined('ABSPATH') || exit;

const HTC_DN_OPT = 'htc_dn_options_lite';
const HTC_DN_COOKIE = 'htc_dn_rule_id';

function htc_dn_defaults(): array {
  return [
    'default_display' => '(850) 555-0000',
    'default_tel'     => '+18505550000',
    'cookie_days'     => 30,
    // Rules are stored as JSON in admin. See example on settings page.
    'rules_json'      => '[]',
  ];
}

function htc_dn_get_options(): array {
  $opt = get_option(HTC_DN_OPT);
  if (!is_array($opt)) $opt = [];
  return wp_parse_args($opt, htc_dn_defaults());
}

/** ---------------------------
 * Admin: Menu + Settings
 * --------------------------- */
add_action('admin_menu', 'htc_dn_admin_menu');
add_action('network_admin_menu', 'htc_dn_admin_menu'); // multisite visibility
add_action('admin_init', 'htc_dn_admin_init');

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

  // Validate JSON (must decode into an array)
  $decoded = json_decode($rules_json, true);
  if (!is_array($decoded)) {
    add_settings_error(HTC_DN_OPT, 'htc_dn_rules_json', 'Rules JSON is invalid. Please fix the JSON and save again.', 'error');
    $rules_json = '[]';
  }

  $out['rules_json'] = $rules_json;

  return $out;
}

function htc_dn_render_settings_page(): void {
  if (!current_user_can('manage_options')) return;
  ?>
  <div class="wrap">
    <h1>Dynamic Numbers</h1>
    <p>
      Use <code>[htc_phone]</code> in your Divi header. Rules are evaluated in ascending <code>priority</code> (lowest wins).
      Patterns support <code>*</code> wildcards.
    </p>

    <form method="post" action="options.php">
      <?php
        settings_fields('htc_dn_group');
        do_settings_sections('htc-dynamic-number');
        submit_button('Save Settings');
      ?>
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
  ], (array)$atts, 'htc_phone');

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
  $opt = htc_dn_get_options();
  $rules = json_decode($opt['rules_json'], true);
  if (!is_array($rules)) $rules = [];

  // Keep enabled rules only + sort by priority
  $rules = array_values(array_filter($rules, function($r){
    return is_array($r) && !empty($r['enabled']);
  }));
  usort($rules, function($a, $b){
    return intval($a['priority'] ?? 9999) <=> intval($b['priority'] ?? 9999);
  });

  $payload = [
    'cookieName' => HTC_DN_COOKIE,
    'cookieDays' => intval($opt['cookie_days']),
    'default'    => ['display' => $opt['default_display'], 'tel' => $opt['default_tel']],
    'rules'      => $rules,
  ];

  wp_register_script('htc-dn-lite', '', [], '1.0.0', true);
  wp_enqueue_script('htc-dn-lite');

  $js = 'window.HTC_DN_LITE=' . wp_json_encode($payload) . ";\n" . <<<'JS'
(() => {
  const CFG = window.HTC_DN_LITE || {};
  const RULES = Array.isArray(CFG.rules) ? CFG.rules : [];

  const getParam = (name) => {
    try { return new URL(window.location.href).searchParams.get(name); }
    catch(e) { return null; }
  };

  const getCookie = (name) => {
    const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m.pop()) : null;
  };

  const setCookie = (name, value, days) => {
    const d = new Date();
    d.setTime(d.getTime() + (days*24*60*60*1000));
    const expires = "expires=" + d.toUTCString();
    const secure = window.location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = name + "=" + encodeURIComponent(value) + ";" + expires + ";Path=/;SameSite=Lax" + secure;
  };

  const wildcardMatch = (pattern, value) => {
    const p = (pattern ?? '*').toString().trim();
    const v = (value ?? '').toString();
    const re = new RegExp('^' + p.replace(/[.+?^${}()|[\\]\\\\]/g, '\\$&').replace(/\\*/g, '.*') + '$', 'i');
    return re.test(v);
  };

  const refHost = () => {
    try {
      if (!document.referrer) return '';
      return new URL(document.referrer).host || '';
    } catch(e) { return ''; }
  };

  const resolveRule = () => {
    const host = refHost();

    for (const r of RULES) {
      if (!r || !r.id) continue;
      const type = (r.type || 'PARAM').toUpperCase();

      if (type === 'CLICKID') {
        const v = getParam(r.param);
        if (v) return r;
      }

      if (type === 'PARAM') {
        const v = getParam(r.param);
        if (v && wildcardMatch(r.pattern, v)) return r;
      }

      if (type === 'REFERRER') {
        const target = (r.param || '').toString().trim(); // e.g. "facebook.com"
        if (!host) continue;
        if (target && !host.toLowerCase().includes(target.toLowerCase())) continue;
        if (wildcardMatch(r.pattern || '*', host)) return r;
      }
    }
    return null;
  };

  const applyNumber = (num) => {
    const nodes = document.querySelectorAll('[data-htc-dn-phone]');
    nodes.forEach(node => {
      if (num.display) node.textContent = num.display;
      if (node.tagName.toLowerCase() === 'a' && num.tel) node.setAttribute('href', 'tel:' + num.tel);
    });
  };

  const boot = () => {
    const match = resolveRule();
    if (match && match.id) setCookie(CFG.cookieName, match.id, CFG.cookieDays || 30);

    const rid = getCookie(CFG.cookieName);
    const chosen = rid ? RULES.find(r => r.id === rid) : null;

    const num = chosen ? { display: chosen.display, tel: chosen.tel } : (CFG.default || {});
    applyNumber(num);
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
JS;

  wp_add_inline_script('htc-dn-lite', $js, 'after');
}
