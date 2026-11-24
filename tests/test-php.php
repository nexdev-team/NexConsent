<?php
// Minimal WordPress stubs to exercise the plugin's own logic. Run: php tests/test-php.php
define('ABSPATH', '/tmp/'); define('MB_IN_BYTES', 1048576); define('DAY_IN_SECONDS', 86400); define('HOUR_IN_SECONDS', 3600);
define('WP_PLUGIN_DIR', '/tmp/nxc-test-plugins');
$GLOBALS['opts'] = []; $GLOBALS['filters'] = []; $GLOBALS['actions'] = []; $GLOBALS['html'] = ''; $GLOBALS['locale'] = 'el'; $GLOBALS['caps'] = ['manage_options' => true, 'unfiltered_html' => true];
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['filters'][$h][] = $cb; }
function remove_filter() {}
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['actions'][$h][] = $cb; }
function do_action($h, ...$args) { foreach ($GLOBALS['actions'][$h] ?? [] as $cb) call_user_func($cb, ...$args); }
function apply_filters($h, $v, ...$args) { foreach ($GLOBALS['filters'][$h] ?? [] as $cb) $v = $cb === '__return_true' ? true : call_user_func($cb, $v, ...$args); return $v; }
function add_shortcode() {} function register_activation_hook() {} function register_setting() {} function plugin_basename($f) { return 'nexconsent/nexconsent.php'; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; }
function home_url($p = '') { return 'https://shop.example.com' . $p; }
function wp_parse_args($a, $d) { return array_merge($d, (array) $a); }
function get_locale() { return $GLOBALS['locale']; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); } function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function esc_url_raw($s) { return filter_var($s, FILTER_VALIDATE_URL) ? $s : ''; } function esc_url($u) { return $u; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html__($s) { return $s; } function __($s) { return $s; } function esc_html_e($s) { echo $s; } function esc_attr_e($s) { echo $s; }
function add_settings_error() {} function add_query_arg($k, $v, $u) { return $u . '?' . $k . '=' . $v; }
function wp_remote_get($u, $a = []) { if (strpos($u, 'api.github.com') !== false) return ($GLOBALS['remote'])($u); return ['body' => $GLOBALS['html']]; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? 200; } function wp_kses_post($s) { return $s; }
function get_bloginfo() { return '7.1'; } function admin_url($p = '') { return 'https://shop.example.com/wp-admin/' . $p; } function wp_nonce_url($u) { return $u; } function is_wp_error($x) { return false; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function get_page_by_path($s) { return $s === 'cookie-policy' ? (object) ['post_status' => 'publish'] : null; }
function get_permalink($p) { return 'https://shop.example.com/cookie-policy/'; }
function get_privacy_policy_url() { return 'https://shop.example.com/privacy-policy/'; }
function get_theme_mod($k, $d = '') { return $GLOBALS['theme_mod'][$k] ?? $d; }
function get_site_transient($k) { return $GLOBALS['st'][$k] ?? false; } function set_site_transient($k, $v, $e = 0) { $GLOBALS['st'][$k] = $v; }
function plugins_url($p = '', $f = '') { return 'https://shop.example.com/wp-content/plugins/nexconsent/' . $p; }
function wp_doing_ajax() { return false; } function wp_doing_cron() { return false; } function is_admin() { return $GLOBALS['is_admin'] ?? true; }
function current_user_can($c) { return !empty($GLOBALS['caps'][$c]); } function load_plugin_textdomain() {}
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_add_inline_style() {} function wp_add_inline_script() {} function wp_script_is() { return false; }
function checked($a, $b, $e = true) { return $a == $b ? 'checked' : ''; } function selected($a, $b, $e = true) { return $a == $b ? 'selected' : ''; }
function get_transient($k) { return $GLOBALS['transients'][$k] ?? false; } function set_transient($k, $v, $e = 0) { $GLOBALS['transients'][$k] = $v; } function delete_transient($k) { unset($GLOBALS['transients'][$k]); }
function settings_errors() {} function settings_fields() {} function wp_nonce_field() {} function submit_button($t = '') { echo '<button>' . $t . '</button>'; }
function wp_kses($s) { return $s; } function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
class MO {
    private $e = [];
    function import_from_file($f) {
        $b = file_get_contents($f); $h = unpack('V7', substr($b, 0, 28)); $n = $h[3];
        // Same checks as WordPress's own MO reader (wp-includes/pomo/mo.php).
        if ($h[1] !== 0x950412de || $h[2] !== 0 || $h[5] - $h[4] !== 8 * $n || $h[7] - $h[5] !== 8 * $n) return false;
        for ($i = 0; $i < $n; $i++) {
            $k = unpack('V2', substr($b, $h[4] + 8 * $i, 8)); $v = unpack('V2', substr($b, $h[5] + 8 * $i, 8));
            $this->e[substr($b, $k[2], $k[1])] = substr($b, $v[2], $v[1]);
        }
        return true;
    }
    function translate($s) { return ($this->e[$s] ?? '') !== '' ? $this->e[$s] : $s; }
}
function translate($text, $domain) {
    static $mo = [];
    $file = dirname(__DIR__) . '/languages/nexconsent-' . $GLOBALS['locale'] . '.mo';
    if (!is_readable($file)) return $text;
    if (!isset($mo[$file])) { $mo[$file] = new MO(); $mo[$file]->import_from_file($file); }
    return $mo[$file]->translate($text);
}
if (!getenv('NO_WOO')) { eval('class WooCommerce {}'); }

require dirname(__DIR__) . '/nexconsent.php';
$fails = 0;
function check($n, $c, $x = '') { global $fails; echo ($c ? 'PASS ' : 'FAIL ') . $n . ($x !== '' ? '  ' . $x : '') . "\n"; if (!$c) $fails++; }
function set_opts(array $o) { $GLOBALS['opts'] = ['nexconsent_options' => nexconsent_clean($o + ['cookie_name' => 'nexconsent'])]; }
function footer() { ob_start(); foreach ($GLOBALS['actions']['wp_footer'] as $cb) $cb(); return ob_get_clean(); }

if (getenv('NO_WOO')) {
    set_opts(['ga_id' => 'G-ABCDEF1234']); $GLOBALS['locale'] = 'el';
    $t = nexconsent_strings();
    check('site without shop: no basket in the message', strpos($t['body'], 'καλάθι') === false && strpos($t['body'], 'site') !== false, $t['body']);
    exit($fails ? 1 : 0);
}

/* 1. Upgrade from 1.x keeps every visitor's consent (same cookie name and version). */
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy-cases.json'), true);
foreach ($cases as $i => $c) {
    $GLOBALS['opts'] = $c['opts']; $GLOBALS['locale'] = $c['locale'];
    $live = nexconsent_config(); // before migration: read straight from cpc_options
    nexconsent_maybe_upgrade();
    $after = nexconsent_config();
    check("legacy case $i: version identical to 1.3.3", $live['version'] === $c['version'] && $after['version'] === $c['version'], $live['version'] . ' / ' . $after['version'] . ' expected ' . $c['version']);
    check("legacy case $i: same cookie name, legacy mode on", $after['cookie'] === 'cpapstore_consent' && $after['legacy'] === true);
}
$GLOBALS['opts'] = ['cpc_options' => ['gtm_id' => 'GTM-ABC1234', 'language' => 'bg', 'reject_button' => 1]];
nexconsent_maybe_upgrade();
$m = $GLOBALS['opts']['nexconsent_options'];
check('migration: settings copied, bg -> bg_BG, reopen off', $m['gtm_id'] === 'GTM-ABC1234' && $m['language'] === 'bg_BG' && $m['reject_button'] === 1 && $m['reopen_button'] === 0 && $m['gtm_analytics'] === 1);
check('migration: version stored, old options kept for rollback', $GLOBALS['opts']['nexconsent_version'] === NEXCONSENT_VERSION && isset($GLOBALS['opts']['cpc_options']));
check('migration: page cache purge scheduled', in_array('nexconsent_purge_page_cache', $GLOBALS['actions']['wp_loaded'] ?? [], true));
@mkdir(WP_PLUGIN_DIR . '/cpapstore-consent/assets', 0777, true);
file_put_contents(WP_PLUGIN_DIR . '/cpapstore-consent/assets/consent.css', '.cpc-actions button{background:#196c3b}');
check('migration: green site without colour setting stays green', nexconsent_from_legacy(['gtm_id' => 'GTM-ABC1234'])['theme'] === 'green');
unlink(WP_PLUGIN_DIR . '/cpapstore-consent/assets/consent.css');
check('migration: other sites without colour setting are blue', nexconsent_from_legacy(['gtm_id' => 'GTM-ABC1234'])['theme'] === 'blue');
check('migration: chosen colour kept', nexconsent_from_legacy(['theme' => 'custom', 'accent' => '#003479'])['accent'] === '#003479');

/* 2. Fresh install */
$GLOBALS['opts'] = [];
$o = nexconsent_options();
check('fresh install: own cookie name, no legacy, reject + reopen buttons on', $o['cookie_name'] === 'nexconsent' && !$o['legacy'] && $o['reject_button'] && $o['reopen_button']);
check('fresh install without tracking: no banner', nexconsent_categories() === []);

/* 3. Languages and custom texts */
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'gtm_marketing' => 1]);
$GLOBALS['locale'] = 'el'; check('auto: Greek site gets Greek', nexconsent_strings()['accept'] === 'Αποδοχή όλων');
$GLOBALS['locale'] = 'bg_BG'; check('auto: Bulgarian site gets Bulgarian', nexconsent_strings()['accept'] === 'Приемам всички');
$GLOBALS['locale'] = 'de_DE'; check('auto: other languages fall back to English', nexconsent_strings()['accept'] === 'Accept all');
$v_de = nexconsent_config()['version']; $GLOBALS['locale'] = 'el'; $v_el = nexconsent_config()['version'];
check('language never changes the consent version', $v_de === $v_el);
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'language' => 'bg_BG']);
check('fixed language reads the bundled file', nexconsent_strings()['reject'] === 'Отказвам незадължителните');
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'language' => 'xx_YY']);
check('unknown language falls back to auto', nexconsent_options()['language'] === 'auto');
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'title' => 'Cookies <b>here</b>', 'accept' => 'OK!']);
$t = nexconsent_strings();
check('custom texts override, tags stripped', $t['title'] === 'Cookies here' && $t['accept'] === 'OK!' && $t['reject'] === 'Απόρριψη προαιρετικών', json_encode([$t['title'], $t['accept']]));
check('admin placeholders show the defaults', nexconsent_strings(false)['accept'] === 'Αποδοχή όλων');

