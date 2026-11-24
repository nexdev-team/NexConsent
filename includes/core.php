<?php
/**
 * NexConsent core: options, translations, services, consent state, front-end output.
 */
defined('ABSPATH') || exit;

/* ---------------------------------------------------------------------------
 * Options
 * ------------------------------------------------------------------------- */

const NEXCONSENT_TEXT_KEYS = ['title', 'body', 'accept', 'reject', 'settings'];

function nexconsent_defaults() {
    return [
        'language' => 'auto',
        // Custom banner texts; empty = translated default.
        'title' => '', 'body' => '', 'accept' => '', 'reject' => '', 'settings' => '',
        'gtm_id' => '', 'ga_id' => '', 'gtm_analytics' => 0, 'gtm_marketing' => 0,
        // 'plugin' = NexConsent loads GTM after consent; 'external' = another service already loads it
        // (Cloudflare / Google tag gateway, server-side GTM): NexConsent only sends Consent Mode signals.
        'gtm_loader' => 'plugin',
        'mailerlite' => 0, 'external_analytics' => 0, 'external_marketing' => 0,
        // "Provider | cookie, cookie" per line.
        'services_analytics' => '', 'services_marketing' => '',
        // Raw <script> snippets that run only after consent (needs unfiltered_html).
        'scripts_analytics' => '', 'scripts_marketing' => '',
        'policy_url' => '', 'revision' => '1', 'bridge' => 'auto',
        'theme' => 'blue', 'accent' => '#1e73be', 'position' => 'bar',
        'reject_button' => 1, 'reopen_button' => 1,
        // Internal: not shown in the settings form.
        'cookie_name' => 'nexconsent', 'legacy' => 0,
    ];
}

function nexconsent_options() {
    $saved = get_option('nexconsent_options', null);
    if (!is_array($saved)) {
        // Not migrated yet (runs on init): read the 1.x settings directly so nothing changes in between.
        $old = get_option('cpc_options', null);
        $saved = is_array($old) ? nexconsent_from_legacy($old) : [];
    }
    return wp_parse_args($saved, nexconsent_defaults());
}

/** Settings of NexConsent 1.x / CPAPstore Consent ("cpc_options") in the 2.x format. */
function nexconsent_from_legacy(array $old) {
    $active = (array) get_option('active_plugins', []);
    // 1.0.x had no per-service switches: an entered GTM meant analytics + marketing.
    $legacy_gtm = !empty($old['gtm_id']) && !array_key_exists('gtm_analytics', $old);
    $legacy_ml = !empty($old['external_marketing']) && !array_key_exists('mailerlite', $old)
        && in_array('woo-mailerlite/woo-mailerlite.php', $active, true);
    $theme = $old['theme'] ?? null;
    $accent = $old['accent'] ?? null;
    if ($theme === null) {
        // Before 1.2.1 the colours lived in the stylesheet of the old plugin.
        $css = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '') . '/cpapstore-consent/assets/consent.css';
        $theme = (is_readable($css) && strpos((string) file_get_contents($css), '#196c3b') !== false) ? 'green' : 'blue';
        $accent = $theme === 'green' ? '#196c3b' : '#1e73be';
    }
    $language = $old['language'] ?? 'auto';
    return nexconsent_clean([
        'language' => $language === 'bg' ? 'bg_BG' : $language,
        'gtm_id' => $old['gtm_id'] ?? '', 'ga_id' => $old['ga_id'] ?? '',
        'gtm_analytics' => $old['gtm_analytics'] ?? ($legacy_gtm ? 1 : 0),
        'gtm_marketing' => $old['gtm_marketing'] ?? ($legacy_gtm ? 1 : 0),
        'mailerlite' => $old['mailerlite'] ?? ($legacy_ml ? 1 : 0),
        'external_analytics' => $old['external_analytics'] ?? 0,
        'external_marketing' => $old['external_marketing'] ?? 0,
        'policy_url' => $old['policy_url'] ?? home_url('/cookie-policy/'),
        'revision' => $old['revision'] ?? '1',
        'bridge' => $old['bridge'] ?? 'auto',
        'theme' => $theme, 'accent' => $accent ?: '#1e73be',
        'reject_button' => $old['reject_button'] ?? 0,
        // Keep the look visitors already know; both can be switched on in the settings.
        'reopen_button' => 0, 'position' => 'bar',
        // Same cookie name keeps every visitor's existing choice valid.
        'cookie_name' => 'cpapstore_consent', 'legacy' => 1,
    ]);
}

