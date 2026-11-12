(function () {
  'use strict';
  const STORAGE_KEY = 'investhood-commercial-manager-sidebar-collapsed';
  const MOBILE_BREAKPOINT = 1024;
  const sidebar = () => document.getElementById('portalSidebar');
  const overlay = () => document.getElementById('portalSidebarOverlay');
  const button = () => document.getElementById('uxMenuButton');

  function setButton(expanded, mobileOpen) {
    const b = button();
    if (!b) return;
    const label = mobileOpen ? 'Hide menu' : (expanded ? 'Hide menu' : 'Show menu');
    b.setAttribute('aria-label', label);
    b.setAttribute('title', label);
    b.setAttribute('aria-expanded', mobileOpen || expanded ? 'true' : 'false');
    const icon = b.querySelector('i');
    if (icon) icon.className = mobileOpen ? 'fas fa-xmark' : 'fas fa-bars';
  }

  function closeMobile() {
    sidebar()?.classList.remove('open');
    overlay()?.classList.remove('open');
    document.body.classList.remove('portal-sidebar-mobile-open', 'ux-menu-open');
    setButton(!document.body.classList.contains('portal-sidebar-collapsed'), false);
  }

  function restore() {
    if (window.innerWidth < MOBILE_BREAKPOINT) {
      document.body.classList.remove('portal-sidebar-collapsed');
      closeMobile();
      return;
    }
    closeMobile();
    let collapsed = false;
    try { collapsed = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}
    document.body.classList.toggle('portal-sidebar-collapsed', collapsed);
    setButton(!collapsed, false);
  }

  function toggle() {
    if (!sidebar()) return;
    if (window.innerWidth < MOBILE_BREAKPOINT) {
      const open = !sidebar().classList.contains('open');
      sidebar().classList.toggle('open', open);
      overlay()?.classList.toggle('open', open);
      document.body.classList.toggle('portal-sidebar-mobile-open', open);
      document.body.classList.toggle('ux-menu-open', open);
      setButton(false, open);
      return;
    }
    const collapsed = !document.body.classList.contains('portal-sidebar-collapsed');
    document.body.classList.toggle('portal-sidebar-collapsed', collapsed);
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
    setButton(!collapsed, false);
  }

  function init() {
    const b = button();
    if (b) {
      // Capture the event so the generic unified-portal handler cannot target its
      // different #uxSidebar markup or interfere with this portal's sidebar.
      document.addEventListener('click', function (event) {
        const target = event.target.closest('#uxMenuButton');
        if (!target) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        toggle();
      }, true);
    }
    document.getElementById('portalSidebarClose')?.addEventListener('click', closeMobile);
    overlay()?.addEventListener('click', closeMobile);
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeMobile();
    });
    restore();
    window.addEventListener('resize', restore);
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();
