/*! NexConsent — first-party cookie consent. GPL-2.0-or-later. */
(function (w, d) {
  'use strict';
  if (w.NexConsent) return;
  var el = d.getElementById('nxc-config'), config;
  try { config = JSON.parse(el.textContent); } catch (_) { return; }
  var state = { analytics: false, marketing: false }, decided = false;
  var listeners = [], loaded = {}, maxAge = config.maxAge * 1000, consentTimestamp = 0;
  w.dataLayer = w.dataLayer || [];
  w.wp_consent_type = 'optin';
  w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };

  function signals() {
    return {
      analytics_storage: state.analytics ? 'granted' : 'denied',
      ad_storage: state.marketing ? 'granted' : 'denied',
      ad_user_data: state.marketing ? 'granted' : 'denied',
      ad_personalization: state.marketing ? 'granted' : 'denied',
      functionality_storage: 'granted', security_storage: 'granted',
      personalization_storage: 'denied'
    };
  }
  function cookieValue() {
    var item = d.cookie.split(';').map(function (s) { return s.trim(); }).find(function (s) { return s.indexOf(config.cookie + '=') === 0; });
    return item ? item.slice(config.cookie.length + 1) : '';
  }
  function read() {
    try {
      var x = JSON.parse(decodeURIComponent(cookieValue())), now = Date.now();
      if (x.v !== config.version || !Number.isFinite(x.ts) || x.ts > now || now - x.ts >= maxAge) return null;
      if (typeof x.analytics !== 'boolean' || typeof x.marketing !== 'boolean') return null;
      return x;
    } catch (_) { return null; }
  }
  function allowed(category) { return config.categories.indexOf(category) !== -1 && state[category] === true; }
  function cleanCookies(category) {
    var names = d.cookie.split(';').map(function (s) { return s.trim().split('=')[0]; });
    var domains = [''], host = w.location.hostname.split('.');
    for (var i = 0; i < host.length - 1; i++) domains.push(host.slice(i).join('.'), '.' + host.slice(i).join('.'));
    var paths = ['/'], parts = w.location.pathname.split('/');
    for (var p = 1; p < parts.length; p++) { var path = parts.slice(0, p + 1).join('/'); if (path) paths.push(path, path + '/'); }
    (config.patterns[category] || []).forEach(function (pattern) {
      var re = new RegExp('^' + pattern.split('*').map(function (s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('.*') + '$');
      names.filter(function (name) { return re.test(name); }).forEach(function (name) {
        domains.forEach(function (domain) { paths.forEach(function (path) {
          d.cookie = name + '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=' + path + (domain ? '; Domain=' + domain : '') + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
        }); });
      });
    });
  }
  function emit() {
    syncWordPress();
    syncAttribution();
    // GTM template callbacks run synchronously before the next dataLayer event.
    listeners.slice().forEach(function (fn) { try { fn(signals()); } catch (_) {} });
    var detail = { analytics: state.analytics, marketing: state.marketing, decided: decided };
    d.dispatchEvent(new CustomEvent('nexconsent:change', { detail: detail }));
    if (config.legacy) d.dispatchEvent(new CustomEvent('cpapstore:consent', { detail: detail }));
  }
  // dataLayer events for GTM triggers of non-Google tags; sites coming from 1.x also get the old names.
  function pushEvent(kind) {
    var item = { event: 'nexconsent_' + kind, nexconsent_analytics: state.analytics, nexconsent_marketing: state.marketing };
    if (kind === 'ready') item.nexconsent_decided = decided;
    w.dataLayer.push(item);
    if (config.legacy) {
      var old = { event: 'cpc_consent_' + kind, cpc_analytics: state.analytics, cpc_marketing: state.marketing };
      if (kind === 'ready') old.cpc_decided = decided;
      w.dataLayer.push(old);
    }
  }
  function syncWordPress() {
    if (!config.wpApi && typeof w.wp_set_consent !== 'function') return;
    var prefix = config.wpCookiePrefix || 'wp_consent';
    var values = { functional: 'allow', preferences: 'deny', statistics: state.analytics ? 'allow' : 'deny',
      'statistics-anonymous': state.analytics ? 'allow' : 'deny', marketing: state.marketing ? 'allow' : 'deny' };
    var seconds = decided ? Math.max(0, Math.floor((consentTimestamp + maxAge - Date.now()) / 1000)) : config.maxAge;
    Object.keys(values).forEach(function (category) {
      // Let the API publish change events, then bound its auxiliary cookie to the original decision.
      if (typeof w.wp_set_consent === 'function') w.wp_set_consent(category, values[category]);
      d.cookie = prefix + '_' + category + '=' + values[category] + '; Path=/; Max-Age=' + seconds + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
    });
    d.dispatchEvent(new CustomEvent('wp_consent_type_defined'));
  }
  // Built-in bridge: switch WooCommerce order attribution (sourcebuster sbjs_* cookies) directly.
  // The server always prints allowTracking=false in this mode, so cached pages are safe.
  var attributionApplied = null;
  function syncAttribution() {
    if (config.bridge !== 'builtin') return;
    var wca = w.wc_order_attribution;
    if (!wca || !wca.params) return;
    var allow = allowed('marketing');
    if (typeof wca.setOrderTracking !== 'function') { wca.params.allowTracking = allow; return; }
    if (attributionApplied === allow) return;
    // WooCommerce already initialised with the right value (set before its script ran): don't re-init.
    if (attributionApplied === null && wca.params.allowTracking === allow) { attributionApplied = allow; return; }
    attributionApplied = allow;
    try { wca.setOrderTracking(allow); } catch (_) {}
    if (!allow) cleanCookies('marketing');
  }
  function inject(src, key) {
    if (loaded[key]) return;
    loaded[key] = true;
    var script = d.createElement('script'); script.async = true; script.src = src;
    script.setAttribute('data-nxc-loaded', key); script.setAttribute('data-cfasync', 'false');
    d.head.appendChild(script);
  }
  // <script type="text/plain" data-nxc-category="analytics|marketing" [data-nxc-src] [data-nxc-id] [data-nxc-type] [data-nxc-async]>
  var prefixes = config.legacy ? ['nxc', 'cpc'] : ['nxc'];
  function activateNodes() {
    prefixes.forEach(function (p) {
      d.querySelectorAll('script[type="text/plain"][data-' + p + '-category]').forEach(function (node) {
        var category = node.getAttribute('data-' + p + '-category');
        if (!allowed(category) || node.getAttribute('data-nxc-activated') === '1') return;
        node.setAttribute('data-nxc-activated', '1');
        var script = d.createElement('script'), src = node.getAttribute('data-' + p + '-src'), id = node.getAttribute('data-' + p + '-id');
        if (node.getAttribute('data-nxc-type') === 'module') script.type = 'module';
        script.async = node.getAttribute('data-' + p + '-async') !== 'false';
        if (src) script.src = src; else script.textContent = node.textContent;
        if (id) script.id = id;
        if (node.nonce) script.nonce = node.nonce;
        script.setAttribute('data-nxc-loaded', category);
        // Insert where the placeholder is, so scripts keep their order and position.
        (node.parentNode || d.head).insertBefore(script, node.nextSibling);
        loaded['custom-' + category] = true;
      });
    });
  }
  // GTM already on the page from another source (CDN / Google tag gateway, theme, other plugin)?
  // Loading it again would fire every tag twice.
  function gtmPresent() {
    if (w.google_tag_manager && w.google_tag_manager[config.gtm]) return true;
    var first = w.google_tags_first_party;
    if (first && typeof first.indexOf === 'function' && first.indexOf(config.gtm) !== -1) return true;
    return !!d.querySelector('script[src*="gtm.js?id=' + config.gtm + '"]:not([data-nxc-loaded])');
  }
  function load() {
    if (config.gtm && config.gtmExternal) {
      // Another service loads gtm.js (e.g. Google tag gateway). GTM still needs the 'gtm.js' event of the
      // standard snippet for page view triggers. Checked once the page is parsed, so a snippet printed
      // later in <head> (theme, another plugin) is seen and the event is never sent twice.
      if (!loaded.gtmEvent) {
        loaded.gtmEvent = true;
        var pushGtmEvent = function () {
          if (!w.dataLayer.some(function (x) { return x && x.event === 'gtm.js'; })) w.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
        };
        if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', pushGtmEvent); else pushGtmEvent();
      }
    } else if (config.gtm && (allowed('analytics') || allowed('marketing'))) {
      if (!loaded.gtm && gtmPresent()) { loaded.gtm = 'external'; }
      if (!loaded.gtm) w.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      inject('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(config.gtm), 'gtm');
    } else if (!config.gtm && config.ga && allowed('analytics')) {
      if (!loaded.ga) { w.gtag('js', new Date()); w.gtag('config', config.ga); }
      inject('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.ga), 'ga');
    }
    activateNodes();
  }
  function save(choice) {
    var previous = { analytics: state.analytics, marketing: state.marketing };
    var next = { analytics: config.categories.indexOf('analytics') !== -1 && choice.analytics === true, marketing: config.categories.indexOf('marketing') !== -1 && choice.marketing === true };
    var record = { v: config.version, ts: Date.now(), analytics: next.analytics, marketing: next.marketing };
    var value = encodeURIComponent(JSON.stringify(record));
    d.cookie = config.cookie + '=' + value + '; Path=/; Max-Age=' + config.maxAge + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
    if (cookieValue() !== value) {
      state = { analytics: false, marketing: false }; decided = false;
      w.gtag('consent', 'update', signals()); cleanCookies('analytics'); cleanCookies('marketing'); emit();
      if (Object.keys(loaded).length) w.location.reload();
      return false;
    }
    state = next; decided = true; consentTimestamp = record.ts;
    w.gtag('consent', 'update', signals());
    if (!state.analytics) cleanCookies('analytics');
    if (!state.marketing) cleanCookies('marketing');
    emit();
    // Stop previously activated third-party code on withdrawal: denial, cleanup, then reload.
    if ((previous.analytics && !next.analytics || previous.marketing && !next.marketing) && (Object.keys(loaded).length || config.gtmExternal)) {
      w.location.reload(); return true;
    }
    // Reinitialize category-specific tags after adding consent to an already loaded container.
    if (loaded.gtm === true && ((!previous.analytics && next.analytics) || (!previous.marketing && next.marketing))) {
      w.location.reload(); return true;
    }
    load();
    pushEvent('update');
    return true;
  }
  w.gtag('consent', 'default', signals());
  w.gtag('set', 'ads_data_redaction', true);
  var existing = read();
  if (existing) {
    decided = true; consentTimestamp = existing.ts;
    state.analytics = config.categories.indexOf('analytics') !== -1 && existing.analytics;
    state.marketing = config.categories.indexOf('marketing') !== -1 && existing.marketing;
    w.gtag('consent', 'update', signals());
  }
  // Publish denial/restored choices before dependent scripts can read stale auxiliary permissions.
  syncWordPress();
  // WooCommerce events generated before consent must never be replayed on a later grant.
  var ecommerceEvents = /^(view_item_list|select_item|view_item|add_to_cart|view_cart|remove_from_cart|begin_checkout|add_billing_info|add_payment_info|add_shipping_info|purchase|payment_failed|login|abandon_cart|abandon_checkout|user_data|page_view)$/;
  var push = w.dataLayer.push;
  w.dataLayer.push = function () {
    var items = Array.prototype.slice.call(arguments).filter(function (item) {
      return state.analytics || state.marketing || !item || !(item.ecommerce || ecommerceEvents.test(item.event || ''));
    });
    return push.apply(w.dataLayer, items);
  };
  // Current state for GTM triggers of non-Google tags (see the GTM package in the plugin settings).
  pushEvent('ready');
  if (!state.analytics) cleanCookies('analytics');
  if (!state.marketing) cleanCookies('marketing');
  // Public API: NexConsent.get(), .save({analytics, marketing}), .open(), .onChange(fn).
  w.NexConsent = {
    get: function () { return { necessary: true, analytics: state.analytics, marketing: state.marketing, decided: decided, bridge: config.bridge }; },
    save: save,
    open: function () { d.dispatchEvent(new CustomEvent('nexconsent:open')); },
    onChange: function (fn) { d.addEventListener('nexconsent:change', function (e) { fn(e.detail); }); },
    subscribeGoogle: function (fn) { listeners.push(fn); fn(signals()); },
    refresh: load,
    syncWordPress: syncWordPress,
    syncAttribution: syncAttribution
  };
  if (config.legacy) w.CPAPConsent = w.NexConsent;
  function reconcile() {
    var record = read(), next = { analytics: !!(record && record.analytics && config.categories.indexOf('analytics') !== -1),
      marketing: !!(record && record.marketing && config.categories.indexOf('marketing') !== -1) };
    if (decided === !!record && state.analytics === next.analytics && state.marketing === next.marketing) return;
    state = next; decided = !!record; consentTimestamp = record ? record.ts : 0;
    w.gtag('consent', 'update', signals());
    if (!state.analytics) cleanCookies('analytics');
    if (!state.marketing) cleanCookies('marketing');
    emit();
    if (Object.keys(loaded).length) w.location.reload(); else load();
  }
  if (typeof w.addEventListener === 'function') w.addEventListener('focus', reconcile);
  d.addEventListener('visibilitychange', function () { if (!d.hidden) reconcile(); });
  if (typeof w.setTimeout === 'function') {
    (function checkExpiry() {
      reconcile();
      var remaining = decided ? consentTimestamp + maxAge - Date.now() + 50 : 86400000;
      w.setTimeout(checkExpiry, Math.max(1000, Math.min(remaining, 86400000)));
    })();
  }
  load();
  d.addEventListener('DOMContentLoaded', function () { syncWordPress(); syncAttribution(); load(); }, { once: true });
  // Cache plugins may delay footer scripts until load or first interaction: keep checking for a while.
  if (config.bridge === 'builtin' && typeof w.setInterval === 'function') {
    var tries = 0, timer = w.setInterval(function () {
      syncAttribution();
      if (attributionApplied !== null || ++tries > 120) w.clearInterval(timer);
    }, 500);
  }
})(window, document);
