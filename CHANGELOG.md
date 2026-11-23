# Changelog

## 2.0.0
Rewritten as a general-purpose plugin that any WordPress site can use.

- **Scripts after consent.** Paste any tracking code (Meta Pixel, TikTok, Clarity, Hotjar…); it runs only after the matching consent. `<noscript>` pixels are removed because they would load without consent.
- **Other services.** List third-party services and their cookies (`Meta Pixel | _fbp, _fbc`). They appear in the cookie table and are deleted on withdrawal.
- **Your own texts** for the title, message and buttons; empty fields keep the translated default.
- **Real translations** (`languages/*.po`): Greek and Bulgarian bundled; other languages through Polylang, WPML, Loco Translate or a `.po` file. Sites without a shop no longer mention a basket.
- **Floating “Cookie settings” button** after a choice, so visitors can change their mind. It was styled in 1.x but never printed. Links to `#cookie-settings` open the settings too.
- **Corner box** position as an alternative to the bottom bar.
- **Quick setup** also reports trackers that load before consent, more cookie banners, and the brand colour of most themes, not only Flatsome. Without a cookie policy page, the privacy policy page is linked.
- **White label** through `wp-config.php` constants or the `nexconsent_branding` filter; GitHub updates can be switched off.
- `NexConsent` JavaScript API with `onChange()`, `nexconsent:change` event, `nexconsent_has_consent()` in PHP, `nexconsent_*` filters.
- GTM package and data layer events renamed to `nexconsent_ready` / `nexconsent_update`.
- Optimiser exclusions for LiteSpeed, WP Rocket and Cloudflare are built in.
- The update check no longer sends the site address to GitHub.

**Upgrading from 1.x / CPAPstore Consent:** settings move over automatically and visitors keep their choices (same cookie and consent version). Old shortcodes, GTM events (`cpc_consent_*`), `CPAPConsent` and `data-cpc-category` keep working on those sites. The page cache is cleared once after the update. The floating button and the corner box are off for upgraded sites; switch them on in the settings.

## 1.3.3
- Withdrawing consent now also removes Google Ads / Analytics cookies set by other plugins (e.g. Google for WooCommerce), not only by the site's own GTM container.
- The cookie table names Google for WooCommerce when it is the Google Ads source.

## 1.3.2
- Optional “Reject optional” button on the first banner screen (Settings → NexConsent → Reject button). On by default for new installs; existing sites keep their current banner until it is switched on.

## 1.3.1
- Sites moving from CPAPstore Consent keep their banner colours (green or blue) instead of switching to blue.

## 1.3.0
- Renamed from CPAPstore Consent to **NexConsent** (by NexDev). Settings and visitors' existing choices carry over; the old plugin is switched off automatically when NexConsent is activated.
- Updates straight from GitHub releases (Dashboard → Updates), plus a "Check for updates" link on the Plugins screen.
- NexDev logo on the settings page and in the update screens.

## 1.2.1
- Banner colours: Blue, Green or a custom brand colour with an automatically generated, readable palette (WCAG AA). Fresh installs take the Flatsome primary colour.

## 1.2.0
- WooCommerce order attribution (`sbjs_*` cookies) only after marketing consent, through the WP Consent API when it is active or through NexConsent itself; switchable in the settings.
- Quick setup on first activation: GTM / GA4 IDs (only from real script loaders), cookie policy page, MailerLite, other cookie banners.
- Downloadable Google Tag Manager import package with consent triggers for non-Google tags.
- Security review fixes: loopback TLS policy, response size limit, nosniff on downloads, `uninstall.php`.
