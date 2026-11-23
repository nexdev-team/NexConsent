const { JSDOM } = require('jsdom');
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
const inlineBefore = 'if(window.wc_order_attribution&&wc_order_attribution.params){wc_order_attribution.params.allowTracking=!!(window.CPAPConsent&&CPAPConsent.get().marketing);}';

function page(bridge, cookies) {
  const config = { cookie: 'cpapstore_consent', maxAge: 15552000, version: 'v1', categories: ['analytics', 'marketing'], gtm: '', ga: '',
    patterns: { analytics: ['_ga', '_ga_*'], marketing: ['_gcl_*', 'sbjs_*'] }, wpCookiePrefix: 'wp_consent', wpApi: false, bridge };
  const html = `<!doctype html><html><head><script id="cpc-config" type="application/json">${JSON.stringify(config)}</script>
<script>${core}</script></head><body><main>x</main>
<section id="cpc-banner" hidden><button data-cpc="accept">A</button><button data-cpc="reject">R</button><button data-cpc="open">S</button></section>
<div id="cpc-overlay" hidden><section role="dialog" tabindex="-1"><input type="checkbox" data-cpc-category="analytics"><input type="checkbox" data-cpc-category="marketing"><button data-cpc="save">Save</button></section></div>
<script>var wc_order_attribution={params:{allowTracking:false,lifetime:1e-5,session:30,base64:false,prefix:'wc_order_attribution_'},fields:{}};</script>
${bridge === 'builtin' ? `<script>${inlineBefore}</script>` : ''}
<script>${sbjs}</script><script>${wcOA}</script><script>${ui}</script></body></html>`;
  const dom = new JSDOM(html, { url: 'https://shop.example.com/', runScripts: 'dangerously', pretendToBeVisual: true,
    beforeParse(w) { (cookies || []).forEach(c => { w.document.cookie = c; }); w.location.reload = () => { w.__reloaded = true; }; } });
  return dom.window;
}
const wait = ms => new Promise(r => setTimeout(r, ms));
const consentCookie = (m) => 'cpapstore_consent=' + encodeURIComponent(JSON.stringify({ v: 'v1', ts: Date.now() - 1000, analytics: true, marketing: m })) + '; path=/';
let fails = 0;
function check(name, cond, extra) { console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra ? '  ' + extra : '')); if (!cond) fails++; }

(async () => {
  // 1. New visitor, builtin
  let w = page('builtin'); await wait(700);
  check('builtin new visitor: no sbjs before consent', !/sbjs_/.test(w.document.cookie), w.document.cookie);
  check('builtin new visitor: banner visible', w.document.getElementById('cpc-banner').hidden === false);
  check('cpc_consent_ready pushed', w.dataLayer.some(x => x && x.event === 'cpc_consent_ready' && x.cpc_marketing === false));
  w.document.querySelector('[data-cpc="accept"]').click(); await wait(50);
  check('builtin accept: sbjs written', /sbjs_current=/.test(w.document.cookie), w.document.cookie);
  check('builtin accept: setOrderTracking(true) called', w.__calls.includes(true), JSON.stringify(w.__calls));
  check('builtin accept: cpc_consent_update pushed', w.dataLayer.some(x => x && x.event === 'cpc_consent_update' && x.cpc_marketing === true));
  // withdraw
  w.CPAPConsent.save({ analytics: true, marketing: false }); await wait(50);
  check('builtin withdraw: sbjs removed', !/sbjs_/.test(w.document.cookie), w.document.cookie);

  // 1b. Reject button on the first screen
  w = page('builtin'); await wait(700);
  w.document.querySelector('[data-cpc="reject"]').click(); await wait(50);
  const st = w.CPAPConsent.get();
  check('reject button: decided, nothing allowed, banner hidden', st.decided && !st.analytics && !st.marketing && w.document.getElementById('cpc-banner').hidden === true);
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
  w.document.querySelector('[data-cpc="accept"]').click(); await wait(50);
  check('wpca mode: no direct setOrderTracking on accept', w.__calls.length === 1, JSON.stringify(w.__calls));

  // 5. Delayed footer scripts (cache plugin delays until later): builtin applies when WC appears
  const config = { cookie: 'cpapstore_consent', maxAge: 15552000, version: 'v1', categories: ['analytics', 'marketing'], gtm: '', ga: '', patterns: { analytics: [], marketing: ['sbjs_*'] }, wpCookiePrefix: 'wp_consent', wpApi: false, bridge: 'builtin' };
  const dom = new JSDOM(`<!doctype html><html><head><script id="cpc-config" type="application/json">${JSON.stringify(config)}</script><script>${core}</script></head><body></body></html>`,
    { url: 'https://shop.example.com/', runScripts: 'dangerously', beforeParse(x) { x.document.cookie = consentCookie(true); } });
  w = dom.window; await wait(300);
  // inject delayed scripts without the inline "before" (worst case: inline was stripped)
  [`var wc_order_attribution={params:{allowTracking:false},fields:{}};`, sbjs, wcOA].forEach(code => { const s = w.document.createElement('script'); s.textContent = code; w.document.body.appendChild(s); });
  await wait(800);
  check('delayed scripts: tracking switched on after they load', w.__calls[w.__calls.length - 1] === true && /sbjs_current=/.test(w.document.cookie), JSON.stringify(w.__calls));
  console.log(fails ? `\n${fails} FAILED` : '\nALL PASSED');
  process.exit(fails ? 1 : 0);
})();
