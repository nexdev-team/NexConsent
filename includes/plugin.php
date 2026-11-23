<?php
// NexConsent core. Loaded by nexconsent.php.
defined('ABSPATH') || exit;
const CPC_COOKIE = 'cpapstore_consent'; // Kept from CPAPstore Consent so existing choices stay valid.
add_filter('wp_get_consent_type', function ($value) { return cpc_categories() ? 'optin' : $value; });
add_filter('wp_consent_api_cookie_expiration', function ($value) { return cpc_categories() ? 180 : $value; });
add_filter('wp_has_consent', 'cpc_wp_has_consent', 100, 2);
add_filter('wp_consent_api_registered_' . plugin_basename(NEXCONSENT_FILE), '__return_true');

function cpc_options() {
    $saved = get_option('cpc_options', []);
    // Preserve explicitly configured 1.0.x installations; new saves always contain these keys.
    $legacy_gtm = !empty($saved['gtm_id']) && !array_key_exists('gtm_analytics', $saved);
    $legacy_ml = !empty($saved['external_marketing']) && !array_key_exists('mailerlite', $saved)
        && in_array('woo-mailerlite/woo-mailerlite.php', get_option('active_plugins', []), true);
    return wp_parse_args($saved, [
        'language' => 'auto', 'gtm_id' => '', 'ga_id' => '', 'revision' => '1',
        'policy_url' => home_url('/cookie-policy/'), 'external_analytics' => 0,
        'external_marketing' => 0,
        'gtm_analytics' => $legacy_gtm ? 1 : 0, 'gtm_marketing' => $legacy_gtm ? 1 : 0,
        'mailerlite' => $legacy_ml ? 1 : 0,
        'bridge' => 'auto',
        'theme' => 'blue', 'accent' => '#1e73be',
        'reject_button' => 0,
    ]);
}

/* ---------------------------------------------------------------------------
 * Colours. Presets reproduce the existing CPAPmask (blue) and CPAPstore (green)
 * banners exactly; "custom" builds a full palette from one brand colour.
 * ------------------------------------------------------------------------- */