function nexconsent_valid_id($id, $kind) {
    $id = strtoupper(trim((string) $id));
    if (!preg_match($kind === 'gtm' ? '/^GTM-[A-Z0-9]+$/' : '/^G-[A-Z0-9]+$/', $id)) return '';
    return preg_match('/^(G|GTM)-X+$/', $id) ? '' : $id;
}

/** Validates every key. Used for saved settings, migration and auto-setup. */
function nexconsent_clean(array $in) {
    $d = nexconsent_defaults();
    $flag = function ($k) use ($in, $d) { return array_key_exists($k, $in) ? (empty($in[$k]) ? 0 : 1) : $d[$k]; };
    $languages = nexconsent_language_codes();
    $out = [
        'language' => in_array($in['language'] ?? '', $languages, true) ? $in['language'] : 'auto',
        'gtm_id' => nexconsent_valid_id($in['gtm_id'] ?? '', 'gtm'),
        'ga_id' => nexconsent_valid_id($in['ga_id'] ?? '', 'ga'),
        'revision' => substr(sanitize_text_field($in['revision'] ?? '1'), 0, 20) ?: '1',
        'policy_url' => esc_url_raw(trim((string) ($in['policy_url'] ?? ''))),
        'bridge' => in_array($in['bridge'] ?? '', ['auto', 'wpca', 'builtin'], true) ? $in['bridge'] : 'auto',
        'gtm_loader' => ($in['gtm_loader'] ?? '') === 'external' ? 'external' : 'plugin',
        'theme' => in_array($in['theme'] ?? '', ['blue', 'green', 'custom'], true) ? $in['theme'] : 'blue',
        'accent' => nexconsent_hex($in['accent'] ?? '') ?: '#1e73be',
        'position' => in_array($in['position'] ?? '', ['bar', 'box'], true) ? $in['position'] : 'bar',
        'services_analytics' => nexconsent_services_text($in['services_analytics'] ?? ''),
        'services_marketing' => nexconsent_services_text($in['services_marketing'] ?? ''),
        'scripts_analytics' => nexconsent_scripts_text($in['scripts_analytics'] ?? ''),
        'scripts_marketing' => nexconsent_scripts_text($in['scripts_marketing'] ?? ''),
        'cookie_name' => preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) ($in['cookie_name'] ?? '')) ? $in['cookie_name'] : $d['cookie_name'],
        'legacy' => empty($in['legacy']) ? 0 : 1,
    ];
    foreach (['gtm_analytics', 'gtm_marketing', 'mailerlite', 'external_analytics', 'external_marketing', 'reject_button', 'reopen_button'] as $k) $out[$k] = $flag($k);
    foreach (NEXCONSENT_TEXT_KEYS as $k) {
        $value = $k === 'body' ? sanitize_textarea_field($in[$k] ?? '') : sanitize_text_field($in[$k] ?? '');
        $out[$k] = mb_substr(trim($value), 0, $k === 'body' ? 600 : 80);
    }
    if ($out['gtm_id']) $out['ga_id'] = '';
    return $out;
}

/** "Provider | cookie, cookie" lines, cookie names limited to safe characters. */
function nexconsent_parse_services($text) {
    $services = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = explode('|', $line, 2);
        $provider = count($parts) === 2 ? trim(sanitize_text_field($parts[0])) : '';
        $cookies = array_values(array_filter(array_map('trim', explode(',', end($parts))), function ($c) {
            return (bool) preg_match('/^[A-Za-z0-9_.\-]*\*?[A-Za-z0-9_.\-]*$/', $c) && $c !== '' && $c !== '*';
        }));
        if ($cookies) $services[] = ['provider' => mb_substr($provider, 0, 60), 'cookies' => array_slice($cookies, 0, 30)];
    }
    return array_slice($services, 0, 20);
}
function nexconsent_services_text($text) {
    return implode("\n", array_map(function ($s) {
        return ($s['provider'] !== '' ? $s['provider'] . ' | ' : '') . implode(', ', $s['cookies']);
    }, nexconsent_parse_services($text)));
}

