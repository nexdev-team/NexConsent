<?php
/**
 * Settings screen, one-click setup and the Google Tag Manager package.
 */
defined('ABSPATH') || exit;

const NEXCONSENT_PAGE = 'nexconsent';

/* ---------------------------------------------------------------------------
 * Saving
 * ------------------------------------------------------------------------- */
function nexconsent_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $current = nexconsent_options();
    // Unchecked boxes are not submitted.
    foreach (['gtm_analytics', 'gtm_marketing', 'mailerlite', 'external_analytics', 'external_marketing', 'reject_button', 'reopen_button'] as $k) {
        $input[$k] = empty($input[$k]) ? 0 : 1;
    }
    // Internal values never come from the form.
    $input['cookie_name'] = $current['cookie_name'];
    $input['legacy'] = $current['legacy'];
    // Raw scripts: only for users who may post unfiltered HTML (single-site admins, network super admins).
    foreach (['scripts_analytics', 'scripts_marketing'] as $k) {
        if (!current_user_can('unfiltered_html') || !array_key_exists($k, $input)) $input[$k] = $current[$k];
    }
    foreach (['gtm_id' => 'gtm', 'ga_id' => 'ga'] as $k => $kind) {
        if (!empty($input[$k]) && !nexconsent_valid_id($input[$k], $kind)) {
            add_settings_error('nexconsent_options', $k, __('An invalid or placeholder tracking ID was removed.', 'nexconsent'));
        }
    }
    return nexconsent_clean($input);
}
add_action('admin_init', function () {
    register_setting('nexconsent', 'nexconsent_options', ['type' => 'array', 'sanitize_callback' => 'nexconsent_sanitize', 'default' => []]);
});
add_action('admin_menu', function () {
    $name = nexconsent_brand()['name'];
    add_options_page($name, $name, 'manage_options', NEXCONSENT_PAGE, 'nexconsent_settings_page');
});
add_filter('plugin_action_links_' . plugin_basename(NEXCONSENT_FILE), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=' . NEXCONSENT_PAGE)) . '">' . esc_html__('Settings', 'nexconsent') . '</a>');
    return $links;
});

/* ---------------------------------------------------------------------------
 * Detection: what the site already uses, so a fresh install works at once.
 * ------------------------------------------------------------------------- */
