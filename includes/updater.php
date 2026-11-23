<?php
/**
 * Updates from GitHub releases of nexdev-team/NexConsent (public repository, no token).
 *
 * Each release must carry an asset named "nexconsent.zip" whose top folder is "nexconsent/"
 * (the release workflow in .github/workflows/release.yml builds it from the tag).
 * The release tag is the version: "v1.3.1" or "1.3.1".
 */
defined('ABSPATH') || exit;

// Agencies that ship their own builds can switch GitHub updates off.
if (defined('NEXCONSENT_DISABLE_UPDATES') && NEXCONSENT_DISABLE_UPDATES) return;

const NEXCONSENT_REPO = 'nexdev-team/NexConsent';
const NEXCONSENT_SLUG = 'nexconsent';

function nexconsent_latest_release($force = false) {
    $cached = get_site_transient('nexconsent_release');
    if (!$force && is_array($cached)) return $cached ?: null;
    $resp = wp_remote_get('https://api.github.com/repos/' . NEXCONSENT_REPO . '/releases/latest', [
        'timeout' => 10,
        // No site address in the User-Agent: GitHub does not need to know which sites run the plugin.
        'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'NexConsent/' . NEXCONSENT_VERSION],
    ]);
    $release = [];
    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $version = is_array($data) ? ltrim((string) ($data['tag_name'] ?? ''), 'vV') : '';
        $package = '';
        foreach ((array) ($data['assets'] ?? []) as $asset) {
            $url = (string) ($asset['browser_download_url'] ?? '');
            // Only accept the expected asset, served by GitHub over HTTPS.
            if (($asset['name'] ?? '') === 'nexconsent.zip' && strpos($url, 'https://github.com/' . NEXCONSENT_REPO . '/releases/download/') === 0) { $package = $url; break; }
        }
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) && $package && empty($data['draft']) && empty($data['prerelease'])) {
            $release = [
                'version' => $version, 'package' => $package,
                'url' => esc_url_raw((string) ($data['html_url'] ?? 'https://github.com/' . NEXCONSENT_REPO)),
                'notes' => wp_kses_post((string) ($data['body'] ?? '')),
                'published' => (string) ($data['published_at'] ?? ''),
            ];
        }
    }
    // Cache failures briefly so an API outage never slows wp-admin down.
    set_site_transient('nexconsent_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
    return $release ?: null;
}

function nexconsent_plugin_file() { return plugin_basename(NEXCONSENT_FILE); }

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (!is_object($transient)) return $transient;
    $release = nexconsent_latest_release();
    $file = nexconsent_plugin_file();
    $item = (object) [
        'id' => 'github.com/' . NEXCONSENT_REPO, 'slug' => NEXCONSENT_SLUG, 'plugin' => $file,
        'new_version' => $release['version'] ?? NEXCONSENT_VERSION, 'url' => 'https://github.com/' . NEXCONSENT_REPO,
        'package' => $release['package'] ?? '', 'requires_php' => '7.4', 'tested' => get_bloginfo('version'),
        'icons' => nexconsent_brand()['credit'] ? ['1x' => plugins_url('assets/icon-128x128.png', NEXCONSENT_FILE), 'default' => plugins_url('assets/icon-128x128.png', NEXCONSENT_FILE)] : [],
    ];
    if ($release && version_compare($release['version'], NEXCONSENT_VERSION, '>')) {
        $transient->response[$file] = $item;
        unset($transient->no_update[$file]);
    } else {
        $transient->no_update[$file] = $item;
        unset($transient->response[$file]);
    }
    return $transient;
});

// "View details" popup in the Plugins / Updates screens.
add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information' || ($args->slug ?? '') !== NEXCONSENT_SLUG) return $result;
    $release = nexconsent_latest_release();
    $brand = nexconsent_brand();
    return (object) [
        'name' => $brand['name'], 'slug' => NEXCONSENT_SLUG, 'version' => $release['version'] ?? NEXCONSENT_VERSION,
        'author' => $brand['url'] ? '<a href="' . esc_url($brand['url']) . '">' . esc_html($brand['author']) . '</a>' : esc_html($brand['author']),
        'homepage' => $brand['credit'] ? 'https://github.com/' . NEXCONSENT_REPO : $brand['url'],
        'requires' => '6.2', 'requires_php' => '7.4', 'last_updated' => $release['published'] ?? '',
        'download_link' => $release['package'] ?? '',
        'sections' => [
            'description' => esc_html__('Lightweight, first-party cookie consent with Google Consent Mode v2 and script blocking.', 'nexconsent'),
            'changelog' => !empty($release['notes']) ? wpautop($release['notes']) : '<p>' . esc_html__('See the release notes.', 'nexconsent') . '</p>',
        ],
        'icons' => $brand['credit'] ? ['1x' => plugins_url('assets/icon-128x128.png', NEXCONSENT_FILE)] : [],
    ];
}, 10, 3);

// Safety net: if a package's top folder is not "nexconsent", rename it so the plugin keeps its slug.
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra = []) {
    if (($hook_extra['plugin'] ?? '') !== nexconsent_plugin_file()) return $source;
    global $wp_filesystem;
    $target = trailingslashit($remote_source) . NEXCONSENT_SLUG . '/';
    if (untrailingslashit($source) === untrailingslashit($target) || !$wp_filesystem) return $source;
    if (!$wp_filesystem->exists(trailingslashit($source) . 'nexconsent.php')) return new WP_Error('nexconsent_package', __('The update package is not valid.', 'nexconsent'));
    return $wp_filesystem->move($source, $target, true) ? $target : new WP_Error('nexconsent_package', __('Could not prepare the update.', 'nexconsent'));
}, 10, 4);

// "Check for updates" link next to the plugin, for when you just published a release.
add_filter('plugin_action_links_' . plugin_basename(NEXCONSENT_FILE), function ($links) {
    $url = wp_nonce_url(admin_url('admin-post.php?action=nexconsent_check_update'), 'nexconsent_check_update');
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'nexconsent') . '</a>';
    return $links;
});
add_action('admin_post_nexconsent_check_update', function () {
    if (!current_user_can('update_plugins')) wp_die(esc_html__('Sorry, you are not allowed to do that.', 'nexconsent'), 403);
    check_admin_referer('nexconsent_check_update');
    delete_site_transient('nexconsent_release');
    delete_site_transient('update_plugins');
    wp_update_plugins();
    wp_safe_redirect(admin_url('plugins.php'));
    exit;
});
