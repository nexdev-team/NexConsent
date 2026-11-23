<?php
// Runs only when the plugin is deleted from the Plugins screen.
defined('WP_UNINSTALL_PLUGIN') || exit;
foreach (['nexconsent_options', 'nexconsent_version', 'cpc_options'] as $option) delete_option($option);
foreach (['nexconsent_needs_setup', 'nexconsent_setup_report', 'cpc_needs_autosetup', 'cpc_autosetup_report'] as $transient) delete_transient($transient);
delete_site_transient('nexconsent_release');
