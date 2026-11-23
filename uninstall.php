<?php
// Runs only when the plugin is deleted from the Plugins screen.
defined('WP_UNINSTALL_PLUGIN') || exit;
delete_option('cpc_options');
delete_transient('cpc_needs_autosetup');
delete_transient('cpc_autosetup_report');
