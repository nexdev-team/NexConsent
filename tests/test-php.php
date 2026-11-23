<?php
// Minimal WordPress stubs to exercise the plugin's own logic.
define('ABSPATH', '/tmp/'); define('MB_IN_BYTES', 1048576); define('DAY_IN_SECONDS', 86400); define('HOUR_IN_SECONDS', 3600);
$GLOBALS['opts'] = []; $GLOBALS['filters'] = []; $GLOBALS['html'] = '';
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['filters'][$h][] = $cb; }
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['actions'][$h][] = $cb; }
function apply_filters($h, $v, ...$args) { foreach ($GLOBALS['filters'][$h] ?? [] as $cb) $v = is_string($cb) && $cb === '__return_true' ? true : call_user_func($cb, $v, ...$args); return $v; }
function add_shortcode() {} function register_activation_hook() {} function plugin_basename($f) { return 'cpapstore-consent/cpapstore-consent.php'; }
function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; }
function home_url($p = '') { return 'https://shop.example.com' . $p; }
function wp_parse_args($a, $d) { return array_merge($d, (array) $a); }
function get_locale() { return 'el'; } function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function sanitize_text_field($s) { return trim((string) $s); } function esc_url_raw($s) { return filter_var($s, FILTER_VALIDATE_URL) ? $s : ''; }
function add_settings_error() {} function add_query_arg($k, $v, $u) { return $u . '?' . $k . '=' . $v; }
function wp_remote_get($u, $a = []) { if (strpos($u, 'api.github.com') !== false) return ($GLOBALS['remote'])($u); return ['body' => $GLOBALS['html']]; } function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? 200; } function esc_url($u){return $u;} function wp_kses_post($s){return $s;} function get_bloginfo(){return '7.1';} function admin_url($p=''){return 'https://shop.example.com/wp-admin/'.$p;} function wp_nonce_url($u){return $u;} function is_wp_error($x) { return false; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function get_page_by_path($s) { return $s === 'cookie-policy' ? (object) ['post_status' => 'publish', 'slug' => $s] : null; }
function get_permalink($p) { return 'https://shop.example.com/cookie-policy/'; } function get_post_status() { return false; }
function gmdate_stub() {}
function get_theme_mod($k,$d=''){ return $GLOBALS['theme_mod'][$k] ?? $d; }
function get_site_transient($k){ return $GLOBALS['st'][$k] ?? false; } function set_site_transient($k,$v,$e=0){ $GLOBALS['st'][$k]=$v; } function plugins_url($p='',$f=''){ return 'https://shop.example.com/wp-content/plugins/nexconsent/'.$p; }
function wp_doing_ajax() { return false; } function wp_doing_cron() { return false; }
class WooCommerce {}
require dirname(__DIR__) . '/nexconsent.php';
$fails = 0;
function check($n, $c, $x = '') { global $fails; echo ($c ? 'PASS ' : 'FAIL ') . $n . ($x !== '' ? '  ' . $x : '') . "\n"; if (!$c) $fails++; }

// Bridge selection
$GLOBALS['opts']['cpc_options'] = ['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'gtm_marketing' => 1, 'bridge' => 'auto'];
check('auto without WP Consent API -> builtin', cpc_bridge() === 'builtin');
$GLOBALS['opts']['cpc_options']['bridge'] = 'wpca';
check('wpca selected but missing -> builtin fallback', cpc_bridge() === 'builtin');
if (true) { function wp_set_consent() {} } // simulate WP Consent API present (conditional = not hoisted)
check('wpca selected and present -> wpca', cpc_bridge() === 'wpca');
$GLOBALS['opts']['cpc_options']['bridge'] = 'auto';
check('auto with WP Consent API -> wpca', cpc_bridge() === 'wpca');
$GLOBALS['opts']['cpc_options']['bridge'] = 'builtin';
check('builtin forced even with API', cpc_bridge() === 'builtin');

// Server filter for order attribution
$allow = apply_filters('wc_order_attribution_allow_tracking', true);
check('builtin: server allowTracking forced false', $allow === false);
$GLOBALS['opts']['cpc_options']['bridge'] = 'wpca';
check('wpca: server filter untouched', apply_filters('wc_order_attribution_allow_tracking', true) === true);

// Config + signature stability: bridge must not change the consent version
$GLOBALS['opts']['cpc_options']['bridge'] = 'builtin'; $v1 = cpc_config()['version'];
$GLOBALS['opts']['cpc_options']['bridge'] = 'wpca'; $v2 = cpc_config()['version'];
check('switching bridge keeps existing consents valid', $v1 === $v2);
check('sbjs_* in marketing cleanup patterns', in_array('sbjs_*', cpc_config()['patterns']['marketing'], true));

// Sanitize
check('sanitize keeps valid bridge', cpc_sanitize(['bridge' => 'builtin'])['bridge'] === 'builtin');
check('sanitize rejects junk bridge', cpc_sanitize(['bridge' => 'x'])['bridge'] === 'auto');

// Detection
$GLOBALS['opts']['cpc_options'] = [];
$GLOBALS['opts']['active_plugins'] = ['woo-mailerlite/woo-mailerlite.php'];
$GLOBALS['html'] = '<script id="cpc-config" type="application/json">{"gtm":"GTM-OLDOLD1"}</script><script>(function(w,d,s,l,i){})(window,document,"script","dataLayer","GTM-NTPKG5T");</script><script src="https://www.googletagmanager.com/gtag/js?id=G-ZHM786SQ0S"></script><script src="https://cdn-cookieyes.com/x.js"></script>';
$f = cpc_detect();
check('detect GTM (ignores own config)', $f['gtm'] === ['GTM-NTPKG5T'], json_encode($f['gtm']));
check('detect GA4', $f['ga'] === ['G-ZHM786SQ0S']);
check('detect policy page', $f['policy_url'] === 'https://shop.example.com/cookie-policy/');
check('detect MailerLite', $f['mailerlite'] === 1);
check('detect other banner', $f['other_banners'] === ['CookieYes'], json_encode($f['other_banners']));
$o = cpc_sanitize(cpc_detected_options($f));
check('detected options: GTM wins, GA cleared, both services on', $o['gtm_id'] === 'GTM-NTPKG5T' && $o['ga_id'] === '' && $o['gtm_analytics'] === 1 && $o['gtm_marketing'] === 1, json_encode($o));
$GLOBALS['opts']['cpc_options'] = $o;
check('after auto-setup the banner turns on', cpc_categories() === ['analytics', 'marketing']);

// Security: IDs in visible text (e.g. a product review) must not be picked up
$GLOBALS['html'] = '<p>Great mask! visit GTM-EVIL123 and G-EVILEVIL1</p><div>googletagmanager.com/gtm.js?id=GTM-EVIL999</div><script>gtag("config","G-GOOD1234");</script>';
$f2 = cpc_detect();
check('security: GTM in plain text / non-script markup ignored', $f2['gtm'] === [], json_encode($f2['gtm']));
check('security: only GA from real gtag config picked', $f2['ga'] === ['G-GOOD1234'], json_encode($f2['ga']));
$GLOBALS['html'] = '<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-ABCD123"></script>';
check('detect GTM from script src', cpc_detect()['gtm'] === ['GTM-ABCD123']);

// GTM package
$pkg = cpc_gtm_package(); $json = json_encode($pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents(sys_get_temp_dir() . '/nexconsent-gtm-package.json', $json);
$cv = $pkg['containerVersion'];
check('GTM package: 2 variables, 2 triggers, publicId set', count($cv['variable']) === 2 && count($cv['trigger']) === 2 && $cv['container']['publicId'] === 'GTM-NTPKG5T');
check('GTM triggers reference existing variable names', $cv['trigger'][1]['filter'][0]['parameter'][0]['value'] === '{{' . $cv['variable'][1]['name'] . '}}');
// Colours
$GLOBALS['opts']['cpc_options'] = ['theme' => 'green'];
check('green preset = cpapstore.bg colours', cpc_palette()['accent'] === '#196c3b' && cpc_palette()['text'] === '#15332b');
$GLOBALS['opts']['cpc_options'] = [];
check('existing installs without theme stay blue', cpc_palette()['accent'] === '#1e73be');
foreach (['#1e73be', '#29a643', '#ffcc00', '#e91e63', '#000000', '#ffffff'] as $hex) {
  $GLOBALS['opts']['cpc_options'] = ['theme' => 'custom', 'accent' => $hex]; $pal = cpc_palette();
  $ok = cpc_contrast($pal['accent'], '#ffffff') >= 4.5 && cpc_contrast($pal['text'], '#ffffff') >= 7;
  foreach ($pal as $v) $ok = $ok && preg_match('/^#[0-9a-f]{3,8}$/', $v);
  check("custom $hex: valid palette, button text readable", (bool) $ok, $pal['accent'] . ' ' . round(cpc_contrast($pal['accent'], '#ffffff'), 2));
}
check('palette CSS only contains safe values', preg_match('/^\.cpc,[^{]+\{(--cpc-[a-z-]+:#[0-9a-f]{3,8};)+\}$/', cpc_palette_css()) === 1, cpc_palette_css());
check('sanitize rejects bad colour', cpc_sanitize(['theme' => 'custom', 'accent' => 'red;}body{x'])['accent'] === '#1e73be');
check('sanitize rejects bad theme', cpc_sanitize(['theme' => '<x>'])['theme'] === 'blue');
$GLOBALS['theme_mod'] = ['color_primary' => '#29A643'];
$GLOBALS['html'] = '';
$f = cpc_detect(); check('detect Flatsome primary colour', $f['accent'] === '#29a643');
$GLOBALS['opts']['cpc_options'] = [];
check('manual re-detect does not change colours', cpc_detected_options($f)['theme'] === 'blue');
$f['apply_colour'] = true;
check('fresh install uses theme colour', cpc_detected_options($f)['theme'] === 'custom' && cpc_detected_options($f)['accent'] === '#29a643');

// Legacy colour migration
@mkdir('/tmp/wp-plugins/cpapstore-consent/assets', 0777, true);
if (!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', '/tmp/wp-plugins');
file_put_contents('/tmp/wp-plugins/cpapstore-consent/assets/consent.css', '.cpc-actions button{background:#196c3b}');
$GLOBALS['opts']['cpc_options'] = ['gtm_id' => 'GTM-ABC1234', 'language' => 'bg'];
cpc_migrate_legacy_colours();
check('legacy green site stays green', $GLOBALS['opts']['cpc_options']['theme'] === 'green' && $GLOBALS['opts']['cpc_options']['gtm_id'] === 'GTM-ABC1234');
$GLOBALS['opts']['cpc_options'] = ['theme' => 'custom', 'accent' => '#7b1fa2'];
cpc_migrate_legacy_colours();
check('existing colour choice untouched', $GLOBALS['opts']['cpc_options']['theme'] === 'custom');
unlink('/tmp/wp-plugins/cpapstore-consent/assets/consent.css');
$GLOBALS['opts']['cpc_options'] = ['gtm_id' => 'GTM-ABC1234'];
cpc_migrate_legacy_colours();
check('legacy site without old files -> blue', $GLOBALS['opts']['cpc_options']['theme'] === 'blue');

// External services: Google cookies are cleaned on withdrawal
$GLOBALS['opts']['cpc_options'] = ['external_marketing' => 1, 'external_analytics' => 1];
$pat = cpc_config()['patterns'];
check('external marketing cleans _gcl_*', in_array('_gcl_*', $pat['marketing'], true) && in_array('sbjs_*', $pat['marketing'], true), json_encode($pat));
check('external analytics cleans _ga', in_array('_ga', $pat['analytics'], true));

// Updater (GitHub releases)
$GLOBALS['remote'] = function ($url) {
  return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v1.3.5', 'draft' => false, 'prerelease' => false, 'html_url' => 'https://github.com/nexdev-team/NexConsent/releases/tag/v1.3.5', 'body' => 'notes',
    'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/nexdev-team/NexConsent/releases/download/v1.3.5/nexconsent.zip']]])];
};
$r = nexconsent_latest_release(true);
check('updater reads latest release', $r && $r['version'] === '1.3.5' && strpos($r['package'], 'releases/download/v1.3.5/nexconsent.zip') !== false, json_encode($r));
$tr = apply_filters('pre_set_site_transient_update_plugins', (object) ['response' => [], 'no_update' => []]);
check('update offered when newer', isset($tr->response['cpapstore-consent/cpapstore-consent.php']) || count($tr->response) === 1);
$GLOBALS['remote'] = function ($url) { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v9.9.9', 'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://evil.example.com/nexconsent.zip']]])]; };
check('security: foreign download host rejected', nexconsent_latest_release(true) === null);
$GLOBALS['remote'] = function ($url) { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v9.9.9', 'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/someone-else/NexConsent/releases/download/v9.9.9/nexconsent.zip']]])]; };
check('security: other repo rejected', nexconsent_latest_release(true) === null);
$GLOBALS['remote'] = function ($url) { return ['response' => ['code' => 404], 'body' => '']; };
check('API failure -> no update, no error', nexconsent_latest_release(true) === null);
$GLOBALS['remote'] = function ($url) { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v1.0.0', 'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/nexdev-team/NexConsent/releases/download/v1.0.0/nexconsent.zip']]])]; };
nexconsent_latest_release(true);
$tr = apply_filters('pre_set_site_transient_update_plugins', (object) ['response' => [], 'no_update' => []]);
check('older release is not offered', count($tr->response) === 0 && count($tr->no_update) === 1);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit($fails ? 1 : 0);