function cpc_palette_presets() {
    return [
        'blue' => ['text' => '#1b344a', 'border' => '#dbe6ee', 'shadow' => '#16365326', 'link' => '#176496', 'accent' => '#1e73be',
            'accent-hover' => '#176496', 'on-accent' => '#fff', 'quiet-border' => '#b8cad9', 'quiet-hover' => '#edf5fb', 'reopen-border' => '#c3d4e1',
            'reopen-shadow' => '#16365315', 'overlay' => '#102b45b3', 'close-bg' => '#edf3f8', 'cat-border' => '#e1e9ef', 'muted' => '#506578', 'locked-bg' => '#e9f3fb'],
        'green' => ['text' => '#15332b', 'border' => '#dae7df', 'shadow' => '#12302626', 'link' => '#146638', 'accent' => '#196c3b',
            'accent-hover' => '#12582f', 'on-accent' => '#fff', 'quiet-border' => '#b8cbbf', 'quiet-hover' => '#edf5ef', 'reopen-border' => '#c3d5c8',
            'reopen-shadow' => '#12302615', 'overlay' => '#08271ab3', 'close-bg' => '#eef4ef', 'cat-border' => '#e2ebe5', 'muted' => '#50665b', 'locked-bg' => '#eaf4ec'],
    ];
}
function cpc_hex($hex) {
    $hex = strtolower(trim((string) $hex));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $hex, $m)) $hex = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : '';
}
function cpc_hsl($hex) {
    $r = hexdec(substr($hex, 1, 2)) / 255; $g = hexdec(substr($hex, 3, 2)) / 255; $b = hexdec(substr($hex, 5, 2)) / 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2; $d = $max - $min; $h = 0; $s = 0;
    if ($d > 0) {
        $s = $d / (1 - abs(2 * $l - 1));
        if ($max === $r) $h = 60 * fmod((($g - $b) / $d), 6); elseif ($max === $g) $h = 60 * ((($b - $r) / $d) + 2); else $h = 60 * ((($r - $g) / $d) + 4);
    }
    return [$h < 0 ? $h + 360 : $h, $s, $l];
}
function cpc_from_hsl($h, $s, $l) {
    $s = max(0, min(1, $s)); $l = max(0, min(1, $l));
    $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
    $seg = [[$c, $x, 0], [$x, $c, 0], [0, $c, $x], [0, $x, $c], [$x, 0, $c], [$c, 0, $x]][(int) floor($h / 60) % 6];
    return sprintf('#%02x%02x%02x', round(($seg[0] + $m) * 255), round(($seg[1] + $m) * 255), round(($seg[2] + $m) * 255));
}
function cpc_luminance($hex) {
    $f = function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); };
    return 0.2126 * $f(hexdec(substr($hex, 1, 2))) + 0.7152 * $f(hexdec(substr($hex, 3, 2))) + 0.0722 * $f(hexdec(substr($hex, 5, 2)));
}
function cpc_contrast($a, $b) { $x = cpc_luminance($a); $y = cpc_luminance($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
function cpc_palette() {
    $o = cpc_options(); $presets = cpc_palette_presets();
    if ($o['theme'] !== 'custom' || !cpc_hex($o['accent'])) return $presets[$o['theme']] ?? $presets['blue'];
    list($h, $s, $l) = cpc_hsl(cpc_hex($o['accent']));
    // Darken until white text on the button meets WCAG AA (4.5:1).
    $accent = cpc_hex($o['accent']);
    while (cpc_contrast($accent, '#ffffff') < 4.5 && $l > 0.05) { $l -= 0.02; $accent = cpc_from_hsl($h, $s, $l); }
    $tint = function ($sat, $light) use ($h, $s) { return cpc_from_hsl($h, min($s, $sat), $light); };
    $text = $tint(0.45, 0.15);
    return ['text' => $text, 'border' => $tint(0.30, 0.89), 'shadow' => $tint(0.55, 0.15) . '26', 'link' => cpc_from_hsl($h, $s, max(0.05, $l - 0.06)),
        'accent' => $accent, 'accent-hover' => cpc_from_hsl($h, $s, max(0.05, $l - 0.08)), 'on-accent' => '#fff',
        'quiet-border' => $tint(0.25, 0.79), 'quiet-hover' => $tint(0.45, 0.95), 'reopen-border' => $tint(0.25, 0.82),
        'reopen-shadow' => $tint(0.55, 0.15) . '15', 'overlay' => $tint(0.60, 0.12) . 'b3', 'close-bg' => $tint(0.30, 0.95),
        'cat-border' => $tint(0.25, 0.91), 'muted' => $tint(0.12, 0.40), 'locked-bg' => $tint(0.55, 0.94)];
}
// Sites coming from CPAPstore Consent have no colour setting yet: keep the colours their old
// stylesheet had (green or blue) instead of switching everyone to blue. Runs once per site.
function cpc_migrate_legacy_colours() {
    $saved = get_option('cpc_options', null);
    if (!is_array($saved) || isset($saved['theme'])) return;
    $theme = 'blue';
    $css = WP_PLUGIN_DIR . '/cpapstore-consent/assets/consent.css';
    if (is_readable($css) && strpos((string) file_get_contents($css), '#196c3b') !== false) $theme = 'green';
    $saved['theme'] = $theme;
    $saved['accent'] = $theme === 'green' ? '#196c3b' : '#1e73be';
    update_option('cpc_options', $saved);
}
add_action('init', 'cpc_migrate_legacy_colours', 1);

function cpc_palette_css() {
    $vars = '';
    foreach (cpc_palette() as $k => $v) if (preg_match('/^#[0-9a-f]{3,8}$/i', $v)) $vars .= '--cpc-' . $k . ':' . $v . ';';
    return '.cpc,.cpc-overlay,.cpc-reopen,.cpc-settings-link{' . $vars . '}';
}

/* ---------------------------------------------------------------------------
 * Consent bridge for WooCommerce order attribution (sourcebuster sbjs_* cookies).
 * auto    = WP Consent API when installed and active, otherwise built-in.
 * wpca    = always through WP Consent API (falls back to built-in if it is missing).
 * builtin = this plugin switches WooCommerce order attribution on/off itself.
 * ------------------------------------------------------------------------- */
function cpc_wpca_available() {
    return class_exists('WP_CONSENT_API') || function_exists('wp_set_consent');
}
function cpc_bridge() {
    $mode = cpc_options()['bridge'];
    if ($mode === 'builtin') return 'builtin';
    return cpc_wpca_available() ? 'wpca' : 'builtin';
}
function cpc_order_attribution_enabled() {
    return class_exists('WooCommerce') && get_option('woocommerce_feature_order_attribution_enabled', 'yes') === 'yes';
}
// Built-in mode: pages are cached for everyone, so the server always starts with tracking off.
// consent-core.js switches it on in the browser only after marketing consent.
add_filter('wc_order_attribution_allow_tracking', function ($allow) {
    return (cpc_categories() && cpc_bridge() === 'builtin') ? false : $allow;
}, 999);

function cpc_copy($dictionary = '') {
    $all = [
        'bg' => [
            'title' => 'Вашият избор за бисквитките',
            'body' => 'Необходимите бисквитки поддържат сайта и количката. Само с Ваше съгласие използваме бисквитки за статистика и маркетинг.',
            'accept' => 'Приемам всички', 'reject' => 'Отказвам незадължителните',
            'settings' => 'Настройки', 'save' => 'Запазвам избора', 'close' => 'Затвори',
            'policy' => 'Политика за бисквитки', 'always' => 'Винаги активни',
            'necessary' => 'Необходими', 'analytics' => 'Статистика', 'marketing' => 'Маркетинг',
            'necessary_desc' => 'Запазват Вашия избор, количката, сесията и сигурността на сайта.',
            'analytics_desc' => 'Помагат ни да разберем как се използва сайтът и да го подобрим.',
            'marketing_desc' => 'Измерват ефективността на рекламите и позволяват персонализирана реклама.',
            'mailerlite_purpose' => 'Показва формуляри и предложения за бюлетини и маркетингови кампании.',
            'change' => 'Настройки за бисквитки', 'error' => 'Изборът не може да бъде запазен. Незадължителните услуги остават изключени.',
            'cookie' => 'Бисквитка / услуга', 'purpose' => 'Цел', 'duration' => 'Продължителност',
            'choice_purpose' => 'Запазва категориите, датата и версията на Вашия избор.',
            'choice_duration' => '180 дни', 'store_duration' => 'Според настройките на магазина',
            'service_duration' => 'Според настройките на съответната услуга',
            'custom' => 'Допълнителни услуги — вижте описанието в политиката',
        ],
        'el' => [
            'title' => 'Η επιλογή σου για τα cookies',
            'body' => 'Τα αναγκαία cookies υποστηρίζουν το site και το καλάθι. Μόνο με τη συγκατάθεσή σου χρησιμοποιούμε cookies στατιστικών και marketing.',
            'accept' => 'Αποδοχή όλων', 'reject' => 'Απόρριψη προαιρετικών', 'settings' => 'Ρυθμίσεις',
            'save' => 'Αποθήκευση επιλογών', 'close' => 'Κλείσιμο', 'policy' => 'Πολιτική cookies',
            'always' => 'Πάντα ενεργά', 'necessary' => 'Αναγκαία', 'analytics' => 'Στατιστικά',
            'marketing' => 'Marketing', 'necessary_desc' => 'Αποθηκεύουν την επιλογή σου και υποστηρίζουν το καλάθι, τη συνεδρία και την ασφάλεια του site.',
            'analytics_desc' => 'Μας βοηθούν να κατανοούμε τη χρήση του site και να το βελτιώνουμε.',
            'marketing_desc' => 'Μετρούν την απόδοση διαφημίσεων και επιτρέπουν εξατομικευμένη διαφήμιση.',
            'mailerlite_purpose' => 'Εμφανίζει φόρμες και προτάσεις για ενημερωτικά δελτία και καμπάνιες marketing.',
            'change' => 'Ρυθμίσεις cookies', 'error' => 'Δεν αποθηκεύτηκε η επιλογή. Οι προαιρετικές υπηρεσίες παραμένουν ανενεργές.',
            'cookie' => 'Cookie / υπηρεσία', 'purpose' => 'Σκοπός', 'duration' => 'Διάρκεια',
            'choice_purpose' => 'Αποθηκεύει τις κατηγορίες, την ημερομηνία και την έκδοση της επιλογής σου.',
            'choice_duration' => '180 ημέρες', 'store_duration' => 'Σύμφωνα με τις ρυθμίσεις του καταστήματος',
            'service_duration' => 'Σύμφωνα με τις ρυθμίσεις της υπηρεσίας',
            'custom' => 'Πρόσθετες υπηρεσίες — δες την περιγραφή στην πολιτική',
        ],
        'en' => [
            'title' => 'Your cookie choices',
            'body' => 'Necessary cookies keep the site and basket working. We use analytics and marketing cookies only with your permission.',
            'accept' => 'Accept all', 'reject' => 'Reject optional', 'settings' => 'Settings',
            'save' => 'Save choices', 'close' => 'Close', 'policy' => 'Cookie policy',
            'always' => 'Always active', 'necessary' => 'Necessary', 'analytics' => 'Analytics',
            'marketing' => 'Marketing', 'necessary_desc' => 'Remember your choices and support the basket, session and site security.',
            'analytics_desc' => 'Help us understand how the site is used and improve it.',
            'marketing_desc' => 'Measure advertising performance and allow personalized advertising.',
            'mailerlite_purpose' => 'Displays newsletter forms and offers for marketing campaigns.',
            'change' => 'Cookie settings', 'error' => 'Your choice could not be saved. Optional services remain off.',
            'cookie' => 'Cookie / service', 'purpose' => 'Purpose', 'duration' => 'Duration',
            'choice_purpose' => 'Stores the categories, date and version of your choice.',
            'choice_duration' => '180 days', 'store_duration' => 'According to store settings',
            'service_duration' => 'According to service settings',
            'custom' => 'Additional services — see the description in the policy',
        ],
    ];
    if ($dictionary === 'all') return $all;
    $language = cpc_options()['language'];
    if ($language === 'auto') {
        $language = substr((string) apply_filters('cpc_language', get_locale()), 0, 2);
    }
    return $all[$language] ?? $all['en'];
}

function cpc_valid_id($id, $kind) {
    $id = strtoupper(trim((string) $id));
    if (!preg_match($kind === 'gtm' ? '/^GTM-[A-Z0-9]+$/' : '/^G-[A-Z0-9]+$/', $id)) return '';
    return preg_match('/^(G|GTM)-X+$/', $id) ? '' : $id;
}

/** A single registry determines visible categories, cookie cleanup and policy disclosure. */
function cpc_services() {
    $o = cpc_options(); $t = cpc_copy();
    $gtm = cpc_valid_id($o['gtm_id'], 'gtm');
    $ga = $gtm ? '' : cpc_valid_id($o['ga_id'], 'ga');
    $services = [
        ['id' => 'choice', 'category' => 'necessary', 'provider' => 'NexConsent', 'cookies' => [CPC_COOKIE], 'purpose' => $t['choice_purpose'], 'duration' => $t['choice_duration']],
    ];
    if (class_exists('WooCommerce')) $services[] = [
        'id' => 'woocommerce', 'category' => 'necessary', 'provider' => 'WooCommerce',
        'cookies' => ['woocommerce_cart_hash', 'woocommerce_items_in_cart', 'wp_woocommerce_session_*'],
        'purpose' => $t['necessary_desc'], 'duration' => $t['store_duration'],
    ];
    if (($gtm && $o['gtm_analytics']) || $ga || $o['external_analytics']) $services[] = [
        'id' => 'analytics', 'category' => 'analytics', 'provider' => (($gtm && $o['gtm_analytics']) || $ga) ? 'Google Analytics / GTM' : 'Registered analytics services',
        'cookies' => (($gtm && $o['gtm_analytics']) || $ga) ? ['_ga', '_ga_*', '_gid', '_gat*'] : [], 'purpose' => $t['analytics_desc'], 'duration' => $t['service_duration'],
    ];
    if (($gtm && $o['gtm_marketing']) || $o['external_marketing']) $services[] = [
        'id' => 'marketing', 'category' => 'marketing', 'provider' => ($gtm && $o['gtm_marketing']) ? 'Google Ads / GTM' : 'Registered marketing services',
        'cookies' => ($gtm && $o['gtm_marketing']) ? ['_gcl_*', '_gac_*'] : [], 'purpose' => $t['marketing_desc'], 'duration' => $t['service_duration'],
    ];
    if ($o['mailerlite']) $services[] = [
        'id' => 'mailerlite', 'category' => 'marketing', 'provider' => 'MailerLite',
        'cookies' => ['mailerlite*', '_mailerlite*', 'ml_*'], 'purpose' => $t['mailerlite_purpose'], 'duration' => $t['service_duration'],
    ];
    if (function_exists('wp_has_consent')) $services[] = ['id' => 'wp-consent-api', 'category' => 'necessary', 'provider' => 'WordPress Consent API',
        'cookies' => [apply_filters('wp_consent_cookie_prefix', 'wp_consent') . '_*'], 'purpose' => $t['choice_purpose'], 'duration' => $t['choice_duration']];
    // Canonical built-in purposes stay independent of translated presentation copy.
    foreach ($services as &$service) $service['purpose_key'] = $service['id'] . ':1';
    unset($service);
    return apply_filters('cpc_services', $services);
}

function cpc_categories() {
    return array_values(array_intersect(['analytics', 'marketing'], array_unique(array_column(cpc_services(), 'category'))));
}

function cpc_config() {
    $o = cpc_options(); $services = cpc_services();
    $gtm = cpc_valid_id($o['gtm_id'], 'gtm');
    $ga = $gtm ? '' : cpc_valid_id($o['ga_id'], 'ga');
    $patterns = ['analytics' => [], 'marketing' => []];
    foreach ($services as $s) if (isset($patterns[$s['category']])) $patterns[$s['category']] = array_merge($patterns[$s['category']], $s['cookies']);
    // WooCommerce order attribution cookies are marketing; kept out of the signature so existing choices stay valid.
    if (cpc_order_attribution_enabled()) $patterns['marketing'][] = 'sbjs_*';
    // Language changes don't invalidate consent; changes to providers, purpose or revision do.
    $canonical = cpc_copy_for_signature();
    $signature = array_map(function ($s) use ($canonical) {
        $purpose = $s['purpose'];
        // Normalize only known translated built-in copy, so a filter changing purpose invalidates consent.
        if (isset($canonical[$s['id']]) && in_array($purpose, $canonical[$s['id']], true)) $purpose = $s['purpose_key'];
        return [$s['id'], $s['category'], $s['provider'], $s['cookies'], $purpose, $s['purpose_revision'] ?? '1'];
    }, $services);
    return [
        'cookie' => CPC_COOKIE, 'maxAge' => 180 * DAY_IN_SECONDS,
        'version' => substr(hash('sha256', wp_json_encode([$o['revision'], $gtm, $ga, $signature])), 0, 16),
        'categories' => cpc_categories(), 'gtm' => $gtm, 'ga' => $ga, 'patterns' => $patterns,
        'wpCookiePrefix' => apply_filters('wp_consent_cookie_prefix', 'wp_consent'),
        'wpApi' => function_exists('wp_has_consent'),
        'bridge' => cpc_bridge(),
    ];
}

function cpc_copy_for_signature() {
    // cpc_copy exposes its dictionary without changing any persisted/site options.
    $all = cpc_copy('all'); $out = [];
    foreach ($all as $copy) foreach (['choice' => 'choice_purpose', 'woocommerce' => 'necessary_desc',
        'analytics' => 'analytics_desc', 'marketing' => 'marketing_desc', 'mailerlite' => 'mailerlite_purpose',
        'wp-consent-api' => 'choice_purpose'] as $id => $key) $out[$id][] = $copy[$key];
    return $out;
}

function cpc_valid_record() {
    $raw = isset($_COOKIE[CPC_COOKIE]) ? stripslashes((string) $_COOKIE[CPC_COOKIE]) : '';
    $record = json_decode($raw, true);
    if (!is_array($record)) $record = json_decode(rawurldecode($raw), true);
    $config = cpc_config(); $now = microtime(true) * 1000;
    if (!is_array($record) || ($record['v'] ?? '') !== $config['version'] || !isset($record['ts'])
        || !(is_int($record['ts']) || is_float($record['ts'])) || $record['ts'] > $now || $now - $record['ts'] >= $config['maxAge'] * 1000
        || !is_bool($record['analytics'] ?? null) || !is_bool($record['marketing'] ?? null)) return null;
    return $record;
}

function cpc_wp_has_consent($value, $category) {
    if (!cpc_categories()) return $value;
    if ($category === 'functional') return true;
    $key = in_array($category, ['statistics', 'statistics-anonymous'], true) ? 'analytics' : ($category === 'marketing' ? 'marketing' : '');
    $record = cpc_valid_record();
    return $key && $record && in_array($key, cpc_categories(), true) && $record[$key] === true;
}

add_action('wp_head', function () {
    if (is_admin() || !cpc_categories()) return;
    $c = cpc_config();
    echo '<script id="cpc-config" type="application/json">' . wp_json_encode($c, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    // Synchronous local bootstrap must precede any optional scripts. No visitor-specific HTML.
    echo '<script id="cpc-bootstrap" data-no-optimize="1" data-cfasync="false" src="' . esc_url(plugins_url('assets/consent-core.js', NEXCONSENT_FILE) . '?ver=' . CPC_VERSION) . '"></script>';
}, -9999);

add_action('wp_enqueue_scripts', function () {
    if (!cpc_categories()) return;
    wp_enqueue_style('cpc-consent', plugins_url('assets/consent.css', NEXCONSENT_FILE), [], CPC_VERSION);
    wp_add_inline_style('cpc-consent', cpc_palette_css());
    wp_enqueue_script('cpc-ui', plugins_url('assets/consent-ui.js', NEXCONSENT_FILE), [], CPC_VERSION, true);
});
// Built-in bridge: give WooCommerce the visitor's choice right before order-attribution.js initialises,
// so returning visitors keep their attribution cookies instead of having them reset on every page.
add_action('wp_enqueue_scripts', function () {
    if (!cpc_categories() || cpc_bridge() !== 'builtin' || !wp_script_is('wc-order-attribution', 'enqueued')) return;
    wp_add_inline_script('wc-order-attribution', 'if(window.wc_order_attribution&&wc_order_attribution.params){wc_order_attribution.params.allowTracking=!!(window.CPAPConsent&&CPAPConsent.get().marketing);}', 'before');
}, 100);

add_action('wp_footer', function () {
    if (!cpc_categories()) return;
    $t = cpc_copy(); $o = cpc_options();
    ?>
    <section id="cpc-banner" class="cpc" role="region" aria-labelledby="cpc-title" hidden>
      <div class="cpc-inner">
        <div class="cpc-copy"><h2 id="cpc-title"><?php echo esc_html($t['title']); ?></h2>
          <p><?php echo esc_html($t['body']); ?> <a href="<?php echo esc_url($o['policy_url']); ?>"><?php echo esc_html($t['policy']); ?></a></p>
          <p class="cpc-error" role="status" hidden><?php echo esc_html($t['error']); ?></p>
        </div>
        <div class="cpc-actions<?php echo $o['reject_button'] ? ' cpc-actions--3' : ''; ?>">
          <button type="button" data-cpc="accept"><?php echo esc_html($t['accept']); ?></button>
          <?php if ($o['reject_button']) : ?><button type="button" class="cpc-quiet" data-cpc="reject"><?php echo esc_html($t['reject']); ?></button><?php endif; ?>
          <button type="button" class="cpc-quiet" data-cpc="open"><?php echo esc_html($t['settings']); ?></button>
        </div>
      </div>
    </section>
    <div id="cpc-overlay" class="cpc-overlay" hidden>
      <section class="cpc-dialog" role="dialog" aria-modal="true" aria-labelledby="cpc-dialog-title" tabindex="-1">
        <div class="cpc-dialog-head"><h2 id="cpc-dialog-title"><?php echo esc_html($t['change']); ?></h2>
          <button type="button" class="cpc-close" data-cpc="close" aria-label="<?php echo esc_attr($t['close']); ?>">×</button>
        </div>
        <p><?php echo esc_html($t['body']); ?></p>
        <div class="cpc-category"><strong><?php echo esc_html($t['necessary']); ?></strong><span class="cpc-locked"><?php echo esc_html($t['always']); ?></span><p><?php echo esc_html($t['necessary_desc']); ?></p></div>
        <?php foreach (cpc_categories() as $key) : ?>
        <div class="cpc-category"><label for="cpc-<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($t[$key]); ?></strong></label>
          <input type="checkbox" id="cpc-<?php echo esc_attr($key); ?>" data-cpc-category="<?php echo esc_attr($key); ?>" aria-describedby="cpc-<?php echo esc_attr($key); ?>-desc">
          <p id="cpc-<?php echo esc_attr($key); ?>-desc"><?php echo esc_html($t[$key . '_desc']); ?></p>
        </div>
        <?php endforeach; ?>
        <p><a href="<?php echo esc_url($o['policy_url']); ?>"><?php echo esc_html($t['policy']); ?></a></p>
        <p class="cpc-error" role="status" hidden><?php echo esc_html($t['error']); ?></p>
        <div class="cpc-actions"><button type="button" data-cpc="accept"><?php echo esc_html($t['accept']); ?></button><button type="button" class="cpc-quiet" data-cpc="save"><?php echo esc_html($t['save']); ?></button></div>
      </section>
    </div>
    <?php
}, 10); // Markup must precede WordPress's footer scripts (normally priority 20).

add_shortcode('cpapstore_cookie_settings', 'cpc_settings_shortcode');
add_shortcode('nexconsent_settings', 'cpc_settings_shortcode');
function cpc_settings_shortcode() {
    return cpc_categories() ? '<button type="button" class="cpc-settings-link" data-cpc="open">' . esc_html(cpc_copy()['change']) . '</button>' : '';
}
add_shortcode('cpapstore_cookie_table', 'cpc_cookie_table_shortcode');
add_shortcode('nexconsent_cookie_table', 'cpc_cookie_table_shortcode');
function cpc_cookie_table_shortcode() {
    $t = cpc_copy();
    $html = '<div class="cpc-table-wrap"><table><thead><tr><th>' . esc_html($t['cookie']) . '</th><th>' . esc_html($t['purpose']) . '</th><th>' . esc_html($t['duration']) . '</th></tr></thead><tbody>';
    foreach (cpc_services() as $s) $html .= '<tr><td>' . esc_html($s['provider']) . '<br>' . esc_html(implode(', ', $s['cookies'])) . '</td><td>' . esc_html($s['purpose']) . '</td><td>' . esc_html($s['duration']) . '</td></tr>';
    return $html . '</tbody></table></div>';
}

function cpc_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $out = [
        'language' => in_array($input['language'] ?? '', ['auto', 'bg', 'en', 'el'], true) ? $input['language'] : 'auto',
        'gtm_id' => cpc_valid_id($input['gtm_id'] ?? '', 'gtm'), 'ga_id' => cpc_valid_id($input['ga_id'] ?? '', 'ga'),
        'revision' => sanitize_text_field($input['revision'] ?? '1'),
        'policy_url' => esc_url_raw($input['policy_url'] ?? home_url('/cookie-policy/')),
        'external_analytics' => empty($input['external_analytics']) ? 0 : 1,
        'external_marketing' => empty($input['external_marketing']) ? 0 : 1,
        'gtm_analytics' => empty($input['gtm_analytics']) ? 0 : 1,
        'gtm_marketing' => empty($input['gtm_marketing']) ? 0 : 1,
        'mailerlite' => empty($input['mailerlite']) ? 0 : 1,
        'bridge' => in_array($input['bridge'] ?? '', ['auto', 'wpca', 'builtin'], true) ? $input['bridge'] : 'auto',
        'theme' => in_array($input['theme'] ?? '', ['blue', 'green', 'custom'], true) ? $input['theme'] : 'blue',
        'accent' => cpc_hex($input['accent'] ?? '') ?: '#1e73be',
        'reject_button' => empty($input['reject_button']) ? 0 : 1,
    ];
    if ($out['gtm_id']) $out['ga_id'] = '';
    foreach (['gtm_id', 'ga_id'] as $key) if (!empty($input[$key]) && !cpc_valid_id($input[$key], $key === 'gtm_id' ? 'gtm' : 'ga')) add_settings_error('cpc_options', $key, 'Invalid or placeholder tracking ID was removed.');
    if (!$out['policy_url']) $out['policy_url'] = home_url('/cookie-policy/');
    return $out;
}
add_action('admin_init', function () {
    register_setting('cpc_settings', 'cpc_options', ['type' => 'array', 'sanitize_callback' => 'cpc_sanitize', 'default' => []]);
});
add_action('admin_menu', function () {
    add_options_page('NexConsent', 'NexConsent', 'manage_options', 'nexconsent', 'cpc_settings_page');
});
add_filter('plugin_action_links_' . plugin_basename(NEXCONSENT_FILE), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=nexconsent')) . '">Settings</a>');
    return $links;
});

/* ---------------------------------------------------------------------------
 * Quick setup: detect what the site already uses, so a fresh install works
 * after one click (or with no click at all on first activation).
 * ------------------------------------------------------------------------- */
function cpc_detect() {
    $found = ['gtm' => [], 'ga' => [], 'policy_url' => '', 'mailerlite' => 0, 'other_banners' => [], 'scan_ok' => false, 'accent' => ''];
    // Brand colour from the theme (Flatsome "Primary color"), used only to colour the banner.
    $found['accent'] = cpc_hex(get_theme_mod('color_primary', ''));
    // Loopback request to this site only; same TLS policy WordPress core uses for loopbacks.
    $resp = wp_remote_get(add_query_arg('cpc_scan', time(), home_url('/')), [
        'timeout' => 15, 'redirection' => 3, 'limit_response_size' => 3 * MB_IN_BYTES,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
    ]);
    $html = is_wp_error($resp) ? '' : (string) wp_remote_retrieve_body($resp);
    if ($html !== '') {
        $found['scan_ok'] = true;
        // Ignore the JSON config this plugin prints itself.
        $html = preg_replace('#<script id="cpc-config"[^>]*>.*?</script>#s', '', $html);
        // Trust IDs only inside real <script> loaders. Visible text (reviews, comments, posts) can never
        // contain <script> for normal users, so nobody can plant a container that the store then loads.
        preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $scripts, PREG_SET_ORDER);
        $gtm = []; $ga = [];
        foreach ($scripts as $s) {
            $attrs = $s[1]; $code = $s[2];
            if (preg_match_all('#googletagmanager\.com/gtm\.js\?id=(GTM-[A-Z0-9]{4,10})#i', $attrs . $code, $m)) $gtm = array_merge($gtm, $m[1]);
            if (preg_match_all('#[\'"]dataLayer[\'"]\s*,\s*[\'"](GTM-[A-Z0-9]{4,10})[\'"]#', $code, $m)) $gtm = array_merge($gtm, $m[1]);
            if (preg_match_all('#googletagmanager\.com/gtag/js\?id=(G-[A-Z0-9]{6,12})#i', $attrs . $code, $m)) $ga = array_merge($ga, $m[1]);
            if (preg_match_all('#gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](G-[A-Z0-9]{6,12})[\'"]#', $code, $m)) $ga = array_merge($ga, $m[1]);
        }
        $found['gtm'] = array_values(array_unique(array_filter(array_map('strtoupper', $gtm), function ($id) { return cpc_valid_id($id, 'gtm'); })));
        $found['ga'] = array_values(array_unique(array_filter(array_map('strtoupper', $ga), function ($id) { return cpc_valid_id($id, 'ga'); })));
        foreach (['CookieYes' => '/cookieyes|cookie-law-info/i', 'Cookiebot' => '/cookiebot/i', 'Complianz' => '/complianz|cmplz/i',
            'Borlabs' => '/borlabs/i', 'Flatsome cookie notice' => '/flatsome-cookies/i', 'Real Cookie Banner' => '/real-cookie-banner/i'] as $name => $re) {
            if (preg_match($re, $html)) $found['other_banners'][] = $name;
        }
    }
    foreach (['cookie-policy', 'cookies-policy', 'cookie-policy-eu', 'politika-za-biskvitki', 'politiki-cookies', 'cookies'] as $slug) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') { $found['policy_url'] = get_permalink($page); break; }
    }
    if (!$found['policy_url']) {
        $pid = (int) get_option('wp_page_for_privacy_policy');
        if ($pid && get_post_status($pid) === 'publish') $found['policy_url'] = get_permalink($pid);
    }
    $active = get_option('active_plugins', []);
    $found['mailerlite'] = in_array('woo-mailerlite/woo-mailerlite.php', $active, true) ? 1 : 0;
    return $found;
}
function cpc_detected_options($found) {
    $o = cpc_options();
    if (!empty($found['gtm'])) { $o['gtm_id'] = $found['gtm'][0]; $o['ga_id'] = ''; $o['gtm_analytics'] = 1; $o['gtm_marketing'] = 1; }
    elseif (!empty($found['ga'])) { $o['ga_id'] = $found['ga'][0]; }
    if ($found['policy_url']) $o['policy_url'] = $found['policy_url'];
    $o['mailerlite'] = $found['mailerlite'];
    if (!empty($found['accent']) && !empty($found['apply_colour'])) { $o['theme'] = 'custom'; $o['accent'] = $found['accent']; }
    return $o;
}
register_activation_hook(NEXCONSENT_FILE, function () {
    // Only a brand-new install is configured automatically; existing settings are never overwritten.
    if (get_option('cpc_options', null) !== null) return;
    set_transient('cpc_needs_autosetup', 1, HOUR_IN_SECONDS);
});
add_action('admin_init', function () {
    if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (!get_transient('cpc_needs_autosetup') || !current_user_can('manage_options')) return;
    delete_transient('cpc_needs_autosetup');
    $found = cpc_detect();
    $found['apply_colour'] = true;
    update_option('cpc_options', cpc_sanitize(['reject_button' => 1] + cpc_detected_options($found)));
    set_transient('cpc_autosetup_report', $found, DAY_IN_SECONDS);
});
add_action('admin_post_cpc_autodetect', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('cpc_autodetect');
    $found = cpc_detect();
    update_option('cpc_options', cpc_sanitize(cpc_detected_options($found)));
    set_transient('cpc_autosetup_report', $found, DAY_IN_SECONDS);
    wp_safe_redirect(admin_url('options-general.php?page=nexconsent&cpc_detected=1'));
    exit;
});
add_action('admin_notices', function () {
    $found = get_transient('cpc_autosetup_report');
    if (!$found || !current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'settings_page_nexconsent') return; // Shown inside the page instead.
    echo '<div class="notice notice-success is-dismissible"><p><strong>NexConsent</strong> configured itself. <a href="' . esc_url(admin_url('options-general.php?page=nexconsent')) . '">Review the settings</a>.</p></div>';
});

