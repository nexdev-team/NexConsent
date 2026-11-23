<?php
/**
 * Plugin Name: NexConsent
 * Plugin URI: https://github.com/nexdev-team/NexConsent
 * Description: Lightweight, first-party cookie consent for WordPress and WooCommerce with Google Consent Mode v2, WP Consent API bridge and Bulgarian, Greek and English copy. By NexDev.
 * Version: 1.3.1
 * Author: NexDev
 * Author URI: https://nexdev.gr
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: nexconsent
 * Update URI: https://github.com/nexdev-team/NexConsent
 */
defined('ABSPATH') || exit;

// NexConsent replaces the earlier "CPAPstore Consent" plugin and keeps its settings (cpc_options)
// and visitors' choices (cookie). If the old plugin is still active, step aside and switch it off.
if (defined('CPC_VERSION')) {
    register_activation_hook(__FILE__, function () {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins('cpapstore-consent/cpapstore-consent.php', true);
    });
    add_action('admin_notices', function () {
        if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p><strong>NexConsent</strong> replaces CPAPstore Consent. Deactivate CPAPstore Consent (your settings are kept), then NexConsent takes over.</p></div>';
    });
    return;
}
const CPC_VERSION = '1.3.1';
const NEXCONSENT_FILE = __FILE__;
// Everything else lives in includes/ so nothing is declared while the old plugin is still loaded.
require_once __DIR__ . '/includes/plugin.php';
require_once __DIR__ . '/includes/updater.php';