/**
 * Keeps only <script> elements of a pasted snippet. Everything else is dropped on purpose:
 * <noscript> pixels and <img> beacons would fire before consent.
 */
function nexconsent_scripts_text($html) {
    $html = (string) $html;
    if (!preg_match_all('#<script\b[^>]*>.*?</script>#is', $html, $m)) return '';
    return trim(implode("\n", $m[0]));
}

/** Pasted scripts as inert <script type="text/plain"> that consent-core.js activates after consent. */
function nexconsent_blocked_scripts($html, $category) {
    $out = '';
    if (!preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', (string) $html, $scripts, PREG_SET_ORDER)) return '';
    foreach ($scripts as $s) {
        $attrs = $s[1];
        $type = preg_match('/\stype\s*=\s*(["\']?)([^"\'\s>]+)\1/i', $attrs, $t) ? strtolower($t[2]) : '';
        if ($type !== '' && !in_array($type, ['text/javascript', 'application/javascript', 'module'], true)) continue;
        $src = preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $u) ? $u[2] : '';
        $id = preg_match('/\sid\s*=\s*(["\'])([A-Za-z0-9_\-:.]+)\1/', $attrs, $i) ? $i[2] : '';
        $out .= '<script type="text/plain" data-nxc-category="' . esc_attr($category) . '"'
            . ($src ? ' data-nxc-src="' . esc_url($src) . '"' : '')
            . ($id ? ' data-nxc-id="' . esc_attr($id) . '"' : '')
            . ($type === 'module' ? ' data-nxc-type="module"' : '')
            . (preg_match('/\sasync\b/i', $attrs) || !$src ? '' : ' data-nxc-async="false"')
            . '>' . ($src ? '' : $s[2]) . "</script>\n";
    }
    return $out;
}

/* ---------------------------------------------------------------------------
 * Upgrade from 1.x: copy the settings once, then clear page caches because
 * cached pages still contain the 1.x banner markup.
 * ------------------------------------------------------------------------- */
function nexconsent_maybe_upgrade() {
    $stored = get_option('nexconsent_version', false);
    if ($stored === NEXCONSENT_VERSION) return;
    $migrated = false;
    if (!is_array(get_option('nexconsent_options', null))) {
        $old = get_option('cpc_options', null);
        if (is_array($old)) { update_option('nexconsent_options', nexconsent_from_legacy($old)); $migrated = true; }
    }
    update_option('nexconsent_version', NEXCONSENT_VERSION);
    if ($stored !== false || $migrated) add_action('wp_loaded', 'nexconsent_purge_page_cache');
}
add_action('init', 'nexconsent_maybe_upgrade', 0);

// Activation can come from WP-CLI too (not an admin request), so this lives here.
register_activation_hook(NEXCONSENT_FILE, function () {
    // Only a brand-new install configures itself (on the next admin page); existing settings are never overwritten.
    if (get_option('nexconsent_options', null) !== null || get_option('cpc_options', null) !== null) return;
    set_transient('nexconsent_needs_setup', 1, HOUR_IN_SECONDS);
});

function nexconsent_purge_page_cache() {
    do_action('litespeed_purge_all');
    if (function_exists('rocket_clean_domain')) rocket_clean_domain();
    if (function_exists('w3tc_flush_all')) w3tc_flush_all();
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
    if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();
    do_action('wpfc_clear_all_cache');
    do_action('breeze_clear_all_cache');
    do_action('nexconsent_purge_page_cache');
}

/* ---------------------------------------------------------------------------
 * Translations. "auto" follows the site / page language through normal
 * WordPress translations (so Polylang, WPML, Loco and language packs work);
 * a fixed language reads the bundled .mo file directly.
 * ------------------------------------------------------------------------- */
add_action('init', function () {
    load_plugin_textdomain('nexconsent', false, dirname(plugin_basename(NEXCONSENT_FILE)) . '/languages');
});

