(function () {
  'use strict';
  const root = document.documentElement;
  const THEME_KEY = 'investhood-unified-theme';
  const OLD_THEME_KEY = 'investhood-fo-theme';
  const SIDEBAR_KEY = 'investhood-finance-sidebar-collapsed';

  function getTheme() {
    try {
      const saved = localStorage.getItem(THEME_KEY) || localStorage.getItem(OLD_THEME_KEY);
      if (saved === 'dark' || saved === 'light') return saved;
    } catch (e) {}
    return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }
  function setTheme(theme) {
    theme = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-ux-theme', theme);
    document.body.classList.toggle('dark', theme === 'dark');
    document.body.classList.toggle('dark-mode', theme === 'dark');
    try {
      localStorage.setItem(THEME_KEY, theme);
      localStorage.setItem(OLD_THEME_KEY, theme);
    } catch (e) {}
    document.querySelectorAll('[data-fo-theme-icon], [data-ux-theme-icon]').forEach(icon => {
      icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });
    document.querySelectorAll('[data-fo-theme-toggle], [data-ux-theme-toggle]').forEach(btn => {
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }

  const sidebar = () => document.getElementById('foSidebar');
  const overlay = () => document.getElementById('foOverlay');
  const menuButton = () => document.getElementById('uxMenuButton');
  function closeMobileSidebar() {
    sidebar()?.classList.remove('fo-sidebar--open');
    overlay()?.classList.remove('fo-overlay--show');
    menuButton()?.setAttribute('aria-expanded', 'false');
  }
  function toggleSidebar() {
    const side = sidebar();
    if (!side) return;
    if (window.matchMedia('(max-width: 920px)').matches) {
      const open = !side.classList.contains('fo-sidebar--open');
      side.classList.toggle('fo-sidebar--open', open);
      overlay()?.classList.toggle('fo-overlay--show', open);
      menuButton()?.setAttribute('aria-expanded', open ? 'true' : 'false');
    } else {
      const collapsed = !root.classList.contains('fo-sidebar-collapsed');
      root.classList.toggle('fo-sidebar-collapsed', collapsed);
      try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (e) {}
    }
  }
  function syncSidebarScrollArea() {
    const side = sidebar();
    const nav = side?.querySelector('.fo-nav');
    if (!side || !nav) return;
    // The sidebar uses CSS Grid (minmax(0,1fr)) to allocate the exact
    // remaining viewport height. Keep scrolling on the nav, not the page.
    nav.style.overflowY = 'scroll';
    nav.style.overflowX = 'hidden';
  }

  function init() {
    setTheme(getTheme());
    // Use delegated handling so the theme control remains functional even if
    // portal scripts replace or re-render navbar contents after page load.
    if (!document.documentElement.dataset.financeThemeHandler) {
      document.documentElement.dataset.financeThemeHandler = '1';
      document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-fo-theme-toggle], [data-ux-theme-toggle]');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        setTheme(getTheme() === 'dark' ? 'light' : 'dark');
      }, true);
    }
    const side = sidebar();
    syncSidebarScrollArea();
    if (side && 'ResizeObserver' in window) {
      const observer = new ResizeObserver(() => requestAnimationFrame(syncSidebarScrollArea));
      observer.observe(side);
      const nav = side.querySelector('.fo-nav');
      if (nav) observer.observe(nav);
    }
    side?.addEventListener('transitionend', syncSidebarScrollArea);
    side?.addEventListener('transitionrun', syncSidebarScrollArea);
    window.addEventListener('resize', syncSidebarScrollArea, { passive: true });
    window.addEventListener('orientationchange', () => requestAnimationFrame(syncSidebarScrollArea), { passive: true });
    window.addEventListener('load', syncSidebarScrollArea, { once: true });
    try {
      if (window.innerWidth > 920 && localStorage.getItem(SIDEBAR_KEY) === '1') root.classList.add('fo-sidebar-collapsed');
    } catch (e) {}
    menuButton()?.addEventListener('click', event => { event.preventDefault(); toggleSidebar(); });
    document.getElementById('foSidebarClose')?.addEventListener('click', closeMobileSidebar);
    overlay()?.addEventListener('click', closeMobileSidebar);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMobileSidebar(); });
    window.addEventListener('resize', () => { syncSidebarScrollArea(); if (window.innerWidth > 920) closeMobileSidebar(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