/* 4. Custom services and scripts */
set_opts(['services_marketing' => "Meta Pixel | _fbp, _fbc\n bad line ; rm -rf | <x>, _ok*\n\n_li_*", 'services_analytics' => 'Microsoft Clarity | _clck, _clsk']);
$services = nexconsent_services();
$custom = array_values(array_filter($services, function ($s) { return strpos($s['id'], 'custom-') === 0; }));
check('custom services parsed', count($custom) === 4 && $custom[0]['provider'] === 'Microsoft Clarity' && $custom[1]['cookies'] === ['_fbp', '_fbc']);
check('unsafe cookie names dropped', $custom[2]['cookies'] === ['_ok*'], json_encode($custom[2]['cookies']));
check('line without provider -> "Other services"', $custom[3]['provider'] === 'Άλλες υπηρεσίες' && $custom[3]['sig_provider'] === 'Other services');
check('custom services enable both categories', nexconsent_categories() === ['analytics', 'marketing']);
$p = nexconsent_config()['patterns'];
check('custom cookies removed on withdrawal', in_array('_fbp', $p['marketing'], true) && in_array('_clck', $p['analytics'], true));
$v1 = nexconsent_config()['version'];
set_opts(['services_marketing' => "Meta Pixel | _fbp, _fbc\n_ok*\n_li_*", 'services_analytics' => 'Microsoft Clarity | _clck, _clsk, CLID']);
check('adding a service asks visitors again', nexconsent_config()['version'] !== $v1);

