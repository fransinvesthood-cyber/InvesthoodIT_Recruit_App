<?php $pageTitle=$pageTitle??'Finance Officer Portal';$currentPage=$currentPage??'';$localFlash=fo_local_flash();?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle) ?> | Investhood IT</title><script>(function(){try{const t=localStorage.getItem('investhood-unified-theme')||localStorage.getItem('investhood-fo-theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light')}catch(e){}})();</script><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="<?= url('css/styles.css') ?>"><link rel="stylesheet" href="<?= url('css/finance_officer.css') ?>?v=20261112-sidebar3"><link rel="stylesheet" href="<?= url('css/unified_portal.css') ?>?v=20261112-sidebar3">

  <link rel="stylesheet" href="<?=url('css/styles_original_pm.css')?>">
  <link rel="stylesheet" href="<?=url('css/original_pm_sidebar_compat.css')?>">
  <link rel="stylesheet" href="<?= url('css/finance_responsive_fix.css') ?>">
<link rel="stylesheet" href="<?= url('css/dashboard_card_modals.css') ?>?v=20260919">
<link rel="stylesheet" href="<?= url('css/finance_current_responsive.css') ?>?v=20260919">
<style>
/* Finance sidebar controls: mobile drawer and desktop collapse */
@media(min-width:921px){html.fo-sidebar-collapsed .fo-sidebar{transform:translateX(-100%)}html.fo-sidebar-collapsed .ux-main{margin-left:0!important}html.fo-sidebar-collapsed .fo-sidebar{transition:transform .22s ease}html:not(.fo-sidebar-collapsed) .fo-sidebar{transition:transform .22s ease}}
@media(max-width:920px){.fo-sidebar{transform:translateX(-105%);transition:transform .22s ease;z-index:6000!important}.fo-sidebar.fo-sidebar--open{transform:translateX(0)}.fo-overlay{z-index:5500!important}.ux-navbar__menu{display:inline-flex!important;align-items:center;justify-content:center;min-width:40px;min-height:40px;cursor:pointer}.ux-navbar__left{min-width:0}.ux-navbar__heading{min-width:0}}
.ux-navbar__menu{display:inline-flex!important;align-items:center;justify-content:center;visibility:visible!important;opacity:1!important;border:1px solid var(--border,#e5e7eb);border-radius:10px;background:var(--surface,#fff);color:inherit;padding:8px 11px}

/* Content-area scrolling: the page itself must own the vertical scrollbar.
   Keep navbar fixed in the main column and let .ux-content consume remaining height. */
html, body.ux-body { height:100%; min-height:100%; }
body.ux-body { margin:0; overflow:hidden!important; }
.ux-app { height:100vh!important; height:100dvh!important; min-height:0!important; overflow:hidden!important; }
.ux-main {
  height:100vh!important; height:100dvh!important; min-height:0!important;
  display:flex!important; flex-direction:column!important; min-width:0!important;
  overflow:hidden!important; transition:margin-left .22s ease,width .22s ease;
}
.ux-main > .ux-navbar { flex:0 0 auto!important; min-width:0; }
.ux-main > .ux-content {
  display:block!important; flex:1 1 auto!important; min-height:0!important;
  height:auto!important; max-height:none!important; min-width:0!important;
  overflow-y:auto!important; overflow-x:hidden!important;
  overscroll-behavior-y:contain; -webkit-overflow-scrolling:touch;
  scrollbar-width:thin; scrollbar-color:rgba(100,116,139,.75) transparent;
  scrollbar-gutter:stable; padding-bottom:max(24px, env(safe-area-inset-bottom));
}
.ux-main > .ux-content::-webkit-scrollbar { width:10px; }
.ux-main > .ux-content::-webkit-scrollbar-track { background:transparent; }
.ux-main > .ux-content::-webkit-scrollbar-thumb { background:rgba(100,116,139,.72); border:2px solid transparent; background-clip:padding-box; border-radius:20px; }
.ux-main > .ux-content::-webkit-scrollbar-thumb:hover { background:rgba(71,85,105,.95); background-clip:padding-box; }
@media (min-width:921px) {
  .ux-main { margin-left:var(--fo-sidebar-width,280px); width:calc(100% - var(--fo-sidebar-width,280px)); }
  html.fo-sidebar-collapsed .ux-main { margin-left:0!important; width:100%!important; }
}
@media (max-width:920px) {
  .ux-main { margin-left:0!important; width:100%!important; }
  .ux-main > .ux-content { padding-left:clamp(12px,3vw,24px); padding-right:clamp(12px,3vw,24px); }
}

/* FIX 2: deterministic desktop column sizing and responsive dashboard containment.
   The sidebar occupies 270px in the live portal; the main workspace uses all remaining width. */
*, *::before, *::after { box-sizing:border-box; }
html, body { max-width:100%; overflow-x:hidden!important; }
.ux-app { width:100%; max-width:100vw; overflow:hidden!important; }
@media (min-width:921px) {
  #foSidebar.fo-sidebar {
    position:fixed!important; left:0!important; top:0!important; bottom:0!important;
    width:270px!important; max-width:270px!important;
  }
  .ux-main {
    position:fixed!important; top:0!important; right:0!important; bottom:0!important;
    left:270px!important; width:calc(100vw - 270px)!important;
    max-width:calc(100vw - 270px)!important; min-width:0!important;
    margin:0!important; padding:0!important; transform:none!important;
    overflow:hidden!important;
  }
  .ux-main > .ux-navbar { width:100%!important; max-width:100%!important; min-width:0!important; }
  .ux-main > .ux-content {
    width:100%!important; max-width:100%!important; min-width:0!important;
    padding-left:clamp(16px,2.1vw,28px)!important;
    padding-right:clamp(16px,2.1vw,28px)!important;
    overflow-x:hidden!important;
  }
  html.fo-sidebar-collapsed .ux-main {
    left:0!important; width:100vw!important; max-width:100vw!important;
  }
}
@media (max-width:920px) {
  .ux-main {
    position:relative!important; left:0!important; right:auto!important;
    top:auto!important; bottom:auto!important; width:100%!important;
    max-width:100vw!important; min-width:0!important; margin:0!important;
  }
  .ux-main > .ux-navbar,.ux-main > .ux-content { width:100%!important; max-width:100%!important; min-width:0!important; }
  .ux-main > .ux-content { padding-left:16px!important; padding-right:16px!important; }
}

/* SIDEBAR ALIGNMENT FIX 3
   The previous gap was caused by inherited offsets on the main content children.
   Anchor the workspace directly to the sidebar edge and clear child-level offsets. */
@media (min-width:921px) {
  #foSidebar.fo-sidebar {
    left:0!important; right:auto!important;
    width:270px!important; min-width:270px!important; max-width:270px!important;
    margin:0!important; padding-right:0!important;
  }
  body.ux-body > .ux-app > main.ux-main {
    position:fixed!important;
    inset:0 0 0 270px!important;
    display:flex!important;
    width:calc(100vw - 270px)!important;
    min-width:0!important; max-width:calc(100vw - 270px)!important;
    margin:0!important; padding:0!important;
    transform:none!important;
    box-sizing:border-box!important;
  }
  body.ux-body > .ux-app > main.ux-main > .ux-navbar {
    position:relative!important;
    inset:auto!important;
    flex:0 0 auto!important;
    display:flex!important;
    width:100%!important; max-width:100%!important;
    margin:0!important; margin-left:0!important; padding-left:24px!important;
    transform:none!important;
    box-sizing:border-box!important;
  }
  body.ux-body > .ux-app > main.ux-main > .ux-content {
    position:relative!important;
    inset:auto!important;
    flex:1 1 auto!important;
    width:100%!important; max-width:100%!important; min-width:0!important;
    margin:0!important; margin-left:0!important;
    padding-left:clamp(16px,2.1vw,28px)!important;
    padding-right:clamp(16px,2.1vw,28px)!important;
    transform:none!important;
    box-sizing:border-box!important;
  }
  html.fo-sidebar-collapsed body.ux-body > .ux-app > main.ux-main {
    inset:0!important; width:100vw!important; max-width:100vw!important;
  }
}
@media (max-width:920px) {
  body.ux-body > .ux-app > main.ux-main {
    position:relative!important; inset:auto!important;
    width:100%!important; max-width:100%!important; margin:0!important;
  }
  body.ux-body > .ux-app > main.ux-main > .ux-navbar,
  body.ux-body > .ux-app > main.ux-main > .ux-content {
    margin-left:0!important; width:100%!important; max-width:100%!important;
  }
}