/** Bundled translations (languages/nexconsent-{locale}.mo) plus "auto" and English. */
function nexconsent_language_codes() {
    $codes = ['auto', 'en'];
    foreach ((array) glob(dirname(NEXCONSENT_FILE) . '/languages/nexconsent-*.mo') as $file) {
        $code = substr(basename($file, '.mo'), strlen('nexconsent-'));
        if (preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $code)) $codes[] = $code;
    }
    return $codes;
}
function nexconsent_languages() {
    $names = ['auto' => __('Site language (automatic)', 'nexconsent'), 'en' => 'English', 'el' => 'Ελληνικά', 'bg_BG' => 'Български',
        'de_DE' => 'Deutsch', 'fr_FR' => 'Français', 'es_ES' => 'Español', 'it_IT' => 'Italiano', 'ro_RO' => 'Română'];
    $list = [];
    foreach (nexconsent_language_codes() as $code) $list[$code] = $names[$code] ?? $code;
    return $list;
}

function nexconsent__($text) {
    $language = nexconsent_options()['language'];
    if ($language === 'auto') return translate($text, 'nexconsent');
    static $mo = [];
    if (!array_key_exists($language, $mo)) {
        $mo[$language] = false;
        $file = dirname(NEXCONSENT_FILE) . '/languages/nexconsent-' . $language . '.mo';
        if ($language !== 'en' && class_exists('MO') && is_readable($file)) {
            $catalog = new MO();
            if ($catalog->import_from_file($file)) $mo[$language] = $catalog;
        }
    }
    return $mo[$language] ? $mo[$language]->translate($text) : $text;
}

/** All front-end texts; $custom applies the site owner's own texts. */
function nexconsent_strings($custom = true) {
    $shop = class_exists('WooCommerce');
    $t = [
        'title' => nexconsent__('Your cookie choices'),
        'body' => $shop
            ? nexconsent__('Necessary cookies keep the site and basket working. We use analytics and marketing cookies only with your permission.')
            : nexconsent__('Necessary cookies keep the site working. We use analytics and marketing cookies only with your permission.'),
        'accept' => nexconsent__('Accept all'), 'reject' => nexconsent__('Reject optional'), 'settings' => nexconsent__('Settings'),
        'save' => nexconsent__('Save choices'), 'close' => nexconsent__('Close'), 'policy' => nexconsent__('Cookie policy'),
        'always' => nexconsent__('Always active'), 'necessary' => nexconsent__('Necessary'),
        'analytics' => nexconsent__('Analytics'), 'marketing' => nexconsent__('Marketing'),
        'necessary_desc' => $shop
            ? nexconsent__('Remember your choices and support the basket, session and site security.')
            : nexconsent__('Remember your choices and support the session and site security.'),
        'analytics_desc' => nexconsent__('Help us understand how the site is used and improve it.'),
        'marketing_desc' => nexconsent__('Measure advertising performance and allow personalized advertising.'),
        'mailerlite_purpose' => nexconsent__('Displays newsletter forms and offers for marketing campaigns.'),
        'change' => nexconsent__('Cookie settings'),
        'error' => nexconsent__('Your choice could not be saved. Optional services remain off.'),
        'cookie' => nexconsent__('Cookie / service'), 'purpose' => nexconsent__('Purpose'), 'duration' => nexconsent__('Duration'),
        'choice_purpose' => nexconsent__('Stores the categories, date and version of your choice.'),
        'choice_duration' => nexconsent__('180 days'), 'store_duration' => nexconsent__('According to store settings'),
        'service_duration' => nexconsent__('According to service settings'),
        'other_services' => nexconsent__('Other services'),
    ];
    if (!$custom) return $t;
    $o = nexconsent_options();
    foreach (NEXCONSENT_TEXT_KEYS as $k) if ($o[$k] !== '') $t[$k] = $o[$k];
    return apply_filters('nexconsent_strings', $t);
}

/* ---------------------------------------------------------------------------
 * Services: one registry drives the categories shown, the cookies removed on
 * withdrawal, the cookie table and the consent version.
 * ------------------------------------------------------------------------- */