$snippet = "<!-- Meta Pixel -->\n<script>!function(f,b,e,v,n,t,s){}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','123');</script>\n<noscript><img height=\"1\" src=\"https://www.facebook.com/tr?id=123&ev=PageView&noscript=1\"/></noscript>\n<script type=\"application/ld+json\">{}</script><script async src=\"https://x.example.com/a.js\" id=\"xa\"></script><img src=\"https://evil/beacon\">";
$kept = nexconsent_scripts_text($snippet);
check('scripts: noscript pixel and images dropped', strpos($kept, 'noscript') === false && strpos($kept, '<img') === false && substr_count($kept, '<script') === 3);
$out = nexconsent_blocked_scripts($kept, 'marketing');
check('scripts: become inert text/plain', substr_count($out, 'type="text/plain" data-nxc-category="marketing"') === 2 && strpos($out, 'ld+json') === false);
check('scripts: external src moved to data-nxc-src, id kept', strpos($out, 'data-nxc-src="https://x.example.com/a.js" data-nxc-id="xa"') !== false && strpos($out, ' src=') === false);
set_opts(['scripts_marketing' => '<script>console.log(1)</script>']);
check('scripts alone enable their category', nexconsent_categories() === ['marketing']);

$GLOBALS['opts']['nexconsent_options']['scripts_marketing'] = '<script>keep()</script>';
$GLOBALS['caps']['unfiltered_html'] = false;
$s = nexconsent_sanitize(['scripts_marketing' => '<script>evil()</script>', 'gtm_id' => 'GTM-ABC1234']);
check('security: without unfiltered_html scripts cannot change', $s['scripts_marketing'] === '<script>keep()</script>');
$GLOBALS['caps']['unfiltered_html'] = true;
check('with unfiltered_html scripts are saved', nexconsent_sanitize(['scripts_marketing' => '<script>ok()</script>'])['scripts_marketing'] === '<script>ok()</script>');
$GLOBALS['opts']['nexconsent_options']['cookie_name'] = 'cpapstore_consent'; $GLOBALS['opts']['nexconsent_options']['legacy'] = 1;
$s = nexconsent_sanitize(['cookie_name' => 'x', 'legacy' => 0]);
check('security: internal values cannot be changed from the form', $s['cookie_name'] === 'cpapstore_consent' && $s['legacy'] === 1);
check('unchecked boxes are saved as off', nexconsent_sanitize([])['reject_button'] === 0);

