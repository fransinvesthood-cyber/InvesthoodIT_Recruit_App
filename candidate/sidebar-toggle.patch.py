from pathlib import Path
root=Path('/mnt/data/candwork/candidate')
# Add a consistent trigger to pages which have a sidebar but no existing trigger.
for p in root.glob('*.php'):
    s=p.read_text(errors='ignore')
    if 'id="sidebar"' not in s or 'id="sidebarToggle"' in s:
        continue
    marker='id="sidebarOverlay"></div>'
    idx=s.find(marker)
    if idx<0: continue
    end=idx+len(marker)
    button='\n<button type="button" class="dash-header__toggle candidate-sidebar-trigger" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>'
    s=s[:end]+button+s[end:]
    p.write_text(s)

# Existing trigger buttons: standardise accessible attributes and type.
for p in root.glob('*.php'):
    s=p.read_text(errors='ignore')
    if 'id="sidebarToggle"' not in s: continue
    s=s.replace('<button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">', '<button type="button" class="dash-header__toggle" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false">')
    s=s.replace('<button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"', '<button type="button" class="dash-header__toggle" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false"')
    p.write_text(s)

# Shared sidebar behaviour, delegated so alternate page layouts and duplicate templates remain safe.
js=root/'theme.js'
s=js.read_text()
insert=r'''
  // Candidate sidebar: desktop collapse + mobile slide-in drawer.
  function setSidebar(open) {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var layout = document.querySelector('.dashboard');
    var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    if (!sidebar) return;
    if (mobile) {
      sidebar.classList.toggle('is-open', !!open);
      if (overlay) overlay.classList.toggle('is-visible', !!open);
      document.body.classList.toggle('sidebar-drawer-open', !!open);
    } else {
      if (layout) layout.classList.toggle('sidebar-collapsed', !open);
      sidebar.classList.toggle('is-collapsed', !open);
      if (overlay) overlay.classList.remove('is-visible');
      document.body.classList.remove('sidebar-drawer-open');
    }
    document.querySelectorAll('#sidebarToggle').forEach(function (button) {
      button.setAttribute('aria-expanded', String(!!open));
      button.setAttribute('aria-label', open ? 'Hide menu' : 'Show menu');
      var icon = button.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-bars', !open);
        icon.classList.toggle('fa-indent', !!open);
      }
    });
  }
  function closeSidebar() { setSidebar(false); }
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) return;
    var toggle = target.closest('#sidebarToggle');
    if (toggle) {
      event.preventDefault();
      var sidebar = document.getElementById('sidebar');
      var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
      var isOpen = mobile ? !!(sidebar && sidebar.classList.contains('is-open')) : !(document.querySelector('.dashboard') && document.querySelector('.dashboard').classList.contains('sidebar-collapsed'));
      setSidebar(!isOpen);
      return;
    }
    if (target.closest('#sidebarClose') || target.closest('#sidebarOverlay')) {
      event.preventDefault(); closeSidebar(); return;
    }
    if (target.closest('#sidebar .sidebar__link') && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
      closeSidebar();
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', function () {
    var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    if (!mobile) {
      var sidebar = document.getElementById('sidebar');
      if (sidebar) sidebar.classList.remove('is-open');
      var overlay = document.getElementById('sidebarOverlay');
      if (overlay) overlay.classList.remove('is-visible');
      document.body.classList.remove('sidebar-drawer-open');
    } else {
      var layout = document.querySelector('.dashboard');
      if (layout) layout.classList.remove('sidebar-collapsed');
      var side = document.getElementById('sidebar');
      if (side) side.classList.remove('is-collapsed');
    }
  });
'''
# place before final closure
pos=s.rfind('})();')
s=s[:pos]+insert+s[pos:]
js.write_text(s)

# Shared styling appended after all existing page styles.
css=root/'theme.css'
c=css.read_text()
c += r'''

/* Candidate portal sidebar show/hide control */
.dash-header__toggle,
.candidate-sidebar-trigger {
  display:inline-flex !important; align-items:center; justify-content:center;
  width:42px; height:42px; min-width:42px; border:1px solid var(--candidate-border, #dce4ef);
  border-radius:10px; background:var(--candidate-surface, #fff); color:var(--candidate-text, #172033);
  cursor:pointer; font-size:1.05rem; line-height:1; z-index:1201; transition:background .18s ease,color .18s ease,border-color .18s ease;
}
.dash-header__toggle:hover,.candidate-sidebar-trigger:hover { background:#eaf1fb; }
html[data-theme="dark"] .dash-header__toggle,
html[data-theme="dark"] .candidate-sidebar-trigger { background:#1d2c43; color:#e7eef8; border-color:#40516b; }
html[data-theme="dark"] .dash-header__toggle:hover,
html[data-theme="dark"] .candidate-sidebar-trigger:hover { background:#294365; }
.sidebar__close { display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
.sidebar-overlay { display:none; }
.sidebar-overlay.is-visible { display:block !important; position:fixed; inset:0; z-index:1098; background:rgba(2,8,23,.58); }
@media (min-width:901px) {
  .dashboard.sidebar-collapsed #sidebar,
  .dashboard #sidebar.is-collapsed { transform:translateX(-100%) !important; visibility:hidden; }
  .dashboard.sidebar-collapsed .dashboard__main,
  .dashboard.sidebar-collapsed .dash-content { margin-left:0 !important; width:100% !important; max-width:none; }
  .dashboard.sidebar-collapsed { --candidate-sidebar-width:0px; }
  .dashboard #sidebar { transition:transform .22s ease,visibility .22s ease; }
}
@media (max-width:900px) {
  #sidebar.sidebar { position:fixed !important; top:0; bottom:0; left:0; height:100dvh; max-height:100dvh; width:min(280px,86vw) !important; z-index:1100; transform:translateX(-105%); visibility:hidden; transition:transform .22s ease,visibility .22s ease; }
  #sidebar.sidebar.is-open { transform:translateX(0); visibility:visible; }
  .sidebar__close { display:inline-flex !important; }
  .dashboard__main,.dash-content { width:100% !important; margin-left:0 !important; min-width:0; }
  .dash-header__toggle,.candidate-sidebar-trigger { position:relative; z-index:1201; }
  body.sidebar-drawer-open { overflow:hidden; }
}
'''
css.write_text(c)

# Bump shared theme cache references in PHP pages.
for p in root.glob('*.php'):
    s=p.read_text(errors='ignore')
    s=s.replace('20261112-4','20261112-sidebar1').replace('20261112-skills-fix1','20261112-sidebar1')
    p.write_text(s)