function nexconsent_services() {
    $o = nexconsent_options(); $t = nexconsent_strings();
    $gtm = nexconsent_valid_id($o['gtm_id'], 'gtm');
    $ga = $gtm ? '' : nexconsent_valid_id($o['ga_id'], 'ga');
    $active = (array) get_option('active_plugins', []);
    // 'sig_provider' is what enters the consent version, so translations and branding never re-ask visitors.
    $services = [['id' => 'choice', 'category' => 'necessary', 'provider' => nexconsent_brand()['name'], 'sig_provider' => 'NexConsent',
        'cookies' => [$o['cookie_name']], 'purpose' => $t['choice_purpose'], 'duration' => $t['choice_duration']]];
    if (class_exists('WooCommerce')) $services[] = ['id' => 'woocommerce', 'category' => 'necessary', 'provider' => 'WooCommerce',
        'cookies' => ['woocommerce_cart_hash', 'woocommerce_items_in_cart', 'wp_woocommerce_session_*'],
        'purpose' => $t['necessary_desc'], 'duration' => $t['store_duration']];
    if (($gtm && $o['gtm_analytics']) || $ga || $o['external_analytics']) $services[] = ['id' => 'analytics', 'category' => 'analytics',
        'provider' => (($gtm && $o['gtm_analytics']) || $ga) ? 'Google Analytics / GTM' : 'Registered analytics services',
        // Google's cookies are listed for other analytics sources too, so they are always removed on withdrawal.
        'cookies' => ['_ga', '_ga_*', '_gid', '_gat*'], 'purpose' => $t['analytics_desc'], 'duration' => $t['service_duration']];
    if (($gtm && $o['gtm_marketing']) || $o['external_marketing']) $services[] = ['id' => 'marketing', 'category' => 'marketing',
        'provider' => ($gtm && $o['gtm_marketing']) ? 'Google Ads / GTM'
            : (in_array('google-listings-and-ads/google-listings-and-ads.php', $active, true) ? 'Google Ads (Google for WooCommerce)' : 'Registered marketing services'),
        'cookies' => ['_gcl_*', '_gac_*'], 'purpose' => $t['marketing_desc'], 'duration' => $t['service_duration']];
    if ($o['mailerlite']) $services[] = ['id' => 'mailerlite', 'category' => 'marketing', 'provider' => 'MailerLite',
        'cookies' => ['mailerlite*', '_mailerlite*', 'ml_*'], 'purpose' => $t['mailerlite_purpose'], 'duration' => $t['service_duration']];
    if (function_exists('wp_has_consent')) $services[] = ['id' => 'wp-consent-api', 'category' => 'necessary', 'provider' => 'WordPress Consent API',
        'cookies' => [apply_filters('wp_consent_cookie_prefix', 'wp_consent') . '_*'], 'purpose' => $t['choice_purpose'], 'duration' => $t['choice_duration']];
    foreach (['analytics', 'marketing'] as $category) {
        $listed = nexconsent_parse_services($o['services_' . $category]);
        foreach ($listed as $i => $s) $services[] = ['id' => 'custom-' . $category . '-' . ($i + 1), 'category' => $category,
            'provider' => $s['provider'] !== '' ? $s['provider'] : $t['other_services'], 'sig_provider' => $s['provider'] !== '' ? $s['provider'] : 'Other services',
            'cookies' => $s['cookies'], 'purpose' => $t[$category . '_desc'], 'duration' => $t['service_duration']];
        // Scripts without listed cookies still need their category on the banner.
        if (!$listed && trim($o['scripts_' . $category]) !== '') $services[] = ['id' => 'scripts-' . $category, 'category' => $category,
            'provider' => $t['other_services'], 'sig_provider' => 'Other services', 'cookies' => [], 'purpose' => $t[$category . '_desc'], 'duration' => $t['service_duration']];
    }
    // Remember the built-in purpose, so the version only changes if a filter changes it.
    foreach ($services as &$service) $service['default_purpose'] = $service['purpose'];
    unset($service);
    return apply_filters('nexconsent_services', $services);
}

function nexconsent_categories() {
    return array_values(array_intersect(['analytics', 'marketing'], array_unique(array_column(nexconsent_services(), 'category'))));
}

/* ---------------------------------------------------------------------------
 * WooCommerce order attribution (sourcebuster sbjs_* cookies).
 * auto    = WP Consent API when active, otherwise built-in.
 * wpca    = WP Consent API (falls back to built-in if it is missing).
 * builtin = NexConsent switches order attribution itself.
 * ------------------------------------------------------------------------- */
