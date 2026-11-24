const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const core = fs.readFileSync(require('path').join(__dirname, '../assets/consent-core.js'), 'utf8');
const ui = fs.readFileSync(require('path').join(__dirname, '../assets/consent-ui.js'), 'utf8');

// Minimal model of WooCommerce 11.3.1 order-attribution.js semantics (init + setOrderTracking).
const wcOA = `(function(wca){var p=wca.params;window.__inits=window.__inits||0;window.__calls=window.__calls||[];
wca.setOrderTracking=function(allow){window.__calls.push(allow);p.allowTracking=allow;
 if(!allow){['sbjs_current','sbjs_first','sbjs_session'].forEach(function(n){document.cookie=n+'=; path=/; max-age=-999; domain=.'+location.hostname+';';document.cookie=n+'=; path=/; max-age=-999;';});}
 else{sbjs.init();}};
wca.setOrderTracking(p.allowTracking);})(window.wc_order_attribution);`;
const sbjs = `window.sbjs={init:function(){window.__inits++; if(!/sbjs_current=/.test(document.cookie)) document.cookie='sbjs_current=typ%3Dorganic; path=/'; if(!/sbjs_first=/.test(document.cookie)) document.cookie='sbjs_first=typ%3Dorganic; path=/';}};`;
const inlineBefore = 'if(window.wc_order_attribution&&wc_order_attribution.params){wc_order_attribution.params.allowTracking=!!(window.NexConsent&&NexConsent.get().marketing);}';

