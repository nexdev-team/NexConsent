(function (w, d) {
  'use strict';
  var api = w.CPAPConsent, banner = d.getElementById('cpc-banner'), overlay = d.getElementById('cpc-overlay'), reopen = d.getElementById('cpc-reopen');
  if (!api || !banner || !overlay) return;
  api.syncWordPress();
  var dialog = overlay.querySelector('[role="dialog"]'), previousFocus, oldOverflow;
  function sync() { var state = api.get(); banner.hidden = state.decided; if (reopen) reopen.hidden = !state.decided; }
  function open() {
    if (!overlay.hidden) return;
    previousFocus = d.activeElement; oldOverflow = d.body.style.overflow;
    var state = api.get();
    overlay.querySelectorAll('[data-cpc-category]').forEach(function (input) { input.checked = state[input.dataset.cpcCategory]; });
    overlay.hidden = false; d.body.style.overflow = 'hidden'; dialog.focus();
  }
  function close() {
    overlay.hidden = true; d.body.style.overflow = oldOverflow || '';
    if (previousFocus && previousFocus.isConnected) previousFocus.focus();
  }
  function save(value) {
    if (!api.save(value)) { d.querySelectorAll('.cpc-error').forEach(function (x) { x.hidden = false; }); return; }
    d.querySelectorAll('.cpc-error').forEach(function (x) { x.hidden = true; });
    var wasDialogOpen = !overlay.hidden;
    if (wasDialogOpen) close();
    sync();
    // A hidden banner button must not retain keyboard focus after a decision.
    if (reopen && reopen.getClientRects().length) reopen.focus();
    else if (d.activeElement && d.activeElement.closest('#cpc-banner[hidden]')) {
      var main = d.querySelector('main') || d.body, priorTabindex = main.getAttribute('tabindex');
      main.setAttribute('tabindex', '-1'); main.focus({ preventScroll: true });
      if (priorTabindex === null) main.removeAttribute('tabindex'); else main.setAttribute('tabindex', priorTabindex);
    }
  }
  d.addEventListener('click', function (e) {
    var target = e.target.closest('[data-cpc]');
    if (!target) { if (e.target === overlay) close(); return; }
    var action = target.dataset.cpc;
    if (action === 'open') open();
    else if (action === 'close') close();
    else if (action === 'accept') save({ analytics: true, marketing: true });
    else if (action === 'reject') save({ analytics: false, marketing: false });
    else if (action === 'save') {
      var value = {}; overlay.querySelectorAll('[data-cpc-category]').forEach(function (input) { value[input.dataset.cpcCategory] = input.checked; }); save(value);
    }
  });
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
  d.addEventListener('cpapstore:open', open);
  d.addEventListener('cpapstore:consent', sync);
  sync();
})(window, document);