/* 5. Branding */
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1]);
$v1 = nexconsent_config()['version'];
add_filter('nexconsent_branding', function ($b) { $b['name'] = 'Agency Cookies'; $b['credit'] = false; return $b; });
check('branding: name used in the cookie table', nexconsent_services()[0]['provider'] === 'Agency Cookies' && nexconsent_is_rebranded());
check('branding never changes the consent version', nexconsent_config()['version'] === $v1);
$plugins = apply_filters('all_plugins', ['nexconsent/nexconsent.php' => ['Name' => 'NexConsent', 'Author' => 'NexDev']]);
check('branding: plugin list renamed', $plugins['nexconsent/nexconsent.php']['Name'] === 'Agency Cookies');
$GLOBALS['filters']['nexconsent_branding'] = [];

/* 6. Front-end markup */
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'gtm_marketing' => 1, 'scripts_marketing' => '<script>fbq()</script>', 'position' => 'box']);
$GLOBALS['locale'] = 'el';
$html = footer();
check('markup: box position, reject and reopen buttons', strpos($html, 'nxc nxc--box') !== false && strpos($html, 'data-nxc="reject"') !== false && strpos($html, 'id="nxc-reopen"') !== false);
check('markup: privacy policy linked when no cookie policy is set', strpos($html, 'https://shop.example.com/privacy-policy/') !== false);
check('markup: blocked script printed', strpos($html, '<script type="text/plain" data-nxc-category="marketing">fbq()</script>') !== false);
check('markup: no trace of the old name', stripos($html, 'cpc') === false && stripos($html, 'cpap') === false);
$GLOBALS['opts']['nexconsent_options']['title'] = '"><script>alert(1)</script>';
check('security: custom text is escaped', strpos(footer(), '<script>alert(1)') === false);

/* 7. Order attribution bridge */
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'gtm_marketing' => 1, 'bridge' => 'auto']);
check('auto without WP Consent API -> builtin', nexconsent_bridge() === 'builtin');
check('builtin: server allowTracking forced false', apply_filters('wc_order_attribution_allow_tracking', true) === false);
$v_builtin = nexconsent_config()['version'];
if (true) { function wp_set_consent() {} }
check('auto with WP Consent API -> wpca', nexconsent_bridge() === 'wpca');
check('wpca: server filter untouched', apply_filters('wc_order_attribution_allow_tracking', true) === true);
check('switching bridge keeps consents valid', nexconsent_config()['version'] === $v_builtin);
check('sbjs_* in marketing cleanup', in_array('sbjs_*', nexconsent_config()['patterns']['marketing'], true));