function nexconsent_detect() {
    $found = ['gtm' => [], 'ga' => [], 'policy_url' => '', 'mailerlite' => 0, 'other_banners' => [], 'unblocked' => [], 'scan_ok' => false, 'accent' => nexconsent_theme_colour()];
    // Loopback request to this site only, with the TLS policy WordPress core uses for loopbacks.
    $resp = wp_remote_get(add_query_arg('nexconsent_scan', time(), home_url('/')), [
        'timeout' => 15, 'redirection' => 3, 'limit_response_size' => 3 * MB_IN_BYTES,
        'sslverify' => apply_filters('https_local_ssl_verify', false),
    ]);
    $html = is_wp_error($resp) ? '' : (string) wp_remote_retrieve_body($resp);
    if ($html !== '') {
        $found['scan_ok'] = true;
        // Leave out what NexConsent prints itself (config and scripts that wait for consent).
        $html = preg_replace('#<script id="(?:nxc|cpc)-config"[^>]*>.*?</script>#s', '', $html);
        $html = preg_replace('#<script type="text/plain" data-(?:nxc|cpc)-category[^>]*>.*?</script>#s', '', $html);
        // Trust IDs only inside real <script> loaders: visible text (reviews, comments, posts) never contains
        // <script> for normal users, so nobody can plant a container that the site then loads.
        preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $scripts, PREG_SET_ORDER);
        $gtm = []; $ga = []; $code = '';
        foreach ($scripts as $s) {
            $all = $s[1] . $s[2]; $code .= $all . "\n";
            if (preg_match_all('#googletagmanager\.com/gtm\.js\?id=(GTM-[A-Z0-9]{4,10})#i', $all, $m)) $gtm = array_merge($gtm, $m[1]);
            if (preg_match_all('#[\'"]dataLayer[\'"]\s*,\s*[\'"](GTM-[A-Z0-9]{4,10})[\'"]#', $s[2], $m)) $gtm = array_merge($gtm, $m[1]);
            if (preg_match_all('#googletagmanager\.com/gtag/js\?id=(G-[A-Z0-9]{6,12})#i', $all, $m)) $ga = array_merge($ga, $m[1]);
            if (preg_match_all('#gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](G-[A-Z0-9]{6,12})[\'"]#', $s[2], $m)) $ga = array_merge($ga, $m[1]);
        }
        $valid = function ($kind) { return function ($id) use ($kind) { return nexconsent_valid_id($id, $kind); }; };
        $found['gtm'] = array_values(array_unique(array_filter(array_map('strtoupper', $gtm), $valid('gtm'))));
        $found['ga'] = array_values(array_unique(array_filter(array_map('strtoupper', $ga), $valid('ga'))));
        foreach (['CookieYes' => '/cookieyes|cookie-law-info/i', 'Cookiebot' => '/cookiebot/i', 'Complianz' => '/complianz|cmplz/i',
            'Borlabs' => '/borlabs/i', 'Real Cookie Banner' => '/real-cookie-banner/i', 'Cookie Notice' => '/cookie-notice/i',
            'Moove GDPR' => '/moove_gdpr|gdpr-cookie-compliance/i', 'Flatsome cookie notice' => '/flatsome-cookies/i'] as $name => $re) {
            if (preg_match($re, $html)) $found['other_banners'][] = $name;
        }
        // Trackers that load on their own, before consent.
        foreach (['Meta Pixel' => '#connect\.facebook\.net/[^"\']*fbevents\.js|fbq\(\s*[\'"]init#i', 'Microsoft Clarity' => '#clarity\.ms/tag#i',
            'Hotjar' => '#static\.hotjar\.com#i', 'TikTok Pixel' => '#analytics\.tiktok\.com#i', 'LinkedIn Insight' => '#snap\.licdn\.com#i',
            'Pinterest Tag' => '#s\.pinimg\.com/ct/core\.js#i'] as $name => $re) {
            if (preg_match($re, $code)) $found['unblocked'][] = $name;
        }
    }
    foreach ((array) apply_filters('nexconsent_policy_slugs', ['cookie-policy', 'cookies-policy', 'cookie-policy-eu', 'politika-za-biskvitki', 'politiki-cookies', 'cookies']) as $slug) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') { $found['policy_url'] = get_permalink($page); break; }
    }
    $found['mailerlite'] = in_array('woo-mailerlite/woo-mailerlite.php', (array) get_option('active_plugins', []), true) ? 1 : 0;
    return $found;
}

function nexconsent_detected_options($found, $apply_colour = false) {
    $o = nexconsent_options();
    if (!empty($found['gtm'])) { $o['gtm_id'] = $found['gtm'][0]; $o['ga_id'] = ''; $o['gtm_analytics'] = 1; $o['gtm_marketing'] = 1; }
    elseif (!empty($found['ga'])) { $o['ga_id'] = $found['ga'][0]; }
    if (!empty($found['policy_url'])) $o['policy_url'] = $found['policy_url'];
    $o['mailerlite'] = $found['mailerlite'];
    if ($apply_colour && !empty($found['accent'])) { $o['theme'] = 'custom'; $o['accent'] = $found['accent']; }
    return $o;
}

