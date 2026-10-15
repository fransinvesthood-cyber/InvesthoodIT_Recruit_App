/* Admin System Settings interactions: tabs, dirty tracking, confirm, spinner */
(function () {
  var tabs = document.querySelectorAll('.set-tab');
  var panels = document.querySelectorAll('.set-panel');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('active'); });
      panels.forEach(function (p) { p.classList.remove('active'); });
      t.classList.add('active');
      var p = document.querySelector('[data-panel="' + t.dataset.tab + '"]');
      if (p) p.classList.add('active');
    });
  });
  var form = document.getElementById('settingsForm');
  if (!form) return;
  var initial = new FormData(form);
  var initialStr = JSON.stringify(Array.from(initial.entries()));
  var dirty = false;
  form.addEventListener('change', function () { dirty = true; });
  form.addEventListener('input', function () { dirty = true; });
  window.addEventListener('beforeunload', function (e) {
    var now = JSON.stringify(Array.from(new FormData(form).entries()));
    if (dirty && now !== initialStr) { e.preventDefault(); e.returnValue = ''; }
  });
  var reset = document.getElementById('settingsReset');
  if (reset) reset.addEventListener('click', function () {
    if (confirm('Discard unsaved changes?')) form.reset();
  });
  var maint = document.getElementById('maintToggle');
  if (maint) maint.addEventListener('change', function () {
    if (maint.checked && !confirm('Enable maintenance mode? Non-admin users will see the maintenance message.')) {
      maint.checked = false;
    }
  });
  form.addEventListener('submit', function () {
    var btn = document.getElementById('settingsSaveBtn');
    if (!btn) return;
    btn.disabled = true;
    var sp = btn.querySelector('.btn__spinner');
    if (sp) sp.hidden = false;
  });
  var sb = document.getElementById('sidebarToggle');
  var side = document.getElementById('adminSidebar');
  var ov = document.getElementById('sidebarOverlay');
  if (sb && side) sb.addEventListener('click', function () {
    side.classList.toggle('open'); if (ov) ov.classList.toggle('active');
  });
})();