function page(bridge, cookies, opts) {
  opts = opts || {};
  const config = { legacy: !!opts.legacy, cookie: opts.legacy ? 'cpapstore_consent' : 'nexconsent', maxAge: 15552000, version: 'v1', categories: ['analytics', 'marketing'], gtm: opts.gtm || '', gtmExternal: !!opts.gtmExternal, ga: '',
    patterns: { analytics: ['_ga', '_ga_*'], marketing: ['_gcl_*', 'sbjs_*'] }, wpCookiePrefix: 'wp_consent', wpApi: false, bridge };
  const html = `<!doctype html><html><head><script id="nxc-config" type="application/json">${JSON.stringify(config)}</script>
${opts.head || ''}<script>${core}</script></head><body><main>x</main>${opts.body || ''}
<section id="nxc-banner" hidden><button data-nxc="accept">A</button><button data-nxc="reject">R</button><button data-nxc="open">S</button></section>
<div id="nxc-overlay" hidden><section role="dialog" tabindex="-1"><input type="checkbox" data-nxc-choice="analytics"><input type="checkbox" data-nxc-choice="marketing"><button data-nxc="save">Save</button></section></div><button id="nxc-reopen" data-nxc="open" hidden>C</button>
<script>var wc_order_attribution={params:{allowTracking:false,lifetime:1e-5,session:30,base64:false,prefix:'wc_order_attribution_'},fields:{}};</script>
${bridge === 'builtin' ? `<script>${inlineBefore}</script>` : ''}
<script>${sbjs}</script><script>${wcOA}</script><script>${ui}</script></body></html>`;
  // jsdom cannot navigate; a reload shows up as a "not implemented: navigation" error.
  const virtualConsole = new VirtualConsole(); let win;
  virtualConsole.on('jsdomError', e => { if (/navigation/.test(e.message) && win) win.__reloaded = true; else console.error(e.message); });
  const dom = new JSDOM(html, { url: 'https://shop.example.com/', runScripts: 'dangerously', pretendToBeVisual: true, virtualConsole,
    beforeParse(w) { win = w; (cookies || []).forEach(c => { w.document.cookie = c; }); } });
  return dom.window;
}
const wait = ms => new Promise(r => setTimeout(r, ms));
const consentCookie = (m, name) => (name || 'nexconsent') + '=' + encodeURIComponent(JSON.stringify({ v: 'v1', ts: Date.now() - 1000, analytics: true, marketing: m })) + '; path=/';
let fails = 0;
function check(name, cond, extra) { console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  ' + extra : '')); if (!cond) fails++; }

(async () => {
  // 1. New visitor, builtin
  let w = page('builtin'); await wait(700);
  check('builtin new visitor: no sbjs before consent', !/sbjs_/.test(w.document.cookie), w.document.cookie);
  check('builtin new visitor: banner visible', w.document.getElementById('nxc-banner').hidden === false);
  check('nexconsent_ready pushed', w.dataLayer.some(x => x && x.event === 'nexconsent_ready' && x.nexconsent_marketing === false));
  w.document.querySelector('[data-nxc="accept"]').click(); await wait(50);
  check('builtin accept: sbjs written', /sbjs_current=/.test(w.document.cookie), w.document.cookie);
  check('builtin accept: setOrderTracking(true) called', w.__calls.includes(true), JSON.stringify(w.__calls));
  check('builtin accept: nexconsent_update pushed', w.dataLayer.some(x => x && x.event === 'nexconsent_update' && x.nexconsent_marketing === true));
  // withdraw
  w.NexConsent.save({ analytics: true, marketing: false }); await wait(50);
  check('builtin withdraw: sbjs removed', !/sbjs_/.test(w.document.cookie), w.document.cookie);

  // 1b. Reject button on the first screen
  w = page('builtin'); await wait(700);
  w.document.querySelector('[data-nxc="reject"]').click(); await wait(50);
  const st = w.NexConsent.get();
  check('reject button: decided, nothing allowed, banner hidden', st.decided && !st.analytics && !st.marketing && w.document.getElementById('nxc-banner').hidden === true);
  check('reject button: no sbjs written', !/sbjs_/.test(w.document.cookie), w.document.cookie);

  // 2. Returning visitor with marketing consent and existing attribution
  w = page('builtin', [consentCookie(true), 'sbjs_first=typ%3Dreferral%7C%7C%7Csrc%3Dgoogle; path=/']); await wait(700);
  check('builtin returning: original sbjs_first kept', /sbjs_first=typ%3Dreferral/.test(w.document.cookie), w.document.cookie);
  check('builtin returning: WC initialised with true, no extra re-init', w.__calls.length === 1 && w.__calls[0] === true && w.__inits === 1, JSON.stringify(w.__calls) + ' inits=' + w.__inits);

  // 3. Returning visitor who rejected marketing, with stale sbjs cookie
  w = page('builtin', [consentCookie(false), 'sbjs_current=typ%3Dx; path=/']); await wait(700);
  check('builtin returning reject: stale sbjs removed', !/sbjs_/.test(w.document.cookie), w.document.cookie);

  // 4. wpca mode: plugin must not touch order attribution itself
  w = page('wpca'); await wait(700);
  check('wpca mode: syncAttribution does nothing', w.__calls.length === 1 && w.__calls[0] === false, JSON.stringify(w.__calls));
  w.document.querySelector('[data-nxc="accept"]').click(); await wait(50);
  check('wpca mode: no direct setOrderTracking on accept', w.__calls.length === 1, JSON.stringify(w.__calls));

  // 5. Delayed footer scripts (cache plugin delays until later): builtin applies when WC appears
  const config = { cookie: 'nexconsent', maxAge: 15552000, version: 'v1', categories: ['analytics', 'marketing'], gtm: '', ga: '', patterns: { analytics: [], marketing: ['sbjs_*'] }, wpCookiePrefix: 'wp_consent', wpApi: false, bridge: 'builtin' };
  const dom = new JSDOM(`<!doctype html><html><head><script id="nxc-config" type="application/json">${JSON.stringify(config)}</script><script>${core}</script></head><body></body></html>`,
    { url: 'https://shop.example.com/', runScripts: 'dangerously', beforeParse(x) { x.document.cookie = consentCookie(true); } });
  w = dom.window; await wait(300);
  // inject delayed scripts without the inline "before" (worst case: inline was stripped)
  [`var wc_order_attribution={params:{allowTracking:false},fields:{}};`, sbjs, wcOA].forEach(code => { const s = w.document.createElement('script'); s.textContent = code; w.document.body.appendChild(s); });
  await wait(800);
  check('delayed scripts: tracking switched on after they load', w.__calls[w.__calls.length - 1] === true && /sbjs_current=/.test(w.document.cookie), JSON.stringify(w.__calls));

  // 6. Scripts after consent: inline and external, only for the allowed category
  w = page('builtin', [], { body: '<script type="text/plain" data-nxc-category="marketing">window.__mk=(window.__mk||0)+1</script><script type="text/plain" data-nxc-category="analytics" data-nxc-src="https://cdn.example.com/a.js" data-nxc-id="ax"></script>' });
  await wait(300);
  check('blocked scripts do not run before consent', !w.__mk && !w.document.getElementById('ax'));
  w.NexConsent.save({ analytics: false, marketing: true }); await wait(50);
  check('marketing script runs once after marketing consent', w.__mk === 1);
  check('analytics script still blocked', !w.document.getElementById('ax'));
  w.NexConsent.refresh(); await wait(20);
  check('activated scripts never run twice', w.__mk === 1);
  check('reopen button shown after a choice', w.document.getElementById('nxc-reopen').hidden === false);

  // 7. Sites coming from 1.x: old cookie, old events and old API name keep working
  w = page('builtin', [consentCookie(true, 'cpapstore_consent')], { legacy: true, body: '<script type="text/plain" data-cpc-category="marketing">window.__old=1</script>' });
  await wait(300);
  check('legacy: existing 1.x choice honoured (no banner)', w.document.getElementById('nxc-banner').hidden === true && w.NexConsent.get().marketing === true);
  check('legacy: old dataLayer events still pushed', w.dataLayer.some(x => x && x.event === 'cpc_consent_ready') && w.dataLayer.some(x => x && x.event === 'nexconsent_ready'));
  check('legacy: CPAPConsent alias and data-cpc scripts', w.CPAPConsent === w.NexConsent && w.__old === 1);
  w = page('builtin');
  await wait(300);
  check('new sites: no old names at all', !w.CPAPConsent && !w.dataLayer.some(x => x && /^cpc_/.test(x.event || '')));

  // 8. Any link to #cookie-settings opens the dialog
  w = page('builtin', [], { body: '<a id="lnk" href="/privacy/#cookie-settings">Cookie settings</a>' });
  await wait(300);
  w.document.getElementById('lnk').click(); await wait(20);
  check('#cookie-settings link opens the dialog', w.document.getElementById('nxc-overlay').hidden === false);

  // 9. GTM loaded once only
  const gtmTags = win => [...win.document.querySelectorAll('script[src*="gtm.js"]')].length;
  const gtmEvents = win => win.dataLayer.filter(x => x && x.event === 'gtm.js').length;
  w = page('builtin', [], { gtm: 'GTM-TEST1' }); await wait(300);
  check('plugin loader: no GTM before consent', gtmTags(w) === 0);
  w.NexConsent.save({ analytics: true, marketing: true }); await wait(50);
  check('plugin loader: GTM loaded once after consent', gtmTags(w) === 1 && gtmEvents(w) === 1);
  w.NexConsent.refresh(); await wait(20);
  check('plugin loader: never twice', gtmTags(w) === 1 && gtmEvents(w) === 1);

  const gateway = `<script>(function(w,i,g){w[g]=w[g]||[];w[g].push(i)})(window,'GTM-TEST1','google_tags_first_party');</script>`;
  w = page('builtin', [], { gtm: 'GTM-TEST1', head: gateway }); await wait(300);
  w.NexConsent.save({ analytics: true, marketing: true }); await wait(50);
  check('GTM from Google tag gateway detected: not loaded again', gtmTags(w) === 0 && gtmEvents(w) === 0);
  check('...and accepting needs no reload', !w.__reloaded);
  check('...consent update sent', w.dataLayer.some(x => x && x[0] === 'consent' && x[1] === 'update' && x[2].ad_storage === 'granted'));

  w = page('builtin', [], { gtm: 'GTM-TEST1', head: '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-TEST1&gtg_health=1"></script>' }); await wait(300);
  w.NexConsent.save({ analytics: true, marketing: false }); await wait(50);
  check('GTM script already on the page: not loaded again', gtmTags(w) === 1 && gtmEvents(w) === 0);

  w = page('builtin', [consentCookie(true)], { gtm: 'GTM-TEST1', gtmExternal: true }); await wait(300);
  check('external loader: never injects GTM, even with consent', gtmTags(w) === 0);
  check('external loader: sends the gtm.js event once (page view triggers)', gtmEvents(w) === 1);
  check('external loader: stored consent sent as update', w.dataLayer.some(x => x && x[0] === 'consent' && x[1] === 'update' && x[2].ad_storage === 'granted'));
  w.NexConsent.save({ analytics: false, marketing: false }); await wait(50);
  check('external loader: withdrawal reloads to stop running tags', w.__reloaded === true);

  w = page('builtin', [], { gtm: 'GTM-TEST1', gtmExternal: true, body: `<script>dataLayer.push({'gtm.start':1,event:'gtm.js'});</script>` }); await wait(300);
  check('external loader: no second gtm.js event when another snippet sent one', gtmEvents(w) === 1);
  w = page('builtin', [], { gtm: 'GTM-TEST1', gtmExternal: true }); await wait(300);
  check('external loader: gtm.js event also before consent (tags wait via Consent Mode)', gtmEvents(w) === 1 && gtmTags(w) === 0);
  console.log(fails ? `\n${fails} FAILED` : '\nALL PASSED');
  process.exit(fails ? 1 : 0);
})();