function nexconsent_wpca_available() {
    return class_exists('WP_CONSENT_API') || function_exists('wp_set_consent');
}
function nexconsent_bridge() {
    if (nexconsent_options()['bridge'] === 'builtin') return 'builtin';
    return nexconsent_wpca_available() ? 'wpca' : 'builtin';
}
function nexconsent_order_attribution_enabled() {
    return class_exists('WooCommerce') && get_option('woocommerce_feature_order_attribution_enabled', 'yes') === 'yes';
}
// Pages are cached for everyone, so the server always starts with tracking off; the browser
// switches it on after marketing consent.
add_filter('wc_order_attribution_allow_tracking', function ($allow) {
    return (nexconsent_categories() && nexconsent_bridge() === 'builtin') ? false : $allow;
}, 999);

/* ---------------------------------------------------------------------------
 * Consent version and visitor record
 * ------------------------------------------------------------------------- */
function nexconsent_config() {
    $o = nexconsent_options(); $services = nexconsent_services();
    $gtm = nexconsent_valid_id($o['gtm_id'], 'gtm');
    $ga = $gtm ? '' : nexconsent_valid_id($o['ga_id'], 'ga');
    $patterns = ['analytics' => [], 'marketing' => []];
    foreach ($services as $s) if (isset($patterns[$s['category']])) $patterns[$s['category']] = array_merge($patterns[$s['category']], $s['cookies']);
    // Order attribution cookies are marketing; kept out of the version so enabling WooCommerce's feature never re-asks.
    if (nexconsent_order_attribution_enabled()) $patterns['marketing'][] = 'sbjs_*';
    // Providers, cookies and purposes define the version; language and branding do not.
    $signature = array_map(function ($s) {
        $purpose = (isset($s['default_purpose']) && $s['purpose'] === $s['default_purpose']) ? $s['id'] . ':1' : $s['purpose'];
        return [$s['id'], $s['category'], $s['sig_provider'] ?? $s['provider'], array_values($s['cookies']), $purpose, $s['purpose_revision'] ?? '1'];
    }, $services);
    return [
        'cookie' => $o['cookie_name'], 'maxAge' => 180 * DAY_IN_SECONDS,
        'version' => substr(hash('sha256', wp_json_encode([$o['revision'], $gtm, $ga, $signature])), 0, 16),
        'categories' => nexconsent_categories(), 'gtm' => $gtm, 'ga' => $ga,
        'patterns' => array_map(function ($p) { return array_values(array_unique($p)); }, $patterns),
        'wpCookiePrefix' => apply_filters('wp_consent_cookie_prefix', 'wp_consent'),
        'wpApi' => function_exists('wp_has_consent'), 'bridge' => nexconsent_bridge(), 'legacy' => (bool) $o['legacy'],
        'gtmExternal' => $gtm !== '' && $o['gtm_loader'] === 'external',
    ];
}

function nexconsent_record() {
    $config = nexconsent_config();
    $raw = isset($_COOKIE[$config['cookie']]) ? stripslashes((string) $_COOKIE[$config['cookie']]) : '';
    $record = json_decode($raw, true);
    if (!is_array($record)) $record = json_decode(rawurldecode($raw), true);
    $now = microtime(true) * 1000;
    if (!is_array($record) || ($record['v'] ?? '') !== $config['version'] || !isset($record['ts'])
        || !(is_int($record['ts']) || is_float($record['ts'])) || $record['ts'] > $now || $now - $record['ts'] >= $config['maxAge'] * 1000
        || !is_bool($record['analytics'] ?? null) || !is_bool($record['marketing'] ?? null)) return null;
    return $record;
}

/** Server-side check for themes and plugins: nexconsent_has_consent('marketing'). */
function nexconsent_has_consent($category) {
    if ($category === 'necessary') return true;
    $record = nexconsent_record();
    return $record && in_array($category, nexconsent_categories(), true) && $record[$category] === true;
}

/* WP Consent API */
add_filter('wp_get_consent_type', function ($value) { return nexconsent_categories() ? 'optin' : $value; });
add_filter('wp_consent_api_cookie_expiration', function ($value) { return nexconsent_categories() ? 180 : $value; });
add_filter('wp_has_consent', function ($value, $category) {
    if (!nexconsent_categories()) return $value;
    if ($category === 'functional') return true;
    $key = in_array($category, ['statistics', 'statistics-anonymous'], true) ? 'analytics' : ($category === 'marketing' ? 'marketing' : '');
    return $key ? nexconsent_has_consent($key) : false;
}, 100, 2);
add_filter('wp_consent_api_registered_' . plugin_basename(NEXCONSENT_FILE), '__return_true');

