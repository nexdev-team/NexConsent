<p align="center"><img src="assets/icon-128x128.png" width="96" alt="NexDev"></p>

# NexConsent

Lightweight, first-party cookie consent for WordPress and WooCommerce, by [NexDev](https://nexdev.gr).

- Google Consent Mode v2; GTM / GA4 load only after consent.
- Bulgarian, Greek and English copy, automatic from the site language.
- WooCommerce order attribution (`sbjs_*`) only after marketing consent — through the WP Consent API when it is active, or built in.
- No subscriptions, no CDN, no tracking of its own. About 14 KB of JS + 5 KB of CSS.
- Works with LiteSpeed Cache and Cloudflare.

## Install

1. Download `nexconsent.zip` from the [latest release](https://github.com/nexdev-team/NexConsent/releases/latest).
2. WordPress → Plugins → Add New → Upload Plugin → choose the zip → Activate.
3. Done. On the first admin page after activation NexConsent finds the GTM / GA4 ID, the cookie policy page, MailerLite and the theme colour by itself. Review them under **Settings → NexConsent**.

Remove any other cookie banner (CookieYes, Cookiebot, Complianz…) and any other plugin or theme snippet that loads the same GTM container: NexConsent must be the only thing that loads GTM so it can wait for consent.

### Coming from CPAPstore Consent

Upload and activate NexConsent. The old plugin is switched off automatically and all settings and visitors' choices are kept. Delete *CPAPstore Consent* afterwards.

## Updates

Sites get new versions as normal WordPress updates (Dashboard → Updates) from this repository's releases. The Plugins screen has a **Check for updates** link to look straight away.

### Publishing a release

1. Bump `Version:` and `CPC_VERSION` in `nexconsent.php`, add a `## x.y.z` section to `CHANGELOG.md`.
2. `git tag vX.Y.Z && git push origin vX.Y.Z`
3. The **Release** workflow runs the tests, builds `nexconsent.zip` and publishes the release.

Anyone who can publish a release here can ship code to every site running NexConsent: keep the repository's write access small and protect tags.

## Google Tag Manager

Settings → NexConsent → **Download GTM package**, then in Tag Manager: Admin → Import container → choose the file → existing workspace → **Merge** → Rename conflicting.

Google tags (GA4, Ads, Conversion Linker) need no change. For other tags (Meta, Clarity, Hotjar…) use the trigger *CPC - Analytics allowed* or *CPC - Marketing allowed* instead of *All Pages*.

## Shortcodes

- `[nexconsent_cookie_table]` — table of cookies for the cookie policy.
- `[nexconsent_settings]` — "Cookie settings" button for the footer.

The older `[cpapstore_cookie_table]` and `[cpapstore_cookie_settings]` keep working.

## Development

```
php tests/test-php.php
npm install --no-save jsdom@24 && node tests/test-js.js
```
