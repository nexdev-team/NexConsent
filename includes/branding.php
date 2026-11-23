<?php
/**
 * White label.
 *
 * Agencies can rebrand NexConsent for their clients from wp-config.php:
 *
 *   define('NEXCONSENT_WHITE_LABEL', true);          // hide the NexDev logo and credit
 *   define('NEXCONSENT_BRAND_NAME', 'Cookie Consent'); // name in menus, plugin list and cookie table
 *   define('NEXCONSENT_BRAND_AUTHOR', 'Your Agency');
 *   define('NEXCONSENT_BRAND_URL', 'https://agency.example');
 *   define('NEXCONSENT_BRAND_LOGO', 'https://agency.example/logo.png'); // square, shown at 36px
 *   define('NEXCONSENT_DISABLE_UPDATES', true);      // no update checks against GitHub
 *
 * or with the 'nexconsent_branding' filter. Front-end markup carries no branding at all.
 */
defined('ABSPATH') || exit;

function nexconsent_brand() {
    $const = function ($name) { return defined($name) ? trim((string) constant($name)) : ''; };
    $white = defined('NEXCONSENT_WHITE_LABEL') && NEXCONSENT_WHITE_LABEL;
    $brand = [
        'name' => $const('NEXCONSENT_BRAND_NAME') ?: 'NexConsent',
        'author' => $const('NEXCONSENT_BRAND_AUTHOR') ?: ($white ? '' : 'NexDev'),
        'url' => $const('NEXCONSENT_BRAND_URL') ?: ($white ? '' : 'https://nexdev.gr'),
        'logo' => $const('NEXCONSENT_BRAND_LOGO') ?: ($white ? '' : plugins_url('assets/nexdev-mark.png', NEXCONSENT_FILE)),
        'credit' => !$white,
    ];
    $brand = (array) apply_filters('nexconsent_branding', $brand);
    $brand['name'] = sanitize_text_field($brand['name'] ?? '') ?: 'NexConsent';
    $brand['author'] = sanitize_text_field($brand['author'] ?? '');
    $brand['url'] = esc_url_raw($brand['url'] ?? '');
    $brand['logo'] = esc_url_raw($brand['logo'] ?? '');
    $brand['credit'] = !empty($brand['credit']);
    return $brand;
}

function nexconsent_is_rebranded() {
    $b = nexconsent_brand();
    return !$b['credit'] || $b['name'] !== 'NexConsent' || $b['author'] !== 'NexDev';
}

// Plugins screen: show the brand instead of the header values.
add_filter('all_plugins', function ($plugins) {
    $file = plugin_basename(NEXCONSENT_FILE);
    if (!isset($plugins[$file]) || !nexconsent_is_rebranded()) return $plugins;
    $b = nexconsent_brand();
    $plugins[$file]['Name'] = $plugins[$file]['Title'] = $b['name'];
    $plugins[$file]['Author'] = $plugins[$file]['AuthorName'] = $b['author'];
    $plugins[$file]['AuthorURI'] = $plugins[$file]['PluginURI'] = $b['url'];
    $plugins[$file]['Description'] = __('Lightweight, first-party cookie consent with Google Consent Mode v2 and script blocking.', 'nexconsent');
    return $plugins;
});
