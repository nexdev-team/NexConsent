<p align="center"><img src="assets/icon-128x128.png" width="96" alt=""></p>

# NexConsent

Lightweight, first-party cookie consent for WordPress and WooCommerce.

- **Google Consent Mode v2.** Google Tag Manager or GA4 load only after consent, with the right default and update signals.
- **Blocks any other tracker until consent.** Paste the Meta Pixel, TikTok, Clarity or Hotjar code into the settings and it runs only after the visitor agrees.
- **Works on install.** It finds the GTM / GA4 ID, the cookie policy page, the theme colour, other cookie banners and trackers that load without consent.
- **WooCommerce aware.** Order attribution (`sbjs_*`) starts only after marketing consent, either through the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) or built in.
- **Translated.** English, Greek and Bulgarian are bundled. Other languages come from the site language through Polylang, WPML, Loco Translate or a `.po` file.
- **White label.** Agencies can rename it, remove the credit and turn off update checks from `wp-config.php`.
- **No account, no CDN, no tracking of its own.** About 17 KB of JavaScript and 6 KB of CSS, all served from your own site. Cached pages stay correct because the choice lives in the visitor's browser.

## Install

1. Download `nexconsent.zip` from the [latest release](https://github.com/nexdev-team/NexConsent/releases/latest).
2. WordPress → Plugins → Add New → Upload Plugin → choose the zip → Activate.
3. Open **Settings → NexConsent** and check what it found.

Remove any other cookie banner (CookieYes, Cookiebot, Complianz…). Also remove any plugin or theme snippet that loads the same GTM container: NexConsent must be the only thing that loads GTM, so it can wait for consent.

With no tracking configured, NexConsent shows no banner and loads nothing on the front end.

## Settings

| Section | What it does |
|---|---|
| Banner | Language, position (bottom bar or corner box), “Reject optional” button, floating “Cookie settings” button, colours (blue, green or your brand colour with an automatically readable palette). |
| Texts | Your own title, message and button labels. Leave a field empty to keep the translated default. |
| Tracking and services | GTM / GA4 ID, MailerLite, plugins that follow Consent Mode on their own, other services and their cookies, and scripts that run only after consent. |
| Policy and advanced | Cookie policy URL (defaults to the privacy policy page), consent revision, and how WooCommerce order attribution is switched. |

### Other services and scripts

*Other analytics / marketing services* lists third-party cookies, one service per line:

```
Meta Pixel | _fbp, _fbc
Microsoft Clarity | _clck, _clsk, CLID
```

The services show up in the cookie table, and their cookies are deleted when a visitor withdraws consent. `*` matches any ending.

*Scripts after consent* takes the tracking code exactly as the provider gives it. Only `<script>` elements are kept. `<noscript>` pixels and images are removed, because they would load without consent. Only users with the `unfiltered_html` capability can change these fields.

Adding or removing a service asks every visitor again, because they agreed to the previous list. Language, texts, colours and branding never ask again.

## Shortcodes and links

- `[nexconsent_cookie_table]`: an always-current table of cookies for the cookie policy page.
- `[nexconsent_settings]`: a “Cookie settings” button, for example in the footer.
- Any link to `#cookie-settings` (for example a menu item) opens the settings dialog.

## Google Tag Manager

Go to Settings → NexConsent → **Download GTM package**. Then in Tag Manager: Admin → Import container → choose the file → existing workspace → **Merge** → Rename conflicting.

Google tags (GA4, Google Ads, Conversion Linker) need no change. For other tags (Meta, Clarity, Hotjar…), replace the *All Pages* trigger with *Consent - Analytics allowed* or *Consent - Marketing allowed*.

The data layer receives `nexconsent_ready` on every page and `nexconsent_update` after each choice. Both carry `nexconsent_analytics` and `nexconsent_marketing` (true/false).

## For developers

Block any script until consent:

```html
<script type="text/plain" data-nxc-category="marketing">/* inline code */</script>
<script type="text/plain" data-nxc-category="analytics" data-nxc-src="https://example.com/tracker.js"></script>
```

JavaScript:

```js
NexConsent.get();                 // { necessary, analytics, marketing, decided }
NexConsent.open();                // open the settings dialog
NexConsent.onChange(state => {}); // after every choice
document.addEventListener('nexconsent:change', e => console.log(e.detail));
```

PHP:

```php
nexconsent_has_consent('marketing'); // the visitor's choice, server side
```

The WP Consent API is supported in both directions (`wp_has_consent()` and `wp_set_consent()`).

| Filter | Use |
|---|---|
| `nexconsent_services` | Add or change services (id, category, provider, cookies, purpose, duration). |
| `nexconsent_strings` | Change any front-end text, e.g. per language. |
| `nexconsent_policy_url` | Policy link per language or page. |
| `nexconsent_branding` | White-label values (see below). |
| `nexconsent_detected_accent` | Theme colour used on a fresh install. |
| `nexconsent_policy_slugs` | Page slugs searched for the cookie policy. |
| `nexconsent_purge_page_cache` (action) | Clear your own cache after an update. |

## White label

In `wp-config.php`:

```php
define('NEXCONSENT_WHITE_LABEL', true);              // no NexDev logo or credit
define('NEXCONSENT_BRAND_NAME', 'Cookie Consent');   // menu, plugin list, cookie table
define('NEXCONSENT_BRAND_AUTHOR', 'Your Agency');
define('NEXCONSENT_BRAND_URL', 'https://agency.example');
define('NEXCONSENT_BRAND_LOGO', 'https://agency.example/logo.png');
define('NEXCONSENT_DISABLE_UPDATES', true);          // no update checks against GitHub
```

The banner itself never shows any branding.

## Translations

`languages/` holds `nexconsent.pot` and the bundled Greek and Bulgarian translations. To add a language, copy the `.pot` to `nexconsent-{locale}.po` (e.g. `nexconsent-de_DE.po`), translate it, and run `python3 tools/po2mo.py`. Loco Translate and WordPress language packs work as well. The admin screens are in English and translatable through the same text domain.

## Updates

Sites get new versions as normal WordPress updates from this repository's releases. The Plugins screen also has a **Check for updates** link.

To publish a release:

1. Bump `Version:` and `NEXCONSENT_VERSION` in `nexconsent.php`, and add a `## x.y.z` section to `CHANGELOG.md`.
2. `git tag vX.Y.Z && git push origin vX.Y.Z`
3. The **Release** workflow runs the tests, builds `nexconsent.zip` and publishes the release.

Anyone who can publish a release here can ship code to every site running NexConsent. Keep write access to the repository small and protect tags.

## Coming from CPAPstore Consent / NexConsent 1.x

Update or upload as usual. Settings move over by themselves, and every visitor's existing choice stays valid. Old shortcodes, GTM events and scripts marked with `data-cpc-category` keep working on those sites. The page cache is cleared once after the update.

## Development

```
php tests/test-php.php
npm install --no-save jsdom@24 && node tests/test-js.js
python3 tools/po2mo.py   # after editing languages/*.po
```

## License

GPL-2.0-or-later.