/* ---------------------------------------------------------------------------
 * Front end
 * ------------------------------------------------------------------------- */
function nexconsent_policy_url() {
    $url = nexconsent_options()['policy_url'];
    if (!$url && function_exists('get_privacy_policy_url')) $url = get_privacy_policy_url();
    return (string) apply_filters('nexconsent_policy_url', $url);
}

add_action('wp_head', function () {
    if (is_admin() || !nexconsent_categories()) return;
    // Consent Mode default, inline so it is in place before any Google tag runs, including one
    // injected at the top of the page by a CDN (Cloudflare / Google tag gateway). consent-core.js
    // then sends the visitor's stored choice as an update.
    echo '<script id="nxc-default" data-no-optimize="1" data-no-defer="1" data-cfasync="false" nowprocket>'
        . 'window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};'
        . "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});"
        . '</script>' . "\n";
    echo '<script id="nxc-config" type="application/json">' . wp_json_encode(nexconsent_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
    // Must run before any optional script: synchronous, local, excluded from optimisers.
    echo '<script id="nxc-bootstrap" src="' . esc_url(plugins_url('assets/consent-core.js', NEXCONSENT_FILE) . '?ver=' . NEXCONSENT_VERSION)
        . '" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-cfasync="false" data-pagespeed-no-defer nowprocket></script>' . "\n";
}, -9999);

add_action('wp_enqueue_scripts', function () {
    if (!nexconsent_categories()) return;
    wp_enqueue_style('nexconsent', plugins_url('assets/consent.css', NEXCONSENT_FILE), [], NEXCONSENT_VERSION);
    wp_add_inline_style('nexconsent', nexconsent_palette_css());
    wp_enqueue_script('nexconsent-ui', plugins_url('assets/consent-ui.js', NEXCONSENT_FILE), [], NEXCONSENT_VERSION, true);
});
// Give WooCommerce the visitor's choice right before order-attribution.js starts, so returning
// visitors keep their attribution cookies instead of having them reset on every page.
add_action('wp_enqueue_scripts', function () {
    if (!nexconsent_categories() || nexconsent_bridge() !== 'builtin' || !wp_script_is('wc-order-attribution', 'enqueued')) return;
    wp_add_inline_script('wc-order-attribution', 'if(window.wc_order_attribution&&wc_order_attribution.params){wc_order_attribution.params.allowTracking=!!(window.NexConsent&&NexConsent.get().marketing);}', 'before');
}, 100);

add_action('wp_footer', function () {
    if (!nexconsent_categories()) return;
    $t = nexconsent_strings(); $o = nexconsent_options(); $policy = nexconsent_policy_url();
    $link = $policy ? ' <a href="' . esc_url($policy) . '">' . esc_html($t['policy']) . '</a>' : '';
    ?>
<section id="nxc-banner" class="nxc nxc--<?php echo esc_attr($o['position']); ?>" role="region" aria-labelledby="nxc-title" hidden>
  <div class="nxc-inner">
    <div class="nxc-copy"><h2 id="nxc-title"><?php echo esc_html($t['title']); ?></h2>
      <p><?php echo esc_html($t['body']) . $link; ?></p>
      <p class="nxc-error" role="status" hidden><?php echo esc_html($t['error']); ?></p>
    </div>
    <div class="nxc-actions<?php echo $o['reject_button'] ? ' nxc-actions--3' : ''; ?>">
      <button type="button" data-nxc="accept"><?php echo esc_html($t['accept']); ?></button>
      <?php if ($o['reject_button']) : ?><button type="button" class="nxc-quiet" data-nxc="reject"><?php echo esc_html($t['reject']); ?></button><?php endif; ?>
      <button type="button" class="nxc-quiet" data-nxc="open"><?php echo esc_html($t['settings']); ?></button>
    </div>
  </div>
</section>
<div id="nxc-overlay" class="nxc-overlay" hidden>
  <section class="nxc-dialog" role="dialog" aria-modal="true" aria-labelledby="nxc-dialog-title" tabindex="-1">
    <div class="nxc-dialog-head"><h2 id="nxc-dialog-title"><?php echo esc_html($t['change']); ?></h2>
      <button type="button" class="nxc-close" data-nxc="close" aria-label="<?php echo esc_attr($t['close']); ?>">×</button>
    </div>
    <p><?php echo esc_html($t['body']); ?></p>
    <div class="nxc-category"><strong><?php echo esc_html($t['necessary']); ?></strong><span class="nxc-locked"><?php echo esc_html($t['always']); ?></span><p><?php echo esc_html($t['necessary_desc']); ?></p></div>
    <?php foreach (nexconsent_categories() as $key) : ?>
    <div class="nxc-category"><label for="nxc-<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($t[$key]); ?></strong></label>
      <input type="checkbox" id="nxc-<?php echo esc_attr($key); ?>" data-nxc-choice="<?php echo esc_attr($key); ?>" aria-describedby="nxc-<?php echo esc_attr($key); ?>-desc">
      <p id="nxc-<?php echo esc_attr($key); ?>-desc"><?php echo esc_html($t[$key . '_desc']); ?></p>
    </div>
    <?php endforeach; ?>
    <?php if ($policy) : ?><p><a href="<?php echo esc_url($policy); ?>"><?php echo esc_html($t['policy']); ?></a></p><?php endif; ?>
    <p class="nxc-error" role="status" hidden><?php echo esc_html($t['error']); ?></p>
    <div class="nxc-actions"><button type="button" data-nxc="accept"><?php echo esc_html($t['accept']); ?></button><button type="button" class="nxc-quiet" data-nxc="save"><?php echo esc_html($t['save']); ?></button></div>
  </section>
</div>
<?php if ($o['reopen_button']) : ?><button type="button" id="nxc-reopen" class="nxc-reopen" data-nxc="open" hidden><?php echo esc_html($t['change']); ?></button><?php endif; ?>
<?php
    foreach (['analytics', 'marketing'] as $category) {
        if (in_array($category, nexconsent_categories(), true)) echo nexconsent_blocked_scripts($o['scripts_' . $category], $category); // phpcs:ignore -- built from validated attributes; code saved only by users with unfiltered_html.
    }
}, 10); // Before WordPress prints footer scripts (priority 20).

function nexconsent_settings_shortcode() {
    return nexconsent_categories() ? '<button type="button" class="nxc-settings-link" data-nxc="open">' . esc_html(nexconsent_strings()['change']) . '</button>' : '';
}
function nexconsent_cookie_table_shortcode() {
    $t = nexconsent_strings();
    $html = '<div class="nxc-table-wrap"><table><thead><tr><th>' . esc_html($t['cookie']) . '</th><th>' . esc_html($t['purpose']) . '</th><th>' . esc_html($t['duration']) . '</th></tr></thead><tbody>';
    foreach (nexconsent_services() as $s) {
        $html .= '<tr><td>' . esc_html($s['provider']) . ($s['cookies'] ? '<br>' . esc_html(implode(', ', $s['cookies'])) : '') . '</td><td>' . esc_html($s['purpose']) . '</td><td>' . esc_html($s['duration']) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}
add_action('init', function () {
    add_shortcode('nexconsent_settings', 'nexconsent_settings_shortcode');
    add_shortcode('nexconsent_cookie_table', 'nexconsent_cookie_table_shortcode');
    if (nexconsent_options()['legacy']) {
        // Shortcodes of the predecessor, still placed in footers and policy pages.
        add_shortcode('cpapstore_cookie_settings', 'nexconsent_settings_shortcode');
        add_shortcode('cpapstore_cookie_table', 'nexconsent_cookie_table_shortcode');
    }
});

// The WooCommerce MailerLite loader runs only after marketing consent; its account settings are kept.
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (is_admin() || $handle !== 'mailerlite-rewrite-universal' || !nexconsent_options()['mailerlite']) return $tag;
    $tag = preg_replace('/\s+(?:src|type)=["\'][^"\']*["\']/i', '', $tag);
    return preg_replace('/<script\b/i', '<script type="text/plain" data-nxc-category="marketing" data-nxc-src="' . esc_url($src) . '"', $tag, 1);
}, 20, 3);
