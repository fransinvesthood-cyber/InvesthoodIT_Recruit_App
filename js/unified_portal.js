(function(){'use strict';const KEY='investhood-unified-theme',html=document.documentElement;function current(){return html.getAttribute('data-theme')==='dark'?'dark':'light'}function setTheme(v){const t=v==='dark'?'dark':'light';html.setAttribute('data-theme',t);try{localStorage.setItem(KEY,t)}catch(e){}document.querySelectorAll('[data-ux-theme-icon]').forEach(i=>i.className=t==='dark'?'fas fa-sun':'fas fa-moon')}function closeSidebar(){document.getElementById('uxSidebar')?.classList.remove('ux-sidebar--open');document.getElementById('uxOverlay')?.classList.remove('ux-overlay--show');document.body.classList.remove('ux-menu-open')}function init(){let saved='light';try{saved=localStorage.getItem(KEY)||((window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light')}catch(e){}setTheme(saved);document.querySelectorAll('[data-ux-theme-toggle]').forEach(b=>b.addEventListener('click',()=>setTheme(current()==='dark'?'light':'dark')));document.getElementById('uxMenuButton')?.addEventListener('click',()=>{document.getElementById('uxSidebar')?.classList.add('ux-sidebar--open');document.getElementById('uxOverlay')?.classList.add('ux-overlay--show');document.body.classList.add('ux-menu-open')});document.getElementById('uxSidebarClose')?.addEventListener('click',closeSidebar);document.getElementById('uxOverlay')?.addEventListener('click',closeSidebar);const b=document.getElementById('uxNotificationButton'),d=document.getElementById('uxNotificationDropdown');b?.addEventListener('click',e=>{e.stopPropagation();d?.classList.toggle('ux-dropdown--open')});document.addEventListener('click',e=>{if(!e.target.closest('.ux-navbar__notification'))d?.classList.remove('ux-dropdown--open')});document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSidebar();d?.classList.remove('ux-dropdown--open')}});window.addEventListener('resize',()=>{if(innerWidth>920)closeSidebar()})}document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init()})();
(function(){
  'use strict';
  const key='investhood-unified-sidebar-collapsed';
  function applySaved(){
    if(window.innerWidth>920){
      try{document.body.classList.toggle('ux-desktop-sidebar-collapsed',localStorage.getItem(key)==='1')}catch(e){}
    }else document.body.classList.remove('ux-desktop-sidebar-collapsed');
  }
  document.addEventListener('click',function(e){
    const b=e.target.closest('#uxSidebarToggle');
    if(!b||window.innerWidth<=920)return;
    e.preventDefault();e.stopImmediatePropagation();
    const c=!document.body.classList.contains('ux-desktop-sidebar-collapsed');
    document.body.classList.toggle('ux-desktop-sidebar-collapsed',c);
    try{localStorage.setItem(key,c?'1':'0')}catch(err){}
  },true);
  addEventListener('resize',applySaved);
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',applySaved):applySaved();
})();

;(()=>{function syncOriginalPmDarkMode(){document.body.classList.toggle('dark-mode',document.documentElement.getAttribute('data-theme')==='dark')}new MutationObserver(syncOriginalPmDarkMode).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});document.readyState==='loading'?document.addEventListener('DOMContentLoaded',syncOriginalPmDarkMode):syncOriginalPmDarkMode()})();
