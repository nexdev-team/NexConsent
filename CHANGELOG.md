# Changelog

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
