<?php
/**
 * Plugin Name: NexConsent
 * Plugin URI: https://github.com/nexdev-team/NexConsent
 * Description: Lightweight, first-party cookie consent for WordPress and WooCommerce. Google Consent Mode v2, script blocking, WP Consent API, no subscriptions and no CDN.
 * Version: 2.0.2
 * Author: NexDev
 * Author URI: https://nexdev.gr
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexconsent
 * Domain Path: /languages
 * Update URI: https://github.com/nexdev-team/NexConsent
 */
defined('ABSPATH') || exit;

// The predecessor "CPAPstore Consent" defines CPC_VERSION. If it is still active, step aside
// (two banners would show) and switch it off when NexConsent is activated.
if (defined('CPC_VERSION')) {
    register_activation_hook(__FILE__, function () {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins('cpapstore-consent/cpapstore-consent.php', true);
    });
    add_action('admin_notices', function () {
        if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p>' . esc_html__('NexConsent is waiting: deactivate the old consent plugin (CPAPstore Consent). Its settings are kept.', 'nexconsent') . '</p></div>';
    });
    return;
}

const NEXCONSENT_VERSION = '2.0.2';
const NEXCONSENT_FILE = __FILE__;

require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/palette.php';
require_once __DIR__ . '/includes/branding.php';
require_once __DIR__ . '/includes/updater.php';
if (is_admin()) require_once __DIR__ . '/includes/admin.php';