add_action('admin_init', function () {
    if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (!get_transient('nexconsent_needs_setup') || !current_user_can('manage_options')) return;
    delete_transient('nexconsent_needs_setup');
    $found = nexconsent_detect();
    update_option('nexconsent_options', nexconsent_clean(nexconsent_detected_options($found, true)));
    $found['colour_applied'] = !empty($found['accent']);
    set_transient('nexconsent_setup_report', $found, DAY_IN_SECONDS);
});
add_action('admin_post_nexconsent_detect', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Sorry, you are not allowed to do that.', 'nexconsent'), 403);
    check_admin_referer('nexconsent_detect');
    $found = nexconsent_detect();
    update_option('nexconsent_options', nexconsent_clean(nexconsent_detected_options($found)));
    set_transient('nexconsent_setup_report', $found, DAY_IN_SECONDS);
    wp_safe_redirect(admin_url('options-general.php?page=' . NEXCONSENT_PAGE . '&detected=1'));
    exit;
});
add_action('admin_notices', function () {
    $found = get_transient('nexconsent_setup_report');
    if (!$found || !current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'settings_page_' . NEXCONSENT_PAGE) return;
    printf('<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
        esc_html(sprintf(__('%s configured itself.', 'nexconsent'), nexconsent_brand()['name'])),
        esc_url(admin_url('options-general.php?page=' . NEXCONSENT_PAGE)), esc_html__('Review the settings', 'nexconsent'));
});

/* ---------------------------------------------------------------------------
 * Google Tag Manager import package (Admin → Import container → Merge).
 * ------------------------------------------------------------------------- */