/* Dashboard grids and hero must shrink inside the available workspace, never create page overflow. */
#finance-dashboard-redesign,
.finance-dashboard-redesign {
  display:block!important; width:100%!important; max-width:100%!important;
  min-width:0!important; margin-left:0!important; margin-right:0!important;
  overflow-x:clip;
}
#finance-dashboard-redesign > *,
.finance-dashboard-redesign > * { min-width:0!important; max-width:100%!important; }
#finance-dashboard-redesign .fo-hero {
  width:100%!important; max-width:100%!important; min-width:0!important;
}
#finance-dashboard-redesign .fo-stats--4 {
  width:100%!important; grid-template-columns:repeat(4,minmax(0,1fr))!important;
}
#finance-dashboard-redesign .fo-grid {
  width:100%!important; grid-template-columns:minmax(0,1.2fr) minmax(0,.8fr)!important;
}
#finance-dashboard-redesign .fo-activity-card,
#finance-dashboard-redesign .fo-card,
#finance-dashboard-redesign .fo-note { min-width:0!important; max-width:100%!important; }
#finance-dashboard-redesign .fo-table-wrap { max-width:100%; overflow-x:auto; }
@media(max-width:1100px) {
  #finance-dashboard-redesign .fo-stats--4 { grid-template-columns:repeat(2,minmax(0,1fr))!important; }
  #finance-dashboard-redesign .fo-grid { grid-template-columns:minmax(0,1fr)!important; }
}
@media(max-width:700px) {
  #finance-dashboard-redesign .fo-hero { align-items:flex-start!important; flex-direction:column!important; }
  #finance-dashboard-redesign .fo-orb { align-self:center; }
  #finance-dashboard-redesign .fo-stats--4 { grid-template-columns:minmax(0,1fr)!important; }
}
</style>
</head><body class="ux-body"><div class="ux-app"><?php require __DIR__.'/sidebar.php';?><main class="ux-main"><?php require __DIR__.'/navbar.php';?><div class="ux-content"><?= $flashes??'' ?><?= $localFlash ?>