/* 8. Detection */
$GLOBALS['opts'] = ['active_plugins' => ['woo-mailerlite/woo-mailerlite.php']];
$GLOBALS['html'] = '<script id="nxc-config" type="application/json">{"gtm":"GTM-OLDOLD1"}</script><script type="text/plain" data-nxc-category="marketing">fbq("init","1")</script><script>(function(w,d,s,l,i){})(window,document,"script","dataLayer","GTM-NTPKG5T");</script><script src="https://www.googletagmanager.com/gtag/js?id=G-ZHM786SQ0S"></script><script src="https://cdn-cookieyes.com/x.js"></script><script src="https://www.clarity.ms/tag/abc"></script>';
$f = nexconsent_detect();
check('detect GTM (ignores own config)', $f['gtm'] === ['GTM-NTPKG5T'], json_encode($f['gtm']));
check('detect GA4, policy page, MailerLite, CookieYes', $f['ga'] === ['G-ZHM786SQ0S'] && $f['policy_url'] === 'https://shop.example.com/cookie-policy/' && $f['mailerlite'] === 1 && $f['other_banners'] === ['CookieYes']);
check('detect trackers loading before consent (not the ones it blocks)', $f['unblocked'] === ['Microsoft Clarity'], json_encode($f['unblocked']));
$o = nexconsent_clean(nexconsent_detected_options($f, true));
check('detected options: GTM wins, both services on', $o['gtm_id'] === 'GTM-NTPKG5T' && $o['ga_id'] === '' && $o['gtm_analytics'] === 1 && $o['gtm_marketing'] === 1);
$GLOBALS['html'] = '<p>Great! GTM-EVIL123 G-EVILEVIL1</p><div>googletagmanager.com/gtm.js?id=GTM-EVIL999</div><script>gtag("config","G-GOOD1234");</script>';
$f2 = nexconsent_detect();
check('security: IDs in page text ignored', $f2['gtm'] === [] && $f2['ga'] === ['G-GOOD1234']);
$GLOBALS['theme_mod'] = ['primary_color' => '#29A643'];
check('theme colour from common Customizer settings', nexconsent_theme_colour() === '#29a643');
$GLOBALS['opts'] = [];
$f = nexconsent_detect();
check('re-detect keeps colours; fresh install uses theme colour', nexconsent_detected_options($f)['theme'] === 'blue' && nexconsent_detected_options($f, true)['accent'] === '#29a643');

/* 8b. Google tag gateway */
$GLOBALS['opts'] = [];
$GLOBALS['html'] = '<script async src="/ri8k/"></script><script>(function(w,i,g){w[g]=w[g]||[];w[g].push(i)})(window,\'GTM-NGKVVX5\',\'google_tags_first_party\');</script><script src="https://www.googletagmanager.com/gtm.js?id=GTM-NGKVVX5&gtg_health=1"></script>';
$f = nexconsent_detect();
$o = nexconsent_clean(nexconsent_detected_options($f));
check('gateway detected: GTM found, loader external', $f['gateway'] === true && $o['gtm_id'] === 'GTM-NGKVVX5' && $o['gtm_loader'] === 'external');
$GLOBALS['opts'] = ['nexconsent_options' => $o];
check('config tells the browser not to load GTM', nexconsent_config()['gtmExternal'] === true);
$v_ext = nexconsent_config()['version']; $GLOBALS['opts']['nexconsent_options']['gtm_loader'] = 'plugin';
check('loader choice never changes the consent version', nexconsent_config()['version'] === $v_ext && nexconsent_config()['gtmExternal'] === false);
check('loader value sanitised', nexconsent_clean(['gtm_loader' => '<x>'])['gtm_loader'] === 'plugin');
$GLOBALS['is_admin'] = false; ob_start(); foreach ($GLOBALS['actions']['wp_head'] as $cb) $cb(); $head = ob_get_clean(); $GLOBALS['is_admin'] = true;
check('Consent Mode default printed inline before config and core', strpos($head, 'id="nxc-default"') !== false && strpos($head, 'id="nxc-default"') < strpos($head, 'id="nxc-config"') && strpos($head, "wait_for_update:500") !== false);

/* 9. GTM package */
set_opts(['gtm_id' => 'GTM-NTPKG5T', 'gtm_analytics' => 1]);
$cv = nexconsent_gtm_package()['containerVersion'];
check('GTM package: 2 variables, 2 triggers, container ID', count($cv['variable']) === 2 && count($cv['trigger']) === 2 && $cv['container']['publicId'] === 'GTM-NTPKG5T');
check('GTM package: triggers use existing variables and new events', $cv['trigger'][1]['filter'][0]['parameter'][0]['value'] === '{{' . $cv['variable'][1]['name'] . '}}' && strpos(json_encode($cv), 'nexconsent_(ready|update)') !== false);
check('GTM package carries no brand', stripos(json_encode($cv), 'nexdev') === false && stripos(json_encode($cv), 'cpc') === false);