function nexconsent_gtm_package() {
    $gtm = nexconsent_valid_id(nexconsent_options()['gtm_id'], 'gtm') ?: 'GTM-XXXXXXX';
    $base = ['accountId' => '0', 'containerId' => '0'];
    $tpl = function ($k, $v) { return ['type' => 'TEMPLATE', 'key' => $k, 'value' => $v]; };
    $variables = $triggers = [];
    foreach ([['1', 'Consent - Analytics', 'nexconsent_analytics'], ['2', 'Consent - Marketing', 'nexconsent_marketing']] as $v) {
        $variables[] = $base + ['variableId' => $v[0], 'name' => $v[1], 'type' => 'v', 'parameter' => [
            ['type' => 'INTEGER', 'key' => 'dataLayerVersion', 'value' => '2'],
            ['type' => 'BOOLEAN', 'key' => 'setDefaultValue', 'value' => 'true'],
            $tpl('defaultValue', 'false'), $tpl('name', $v[2]),
        ]];
    }
    $event = ['type' => 'MATCH_REGEX', 'parameter' => [$tpl('arg0', '{{_event}}'), $tpl('arg1', '^nexconsent_(ready|update)$')]];
    foreach ([['1', 'Consent - Analytics allowed', 'Consent - Analytics'], ['2', 'Consent - Marketing allowed', 'Consent - Marketing']] as $t) {
        $triggers[] = $base + ['triggerId' => $t[0], 'name' => $t[1], 'type' => 'CUSTOM_EVENT', 'customEventFilter' => [$event],
            'filter' => [['type' => 'EQUALS', 'parameter' => [$tpl('arg0', '{{' . $t[2] . '}}'), $tpl('arg1', 'true')]]]];
    }
    return [
        'exportFormatVersion' => 2, 'exportTime' => gmdate('Y-m-d H:i:s'),
        'containerVersion' => $base + [
            'containerVersionId' => '0', 'name' => 'Consent triggers',
            'description' => 'Triggers for non-Google tags (Meta, Clarity, Hotjar, TikTok...). Google tags need nothing: Consent Mode v2 is sent by the consent plugin.',
            'container' => $base + ['name' => 'Consent', 'publicId' => $gtm, 'usageContext' => ['WEB']],
            'tag' => [], 'trigger' => $triggers, 'variable' => $variables, 'builtInVariable' => [], 'fingerprint' => (string) time(),
        ],
    ];
}
add_action('admin_post_nexconsent_gtm_package', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Sorry, you are not allowed to do that.', 'nexconsent'), 403);
    check_admin_referer('nexconsent_gtm_package');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="consent-gtm-' . sanitize_file_name((string) wp_parse_url(home_url(), PHP_URL_HOST)) . '.json"');
    echo wp_json_encode(nexconsent_gtm_package(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
});

/* ---------------------------------------------------------------------------
 * Settings page
 * ------------------------------------------------------------------------- */
function nexconsent_field($key, $label, $type = 'text', $help = '', $attrs = '') {
    $o = nexconsent_options(); $id = 'nxc-' . $key; $name = 'nexconsent_options[' . $key . ']';
    echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
    if ($type === 'textarea') echo '<textarea class="large-text code" rows="4" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" ' . $attrs . '>' . esc_textarea($o[$key]) . '</textarea>';
    else echo '<input class="regular-text" type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($o[$key]) . '" ' . $attrs . '>';
    if ($help) echo '<p class="description">' . wp_kses($help, ['code' => [], 'strong' => [], 'em' => [], 'br' => []]) . '</p>';
    echo '</td></tr>';
}
function nexconsent_checkbox($key, $label) {
    printf('<p><label><input type="checkbox" name="nexconsent_options[%s]" value="1" %s> %s</label></p>', esc_attr($key), checked(nexconsent_options()[$key], 1, false), wp_kses($label, ['code' => [], 'strong' => []]));
}
function nexconsent_select($key, $label, array $choices, $help = '') {
    $o = nexconsent_options();
    echo '<tr><th><label for="nxc-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><select id="nxc-' . esc_attr($key) . '" name="nexconsent_options[' . esc_attr($key) . ']">';
    foreach ($choices as $k => $v) echo '<option value="' . esc_attr($k) . '" ' . selected($o[$key], $k, false) . '>' . esc_html($v) . '</option>';
    echo '</select>';
    if ($help) echo '<p class="description">' . wp_kses($help, ['code' => [], 'strong' => []]) . '</p>';
    echo '</td></tr>';
}

function nexconsent_settings_page() {
    if (!current_user_can('manage_options')) return;
    $o = nexconsent_options(); $brand = nexconsent_brand(); $found = get_transient('nexconsent_setup_report');
    $defaults = nexconsent_strings(false); $bridge = nexconsent_bridge(); $can_scripts = current_user_can('unfiltered_html');
    $yes = __('yes', 'nexconsent'); $no = __('no', 'nexconsent'); $none = __('not found', 'nexconsent');
    ?>
    <div class="wrap">
      <h1 style="display:flex;align-items:center;gap:10px">
        <?php if ($brand['logo']) : ?><img src="<?php echo esc_url($brand['logo']); ?>" width="36" height="36" alt=""><?php endif; ?>
        <?php echo esc_html($brand['name']); ?>
        <span style="font-size:13px;font-weight:400;color:#646970">v<?php echo esc_html(NEXCONSENT_VERSION); ?><?php if ($brand['author']) : ?> · <?php echo $brand['url'] ? '<a href="' . esc_url($brand['url']) . '" target="_blank" rel="noopener">' . esc_html($brand['author']) . '</a>' : esc_html($brand['author']); ?><?php endif; ?></span>
      </h1>
      <p><?php esc_html_e('First-party banner: no subscription, no CDN, no tracking of its own. Optional tags load only after consent.', 'nexconsent'); ?></p>

      <div class="card" style="max-width:960px">
        <h2><?php esc_html_e('Quick setup', 'nexconsent'); ?></h2>
        <?php if ($found) : ?>
          <p><?php echo isset($_GET['detected']) ? esc_html__('Detection finished and the settings below were updated:', 'nexconsent') : esc_html__('Found on this site:', 'nexconsent'); ?></p>
          <ul style="list-style:disc;margin-left:20px">
            <li>Google Tag Manager: <strong><?php echo esc_html($found['gtm'] ? implode(', ', $found['gtm']) : $none); ?></strong></li>
            <li>Google Analytics 4: <strong><?php echo esc_html($found['ga'] ? implode(', ', $found['ga']) : $none); ?></strong></li>
            <li><?php esc_html_e('Cookie policy page', 'nexconsent'); ?>: <strong><?php echo esc_html($found['policy_url'] ?: __('not found: the privacy policy page is linked instead', 'nexconsent')); ?></strong></li>
            <li>MailerLite (WooCommerce): <strong><?php echo esc_html($found['mailerlite'] ? $yes : $no); ?></strong></li>
            <?php if (!empty($found['accent'])) : ?><li><?php esc_html_e('Theme colour', 'nexconsent'); ?>: <strong><?php echo esc_html($found['accent']); ?></strong> <span style="display:inline-block;width:14px;height:14px;vertical-align:middle;border-radius:3px;background:<?php echo esc_attr($found['accent']); ?>"></span><?php echo !empty($found['colour_applied']) ? ' ' . esc_html__('(used for the banner)', 'nexconsent') : ''; ?></li><?php endif; ?>
            <?php if (empty($found['scan_ok'])) : ?><li style="color:#b32d2e"><?php esc_html_e('The home page could not be scanned; enter the IDs by hand.', 'nexconsent'); ?></li><?php endif; ?>
            <?php if (!empty($found['other_banners'])) : ?><li style="color:#b32d2e"><strong><?php echo esc_html(sprintf(__('Another cookie banner is active: %s. Remove it, otherwise visitors see two banners.', 'nexconsent'), implode(', ', $found['other_banners']))); ?></strong></li><?php endif; ?>
            <?php if (!empty($found['unblocked'])) : ?><li style="color:#b32d2e"><strong><?php echo esc_html(sprintf(__('These load before consent: %s. Remove them from the theme or plugin that adds them and paste their code into “Scripts after consent” below.', 'nexconsent'), implode(', ', $found['unblocked']))); ?></strong></li><?php endif; ?>
            <?php if (count($found['gtm']) > 1) : ?><li style="color:#b32d2e"><?php esc_html_e('More than one GTM container is on the page; the first one was used.', 'nexconsent'); ?></li><?php endif; ?>
          </ul>
        <?php else : ?>
          <p><?php esc_html_e('Scans the home page for Google Tag Manager / GA4, other cookie banners and trackers that load without consent, and finds the cookie policy page.', 'nexconsent'); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
          <input type="hidden" name="action" value="nexconsent_detect"><?php wp_nonce_field('nexconsent_detect'); ?>
          <?php submit_button($found ? __('Detect again and apply', 'nexconsent') : __('Detect and apply', 'nexconsent'), 'secondary', 'submit', false); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
          <input type="hidden" name="action" value="nexconsent_gtm_package"><?php wp_nonce_field('nexconsent_gtm_package'); ?>
          <?php submit_button(__('Download GTM package', 'nexconsent'), 'secondary', 'submit', false); ?>
        </form>
        <p class="description"><?php echo wp_kses(__('GTM must be loaded only by this plugin, so it can wait for consent: remove it from other plugins and the theme.<br>GTM package: Tag Manager → Admin → Import container → choose the file → existing workspace → <strong>Merge</strong> → Rename conflicting. Google tags need no change. For other tags (Meta, Clarity, Hotjar…) replace “All Pages” with <em>Consent - Analytics allowed</em> or <em>Consent - Marketing allowed</em>.', 'nexconsent'), ['br' => [], 'strong' => [], 'em' => []]); ?></p>
      </div>

      <?php settings_errors('nexconsent_options'); ?>
      <form method="post" action="options.php"><?php settings_fields('nexconsent'); ?>
        <h2 class="title"><?php esc_html_e('Banner', 'nexconsent'); ?></h2>
        <table class="form-table" role="presentation"><tbody>
          <?php nexconsent_select('language', __('Language', 'nexconsent'), nexconsent_languages(), __('Automatic follows the site language, including Polylang / WPML pages. Other languages: add <code>languages/nexconsent-{locale}.po</code> or translate with Loco Translate.', 'nexconsent')); ?>
          <?php nexconsent_select('position', __('Position', 'nexconsent'), ['bar' => __('Bar across the bottom', 'nexconsent'), 'box' => __('Box in the bottom-left corner', 'nexconsent')]); ?>
          <tr><th><?php esc_html_e('Buttons', 'nexconsent'); ?></th><td>
            <?php nexconsent_checkbox('reject_button', __('“Reject optional” next to “Accept all” (data protection authorities expect refusing to be as easy as accepting)', 'nexconsent')); ?>
            <?php nexconsent_checkbox('reopen_button', __('Small “Cookie settings” button in the corner after a choice, so visitors can change their mind (or put <code>[nexconsent_settings]</code> in the footer)', 'nexconsent')); ?>
          </td></tr>
          <tr><th><label for="nxc-theme"><?php esc_html_e('Colours', 'nexconsent'); ?></label></th><td>
            <select id="nxc-theme" name="nexconsent_options[theme]"><?php foreach (['blue' => __('Blue', 'nexconsent'), 'green' => __('Green', 'nexconsent'), 'custom' => __('Brand colour', 'nexconsent')] as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($o['theme'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select>
            <input type="color" name="nexconsent_options[accent]" value="<?php echo esc_attr(nexconsent_hex($o['accent']) ?: '#1e73be'); ?>" aria-label="<?php esc_attr_e('Brand colour', 'nexconsent'); ?>">
            <?php $pal = nexconsent_palette(); ?><span style="display:inline-flex;gap:6px;vertical-align:middle;margin-left:10px"><span style="padding:6px 12px;border-radius:7px;background:<?php echo esc_attr($pal['accent']); ?>;color:#fff;font-weight:600"><?php echo esc_html($defaults['accept']); ?></span><span style="padding:6px 12px;border-radius:7px;border:1px solid <?php echo esc_attr($pal['quiet-border']); ?>;color:<?php echo esc_attr($pal['text']); ?>;background:#fff"><?php echo esc_html($defaults['settings']); ?></span></span>
            <p class="description"><?php esc_html_e('With “Brand colour” every other shade is generated from it, and the button is darkened if needed so its text stays readable (WCAG AA).', 'nexconsent'); ?></p>
          </td></tr>
        </tbody></table>

        <h2 class="title"><?php esc_html_e('Texts', 'nexconsent'); ?></h2>
        <p><?php esc_html_e('Leave empty to use the translated default shown in grey. Custom texts are used for every language.', 'nexconsent'); ?></p>
        <table class="form-table" role="presentation"><tbody>
          <?php foreach (['title' => __('Title', 'nexconsent'), 'body' => __('Message', 'nexconsent'), 'accept' => __('Accept button', 'nexconsent'), 'reject' => __('Reject button', 'nexconsent'), 'settings' => __('Settings button', 'nexconsent')] as $k => $label) {
              nexconsent_field($k, $label, $k === 'body' ? 'textarea' : 'text', '', 'placeholder="' . esc_attr($defaults[$k]) . '" maxlength="' . ($k === 'body' ? 600 : 80) . '"');
          } ?>
        </tbody></table>

        <h2 class="title"><?php esc_html_e('Tracking and services', 'nexconsent'); ?></h2>
        <table class="form-table" role="presentation"><tbody>
          <?php nexconsent_field('gtm_id', __('GTM container ID', 'nexconsent'), 'text', __('Loaded only after consent, with Google Consent Mode v2.', 'nexconsent'), 'placeholder="GTM-XXXXXXX"'); ?>
          <?php nexconsent_field('ga_id', __('GA4 measurement ID', 'nexconsent'), 'text', __('Only without GTM.', 'nexconsent'), 'placeholder="G-XXXXXXXXXX"'); ?>
          <tr><th><?php esc_html_e('Services', 'nexconsent'); ?></th><td>
            <?php nexconsent_checkbox('gtm_analytics', __('The GTM container runs Google Analytics', 'nexconsent')); ?>
            <?php nexconsent_checkbox('gtm_marketing', __('The GTM container runs Google Ads or other advertising', 'nexconsent')); ?>
            <?php nexconsent_checkbox('mailerlite', __('MailerLite for WooCommerce (its loader waits for marketing consent)', 'nexconsent')); ?>
            <?php nexconsent_checkbox('external_analytics', __('Another plugin sends analytics and follows Consent Mode / WP Consent API (e.g. Site Kit, MonsterInsights)', 'nexconsent')); ?>
            <?php nexconsent_checkbox('external_marketing', __('Another plugin sends advertising data and follows Consent Mode / WP Consent API (e.g. Google for WooCommerce, Meta for WooCommerce)', 'nexconsent')); ?>
          </td></tr>
          <?php nexconsent_field('services_analytics', __('Other analytics services', 'nexconsent'), 'textarea', __('One per line: <code>Provider | cookie, cookie</code>, e.g. <code>Microsoft Clarity | _clck, _clsk, CLID</code>. Listed in the cookie table and deleted when consent is withdrawn. <code>*</code> matches any ending.', 'nexconsent')); ?>
          <?php nexconsent_field('services_marketing', __('Other marketing services', 'nexconsent'), 'textarea', __('e.g. <code>Meta Pixel | _fbp, _fbc</code>', 'nexconsent')); ?>
          <?php nexconsent_field('scripts_analytics', __('Scripts after analytics consent', 'nexconsent'), 'textarea', $can_scripts ? __('Paste the tracking code as given by the provider. Only <code>&lt;script&gt;</code> elements are kept; <code>&lt;noscript&gt;</code> pixels are removed because they would load without consent.', 'nexconsent') : __('Only administrators who may post unfiltered HTML can change this.', 'nexconsent'), $can_scripts ? 'spellcheck="false"' : 'readonly'); ?>
          <?php nexconsent_field('scripts_marketing', __('Scripts after marketing consent', 'nexconsent'), 'textarea', $can_scripts ? __('e.g. the Meta Pixel or TikTok Pixel base code.', 'nexconsent') : __('Only administrators who may post unfiltered HTML can change this.', 'nexconsent'), $can_scripts ? 'spellcheck="false"' : 'readonly'); ?>
        </tbody></table>

        <h2 class="title"><?php esc_html_e('Policy and advanced', 'nexconsent'); ?></h2>
        <table class="form-table" role="presentation"><tbody>
          <?php nexconsent_field('policy_url', __('Cookie policy URL', 'nexconsent'), 'url', __('Empty = the privacy policy page from Settings → Privacy. Put <code>[nexconsent_cookie_table]</code> on the page for an always-current list of cookies.', 'nexconsent'), 'placeholder="' . esc_attr(function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '') . '"'); ?>
          <?php nexconsent_field('revision', __('Consent revision', 'nexconsent'), 'text', __('Change it (e.g. 1 → 2) when the policy changes in a way that needs every visitor to choose again. Adding or removing a service does this automatically.', 'nexconsent'), 'style="width:6em"'); ?>
          <?php nexconsent_select('bridge', __('Order attribution consent', 'nexconsent'), ['auto' => __('Automatic: WP Consent API if active, otherwise built-in (recommended)', 'nexconsent'), 'wpca' => __('Always through WP Consent API', 'nexconsent'), 'builtin' => __('Always built-in', 'nexconsent')],
              sprintf(__('WooCommerce source tracking (<code>sbjs_*</code> cookies) starts only after marketing consent. Now running: <strong>%s</strong>.', 'nexconsent'), $bridge === 'wpca' ? 'WP Consent API' : __('built-in', 'nexconsent'))); ?>
        </tbody></table>
        <?php submit_button(); ?>
      </form>

      <h2><?php esc_html_e('Good to know', 'nexconsent'); ?></h2>
      <ul style="list-style:disc;margin-left:20px">
        <li><?php esc_html_e('With no tracking configured there is no banner and nothing is loaded on the front end.', 'nexconsent'); ?></li>
        <li><?php echo wp_kses(__('Shortcodes: <code>[nexconsent_cookie_table]</code> for the policy page, <code>[nexconsent_settings]</code> for a “Cookie settings” link in the footer.', 'nexconsent'), ['code' => []]); ?></li>
        <li><?php echo wp_kses(__('Block any other script yourself with <code>&lt;script type="text/plain" data-nxc-category="marketing"&gt;</code> (or <code>data-nxc-src="…"</code> for external files).', 'nexconsent'), ['code' => []]); ?></li>
        <li><?php echo wp_kses(__('Cache and optimisation plugins: <code>consent-core.js</code> is already marked to be left alone (LiteSpeed, WP Rocket, Cloudflare Rocket Loader). Choices are stored in the browser, so cached pages stay correct.', 'nexconsent'), ['code' => []]); ?></li>
        <li><?php esc_html_e('Choices last 180 days.', 'nexconsent'); ?></li>
      </ul>
    </div>
    <?php
}
