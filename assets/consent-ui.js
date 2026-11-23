/*! NexConsent banner. GPL-2.0-or-later. */
(function (w, d) {
  'use strict';
  var api = w.NexConsent, banner = d.getElementById('nxc-banner'), overlay = d.getElementById('nxc-overlay'), reopen = d.getElementById('nxc-reopen');
  if (!api || !banner || !overlay) return;
  api.syncWordPress();
  var dialog = overlay.querySelector('[role="dialog"]'), previousFocus, oldOverflow;
  function sync() { var state = api.get(); banner.hidden = state.decided; if (reopen) reopen.hidden = !state.decided; }
  function open() {
    if (!overlay.hidden) return;
    previousFocus = d.activeElement; oldOverflow = d.body.style.overflow;
    var state = api.get();
    overlay.querySelectorAll('[data-nxc-choice]').forEach(function (input) { input.checked = state[input.getAttribute('data-nxc-choice')]; });
    overlay.hidden = false; d.body.style.overflow = 'hidden'; dialog.focus();
  }
  function close() {
    overlay.hidden = true; d.body.style.overflow = oldOverflow || '';
    if (previousFocus && previousFocus.isConnected) previousFocus.focus();
  }
  function save(value) {
    if (!api.save(value)) { d.querySelectorAll('.nxc-error').forEach(function (x) { x.hidden = false; }); return; }
    d.querySelectorAll('.nxc-error').forEach(function (x) { x.hidden = true; });
    if (!overlay.hidden) close();
    sync();
    // A hidden banner button must not keep keyboard focus after a decision.
    if (reopen && reopen.getClientRects().length) reopen.focus();
    else if (d.activeElement && d.activeElement.closest('#nxc-banner[hidden]')) {
      var main = d.querySelector('main') || d.body, priorTabindex = main.getAttribute('tabindex');
      main.setAttribute('tabindex', '-1'); main.focus({ preventScroll: true });
      if (priorTabindex === null) main.removeAttribute('tabindex'); else main.setAttribute('tabindex', priorTabindex);
    }
  }
  d.addEventListener('click', function (e) {
    var target = e.target.closest ? e.target.closest('[data-nxc],a[href$="#cookie-settings"]' + (w.CPAPConsent ? ',[data-cpc="open"]' : '')) : null;
    if (!target) { if (e.target === overlay) close(); return; }
    var action = target.getAttribute('data-nxc') || 'open';
    if (target.tagName === 'A') e.preventDefault();
    if (action === 'open') open();
    else if (action === 'close') close();
    else if (action === 'accept') save({ analytics: true, marketing: true });
    else if (action === 'reject') save({ analytics: false, marketing: false });
    else if (action === 'save') {
      var value = {};
      overlay.querySelectorAll('[data-nxc-choice]').forEach(function (input) { value[input.getAttribute('data-nxc-choice')] = input.checked; });
      save(value);
    }
  });
  // A menu link to #cookie-settings opens the settings too.
  if (w.location.hash === '#cookie-settings') open();
  d.addEventListener('keydown', function (e) {
    if (overlay.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    if (e.key === 'Tab') {
      var nodes = Array.from(dialog.querySelectorAll('button, a[href], input, [tabindex="0"]')).filter(function (x) { return !x.disabled && x.getClientRects().length; });
      var first = nodes[0], last = nodes[nodes.length - 1];
      if (e.shiftKey && (d.activeElement === first || d.activeElement === dialog)) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && (d.activeElement === last || d.activeElement === dialog)) { e.preventDefault(); first.focus(); }
    }
  });
  d.addEventListener('focusin', function (e) { if (!overlay.hidden && !dialog.contains(e.target)) dialog.focus(); });
  d.addEventListener('nexconsent:open', open);
  d.addEventListener('nexconsent:change', sync);
  sync();
})(window, document);
