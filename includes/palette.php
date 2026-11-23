<?php
/**
 * Banner colours: two presets, or a full palette generated from one brand colour.
 */
defined('ABSPATH') || exit;

function nexconsent_palette_presets() {
    return [
        'blue' => ['text' => '#1b344a', 'border' => '#dbe6ee', 'shadow' => '#16365326', 'link' => '#176496', 'accent' => '#1e73be',
            'accent-hover' => '#176496', 'on-accent' => '#fff', 'quiet-border' => '#b8cad9', 'quiet-hover' => '#edf5fb', 'reopen-border' => '#c3d4e1',
            'reopen-shadow' => '#16365315', 'overlay' => '#102b45b3', 'close-bg' => '#edf3f8', 'cat-border' => '#e1e9ef', 'muted' => '#506578', 'locked-bg' => '#e9f3fb'],
        'green' => ['text' => '#15332b', 'border' => '#dae7df', 'shadow' => '#12302626', 'link' => '#146638', 'accent' => '#196c3b',
            'accent-hover' => '#12582f', 'on-accent' => '#fff', 'quiet-border' => '#b8cbbf', 'quiet-hover' => '#edf5ef', 'reopen-border' => '#c3d5c8',
            'reopen-shadow' => '#12302615', 'overlay' => '#08271ab3', 'close-bg' => '#eef4ef', 'cat-border' => '#e2ebe5', 'muted' => '#50665b', 'locked-bg' => '#eaf4ec'],
    ];
}

function nexconsent_hex($hex) {
    $hex = strtolower(trim((string) $hex));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $hex, $m)) $hex = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : '';
}
function nexconsent_hsl($hex) {
    $r = hexdec(substr($hex, 1, 2)) / 255; $g = hexdec(substr($hex, 3, 2)) / 255; $b = hexdec(substr($hex, 5, 2)) / 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2; $d = $max - $min; $h = 0; $s = 0;
    if ($d > 0) {
        $s = $d / (1 - abs(2 * $l - 1));
        if ($max === $r) $h = 60 * fmod((($g - $b) / $d), 6); elseif ($max === $g) $h = 60 * ((($b - $r) / $d) + 2); else $h = 60 * ((($r - $g) / $d) + 4);
    }
    return [$h < 0 ? $h + 360 : $h, $s, $l];
}
function nexconsent_from_hsl($h, $s, $l) {
    $s = max(0, min(1, $s)); $l = max(0, min(1, $l));
    $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
    $seg = [[$c, $x, 0], [$x, $c, 0], [0, $c, $x], [0, $x, $c], [$x, 0, $c], [$c, 0, $x]][(int) floor($h / 60) % 6];
    return sprintf('#%02x%02x%02x', round(($seg[0] + $m) * 255), round(($seg[1] + $m) * 255), round(($seg[2] + $m) * 255));
}
function nexconsent_luminance($hex) {
    $f = function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); };
    return 0.2126 * $f(hexdec(substr($hex, 1, 2))) + 0.7152 * $f(hexdec(substr($hex, 3, 2))) + 0.0722 * $f(hexdec(substr($hex, 5, 2)));
}
function nexconsent_contrast($a, $b) { $x = nexconsent_luminance($a); $y = nexconsent_luminance($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }

function nexconsent_palette() {
    $o = nexconsent_options(); $presets = nexconsent_palette_presets();
    $accent = nexconsent_hex($o['accent']);
    if ($o['theme'] !== 'custom' || !$accent) return $presets[$o['theme']] ?? $presets['blue'];
    list($h, $s, $l) = nexconsent_hsl($accent);
    // Darken until white button text meets WCAG AA (4.5:1).
    while (nexconsent_contrast($accent, '#ffffff') < 4.5 && $l > 0.05) { $l -= 0.02; $accent = nexconsent_from_hsl($h, $s, $l); }
    $tint = function ($sat, $light) use ($h, $s) { return nexconsent_from_hsl($h, min($s, $sat), $light); };
    return ['text' => $tint(0.45, 0.15), 'border' => $tint(0.30, 0.89), 'shadow' => $tint(0.55, 0.15) . '26', 'link' => nexconsent_from_hsl($h, $s, max(0.05, $l - 0.06)),
        'accent' => $accent, 'accent-hover' => nexconsent_from_hsl($h, $s, max(0.05, $l - 0.08)), 'on-accent' => '#fff',
        'quiet-border' => $tint(0.25, 0.79), 'quiet-hover' => $tint(0.45, 0.95), 'reopen-border' => $tint(0.25, 0.82),
        'reopen-shadow' => $tint(0.55, 0.15) . '15', 'overlay' => $tint(0.60, 0.12) . 'b3', 'close-bg' => $tint(0.30, 0.95),
        'cat-border' => $tint(0.25, 0.91), 'muted' => $tint(0.12, 0.40), 'locked-bg' => $tint(0.55, 0.94)];
}

function nexconsent_palette_css() {
    $vars = '';
    foreach (nexconsent_palette() as $k => $v) if (preg_match('/^[a-z-]+$/', $k) && preg_match('/^#[0-9a-f]{3,8}$/i', $v)) $vars .= '--nxc-' . $k . ':' . $v . ';';
    return '.nxc,.nxc-overlay,.nxc-reopen,.nxc-settings-link{' . $vars . '}';
}

/** Brand colour of the active theme, used to colour the banner on a fresh install. */
function nexconsent_theme_colour() {
    // Customizer settings of common themes (Flatsome, Porto, Astra-style, many others).
    foreach (['color_primary', 'primary_color', 'accent_color', 'theme_color', 'primary-color', 'skin_color'] as $mod) {
        $hex = nexconsent_hex(get_theme_mod($mod, ''));
        if ($hex) return apply_filters('nexconsent_detected_accent', $hex);
    }
    // Block themes and theme.json palettes.
    if (function_exists('wp_get_global_settings')) {
        $palette = (array) wp_get_global_settings(['color', 'palette']);
        foreach (['custom', 'theme'] as $origin) foreach ((array) ($palette[$origin] ?? []) as $entry) {
            if (in_array($entry['slug'] ?? '', ['primary', 'accent', 'brand', 'accent-1'], true) && ($hex = nexconsent_hex($entry['color'] ?? ''))) {
                return apply_filters('nexconsent_detected_accent', $hex);
            }
        }
    }
    return (string) apply_filters('nexconsent_detected_accent', '');
}