/* ---------------------------------------------------------------------------
 * Google Tag Manager import package (Admin → Import container → Merge).
 * ------------------------------------------------------------------------- */
function cpc_gtm_package() {
    $o = cpc_options(); $gtm = cpc_valid_id($o['gtm_id'], 'gtm') ?: 'GTM-XXXXXXX';
    $base = ['accountId' => '0', 'containerId' => '0'];
    $tpl = function ($k, $v) { return ['type' => 'TEMPLATE', 'key' => $k, 'value' => $v]; };
    $variables = [];
    foreach ([['1', 'CPC - Analytics consent', 'cpc_analytics'], ['2', 'CPC - Marketing consent', 'cpc_marketing']] as $v) {
        $variables[] = $base + ['variableId' => $v[0], 'name' => $v[1], 'type' => 'v', 'parameter' => [
            ['type' => 'INTEGER', 'key' => 'dataLayerVersion', 'value' => '2'],
            ['type' => 'BOOLEAN', 'key' => 'setDefaultValue', 'value' => 'true'],
            $tpl('defaultValue', 'false'),
            $tpl('name', $v[2]),
        ]];
    }
    $event = ['type' => 'MATCH_REGEX', 'parameter' => [$tpl('arg0', '{{_event}}'), $tpl('arg1', '^cpc_consent_(ready|update)$')]];
    $triggers = [];
    foreach ([['1', 'CPC - Analytics allowed', 'CPC - Analytics consent'], ['2', 'CPC - Marketing allowed', 'CPC - Marketing consent']] as $t) {
        $triggers[] = $base + ['triggerId' => $t[0], 'name' => $t[1], 'type' => 'CUSTOM_EVENT', 'customEventFilter' => [$event],
            'filter' => [['type' => 'EQUALS', 'parameter' => [$tpl('arg0', '{{' . $t[2] . '}}'), $tpl('arg1', 'true')]]]];
    }
    return [
        'exportFormatVersion' => 2,
        'exportTime' => gmdate('Y-m-d H:i:s'),
        'containerVersion' => $base + [
            'containerVersionId' => '0',
            'name' => 'NexConsent ' . CPC_VERSION,
            'description' => 'Triggers for non-Google tags (Meta, Clarity, Hotjar, TikTok...). Google tags need nothing: Consent Mode v2 is sent by the plugin.',
            'container' => $base + ['name' => 'NexConsent', 'publicId' => $gtm, 'usageContext' => ['WEB']],
            'tag' => [], 'trigger' => $triggers, 'variable' => $variables, 'builtInVariable' => [],
            'fingerprint' => (string) time(),
        ],
    ];
}
add_action('admin_post_cpc_gtm_package', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('cpc_gtm_package');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="nexconsent-gtm-' . sanitize_file_name(wp_parse_url(home_url(), PHP_URL_HOST)) . '.json"');
    echo wp_json_encode(cpc_gtm_package(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
});

function cpc_settings_page() {
    if (!current_user_can('manage_options')) return;
    $o = cpc_options();
    $found = get_transient('cpc_autosetup_report');
    $bridge = cpc_bridge();
    $bridge_label = ['wpca' => 'WP Consent API', 'builtin' => 'built-in (this plugin)'];
    ?>
    <div class="wrap"><h1 style="display:flex;align-items:center;gap:10px"><img src="<?php echo esc_url(plugins_url('assets/nexdev-mark.png', NEXCONSENT_FILE)); ?>" width="36" height="36" alt="NexDev"> NexConsent <span style="font-size:13px;font-weight:400;color:#646970">v<?php echo esc_html(CPC_VERSION); ?> · by <a href="https://nexdev.gr" target="_blank" rel="noopener">NexDev</a></span></h1>
      <p>Local banner, no subscriptions or CDN requests. Optional tags load only after consent.</p>

      <div class="card" style="max-width:900px">
        <h2>Quick setup</h2>
        <?php if ($found) : ?>
          <p><?php echo isset($_GET['cpc_detected']) ? 'Detection finished and settings were applied:' : 'Detected on this site:'; ?></p>
          <ul style="list-style:disc;margin-left:20px">
            <li>Google Tag Manager: <strong><?php echo esc_html($found['gtm'] ? implode(', ', $found['gtm']) : 'not found'); ?></strong></li>
            <li>Google Analytics 4: <strong><?php echo esc_html($found['ga'] ? implode(', ', $found['ga']) : 'not found'); ?></strong></li>
            <li>Cookie policy page: <strong><?php echo esc_html($found['policy_url'] ?: 'not found — default /cookie-policy/ is used'); ?></strong></li>
            <li>MailerLite WooCommerce: <strong><?php echo $found['mailerlite'] ? 'yes' : 'no'; ?></strong></li>
            <?php if (!empty($found['accent'])) : ?><li>Theme brand colour: <strong><?php echo esc_html($found['accent']); ?></strong> <span style="display:inline-block;width:14px;height:14px;vertical-align:middle;border-radius:3px;background:<?php echo esc_attr($found['accent']); ?>"></span><?php echo !empty($found['apply_colour']) ? ' (applied to the banner)' : ''; ?></li><?php endif; ?>
            <?php if (!$found['scan_ok']) : ?><li style="color:#b32d2e">The home page could not be scanned; enter the IDs manually.</li><?php endif; ?>
            <?php if (!empty($found['other_banners'])) : ?><li style="color:#b32d2e"><strong>Another cookie banner is still active: <?php echo esc_html(implode(', ', $found['other_banners'])); ?>.</strong> Remove it, otherwise visitors see two banners.</li><?php endif; ?>
            <?php if (count($found['gtm']) > 1) : ?><li style="color:#b32d2e">More than one GTM container is on the page; the first one was used.</li><?php endif; ?>
          </ul>
        <?php else : ?>
          <p>Scan the home page for GTM / GA4 IDs, the cookie policy page and MailerLite, and fill in the settings below.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
          <input type="hidden" name="action" value="cpc_autodetect"><?php wp_nonce_field('cpc_autodetect'); ?>
          <?php submit_button($found ? 'Detect again and apply' : 'Detect and apply', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
          <input type="hidden" name="action" value="cpc_gtm_package"><?php wp_nonce_field('cpc_gtm_package'); ?>
          <?php submit_button('Download GTM package', 'secondary', 'submit', false); ?>
        </form>
        <p class="description">If GTM is loaded by another plugin or the theme, remove it there: this plugin must be the only one that loads GTM, so it can wait for consent.<br>
        GTM package: in Tag Manager go to Admin → Import container → choose the file → existing workspace → <strong>Merge</strong> → Rename conflicting. Google tags (GA4, Ads, Conversion Linker) need no change. For other tags (Meta, Clarity, Hotjar…) replace their “All Pages” trigger with <em>CPC - Analytics allowed</em> or <em>CPC - Marketing allowed</em>.</p>
      </div>

      <div class="notice notice-info inline"><p>Order attribution consent (WooCommerce <code>sbjs_*</code> cookies) currently runs <strong>through <?php echo esc_html($bridge_label[$bridge]); ?></strong>.
        WP Consent API is <?php echo cpc_wpca_available() ? 'installed and active' : '<strong>not active</strong>'; ?>.
        <?php if ($o['bridge'] === 'wpca' && $bridge === 'builtin') : ?><strong>WP Consent API was selected but is not active, so the built-in bridge is used.</strong><?php endif; ?>
        <?php if (!cpc_order_attribution_enabled()) : ?>WooCommerce order attribution is off, so nothing needs to be bridged.<?php endif; ?></p></div>

      <?php settings_errors('cpc_options'); ?>
      <form method="post" action="options.php"><?php settings_fields('cpc_settings'); ?>
        <table class="form-table"><tbody>
          <tr><th><label for="cpc-language">Banner language</label></th><td><select id="cpc-language" name="cpc_options[language]"><?php foreach (['auto' => 'Site language (automatic)', 'bg' => 'Български', 'en' => 'English', 'el' => 'Ελληνικά'] as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($o['language'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></td></tr>
          <?php foreach (['gtm_id' => 'GTM container ID', 'ga_id' => 'GA4 ID (only without GTM)', 'policy_url' => 'Cookie policy URL', 'revision' => 'Policy / consent revision'] as $k => $v) : ?>
          <tr><th><label for="cpc-<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></label></th><td><input class="regular-text" id="cpc-<?php echo esc_attr($k); ?>" name="cpc_options[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($o[$k]); ?>" type="<?php echo $k === 'policy_url' ? 'url' : 'text'; ?>"></td></tr>
          <?php endforeach; ?>
          <tr><th>Services used by this site</th><td><?php foreach (['gtm_analytics' => 'Google Analytics in this GTM container', 'gtm_marketing' => 'Google Ads in this GTM container', 'mailerlite' => 'MailerLite WooCommerce universal loader', 'external_analytics' => 'Other analytics services (register their cookies with cpc_services)', 'external_marketing' => 'Other marketing services (register their cookies with cpc_services)'] as $k => $v) : ?><p><label><input type="checkbox" name="cpc_options[<?php echo esc_attr($k); ?>]" value="1" <?php checked($o[$k], 1); ?>> <?php echo esc_html($v); ?></label></p><?php endforeach; ?></td></tr>
          <tr><th>Reject button</th><td><label><input type="checkbox" name="cpc_options[reject_button]" value="1" <?php checked($o['reject_button'], 1); ?>> Show “Reject optional” on the banner, next to “Accept all” (recommended: data protection authorities expect refusing to be as easy as accepting)</label></td></tr>
          <tr><th><label for="cpc-theme">Banner colours</label></th><td><select id="cpc-theme" name="cpc_options[theme]">
            <?php foreach (['blue' => 'Blue', 'green' => 'Green', 'custom' => 'Custom brand colour'] as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($o['theme'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
          </select> <input type="color" id="cpc-accent" name="cpc_options[accent]" value="<?php echo esc_attr(cpc_hex($o['accent']) ?: '#1e73be'); ?>" aria-label="Brand colour">
          <?php $pal = cpc_palette(); ?><span style="display:inline-flex;gap:6px;vertical-align:middle;margin-left:10px"><span style="padding:6px 12px;border-radius:7px;background:<?php echo esc_attr($pal['accent']); ?>;color:#fff;font-weight:600">Accept all</span><span style="padding:6px 12px;border-radius:7px;border:1px solid <?php echo esc_attr($pal['quiet-border']); ?>;color:<?php echo esc_attr($pal['text']); ?>;background:#fff">Settings</span></span>
          <p class="description">The brand colour is used only with “Custom”. All other shades are generated from it, and the button colour is darkened if needed so the text stays readable (WCAG AA).</p></td></tr>
          <tr><th><label for="cpc-bridge">Order attribution consent</label></th><td><select id="cpc-bridge" name="cpc_options[bridge]">
            <?php foreach (['auto' => 'Automatic — WP Consent API if active, otherwise built-in (recommended)', 'wpca' => 'Always through WP Consent API', 'builtin' => 'Always built-in (ignore WP Consent API)'] as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($o['bridge'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
          </select><p class="description">Controls WooCommerce source tracking (<code>sbjs_*</code> cookies): they are written only after marketing consent. Switch to built-in if the WP Consent API causes a problem; both give the same result for visitors.</p></td></tr>
        </tbody></table><?php submit_button(); ?>
      </form>
      <p>Blank IDs and no external services = no banner and no front-end assets. GTM overrides direct GA4. Choices expire in 180 days. Increase the revision when purposes change.</p>
      <p>Policy table: <code>[nexconsent_cookie_table]</code> · Footer settings button: <code>[nexconsent_settings]</code> (the old <code>[cpapstore_…]</code> shortcodes keep working).</p>
      <p>LiteSpeed JS Excludes and JS Deferred/Delayed Excludes: <code>nexconsent/assets/consent-core.js</code>. Cloudflare Rocket Loader is excluded via <code>data-cfasync="false"</code>.</p>
    </div><?php
}

// Gate the observed WooCommerce MailerLite loader, preserving its account configuration.
function cpc_gate_mailerlite($tag, $handle, $src) {
    $o = cpc_options();
    if (is_admin() || !$o['mailerlite'] || $handle !== 'mailerlite-rewrite-universal') return $tag;
    $tag = preg_replace('/\s+(?:src|type)=["\'][^"\']*["\']/i', '', $tag);
    return preg_replace('/<script\b/i', '<script type="text/plain" data-cpc-category="marketing" data-cpc-src="' . esc_url($src) . '"', $tag, 1);
}
add_filter('script_loader_tag', 'cpc_gate_mailerlite', 20, 3);