/* 10. Colours */
set_opts(['theme' => 'green']);
check('green preset', nexconsent_palette()['accent'] === '#196c3b');
foreach (['#1e73be', '#29a643', '#ffcc00', '#e91e63', '#000000', '#ffffff'] as $hex) {
    set_opts(['theme' => 'custom', 'accent' => $hex]); $pal = nexconsent_palette();
    $ok = nexconsent_contrast($pal['accent'], '#ffffff') >= 4.5 && nexconsent_contrast($pal['text'], '#ffffff') >= 7;
    foreach ($pal as $v) $ok = $ok && preg_match('/^#[0-9a-f]{3,8}$/', $v);
    check("custom $hex: readable palette", (bool) $ok);
}
check('palette CSS only contains safe values', preg_match('/^\.nxc,[^{]+\{(--nxc-[a-z-]+:#[0-9a-f]{3,8};)+\}$/', nexconsent_palette_css()) === 1);
check('sanitize rejects bad colour / theme / position', nexconsent_clean(['theme' => '<x>', 'accent' => 'red;}body{x', 'position' => 'top'])['accent'] === '#1e73be' && nexconsent_clean(['theme' => '<x>'])['theme'] === 'blue' && nexconsent_clean(['position' => 'x'])['position'] === 'bar');

/* 11. Settings page renders with every section and escapes stored values */
set_opts(['gtm_id' => 'GTM-ABC1234', 'gtm_analytics' => 1, 'services_marketing' => 'Meta | _fbp', 'scripts_marketing' => '<script>x("</textarea><b>")</script>']);
$GLOBALS['transients']['nexconsent_setup_report'] = ['gtm' => ['GTM-ABC1234'], 'ga' => [], 'policy_url' => '', 'mailerlite' => 0, 'other_banners' => ['CookieYes'], 'unblocked' => ['Meta Pixel'], 'scan_ok' => true, 'accent' => '#29a643'];
ob_start(); nexconsent_settings_page(); $page = ob_get_clean();
check('settings page renders all sections', substr_count($page, '<h2 class="title">') === 4 && strpos($page, 'name="nexconsent_options[scripts_marketing]"') !== false && strpos($page, 'Meta Pixel') !== false);
check('security: stored script cannot break out of its textarea', strpos($page, '</textarea><b>') === false);
$GLOBALS['caps']['unfiltered_html'] = false; ob_start(); nexconsent_settings_page(); $page = ob_get_clean(); $GLOBALS['caps']['unfiltered_html'] = true;
check('script fields read-only without unfiltered_html', (bool) preg_match('/name="nexconsent_options\[scripts_marketing\]" readonly/', $page));

/* 12. Updater */
$GLOBALS['remote'] = function () { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v9.1.0', 'html_url' => 'https://github.com/nexdev-team/NexConsent/releases/tag/v9.1.0', 'body' => 'notes',
    'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/nexdev-team/NexConsent/releases/download/v9.1.0/nexconsent.zip']]])]; };
$r = nexconsent_latest_release(true);
check('updater reads latest release', $r && $r['version'] === '9.1.0');
$tr = apply_filters('pre_set_site_transient_update_plugins', (object) ['response' => [], 'no_update' => []]);
check('update offered when newer', isset($tr->response['nexconsent/nexconsent.php']));
$GLOBALS['remote'] = function () { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v9.9.9', 'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/someone-else/NexConsent/releases/download/v9.9.9/nexconsent.zip']]])]; };
check('security: package from another repository rejected', nexconsent_latest_release(true) === null);
$GLOBALS['remote'] = function () { return ['response' => ['code' => 200], 'body' => json_encode(['tag_name' => 'v1.0.0', 'assets' => [['name' => 'nexconsent.zip', 'browser_download_url' => 'https://github.com/nexdev-team/NexConsent/releases/download/v1.0.0/nexconsent.zip']]])]; };
nexconsent_latest_release(true);
$tr = apply_filters('pre_set_site_transient_update_plugins', (object) ['response' => [], 'no_update' => []]);
check('older release not offered', count($tr->response) === 0);

echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
$sub = 0; passthru('NO_WOO=1 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__), $sub);
exit($fails || $sub ? 1 : 0);
