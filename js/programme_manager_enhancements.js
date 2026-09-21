(function(){'use strict';
const KEY='investhood-programme-manager-theme',html=document.documentElement,body=document.body;
function side(){return document.getElementById('portalSidebar')||document.querySelector('.sidebar')}
function overlay(){return document.getElementById('portalSidebarOverlay')||document.querySelector('.sidebar-overlay')}
function current(){return html.getAttribute('data-theme')==='dark'?'dark':'light'}
function apply(v){v=v==='dark'?'dark':'light';html.setAttribute('data-theme',v);body.classList.toggle('dark-mode',v==='dark');try{localStorage.setItem(KEY,v)}catch(e){}let i=document.getElementById('pmThemeIcon');if(i)i.className=v==='dark'?'fas fa-sun':'fas fa-moon'}
function toggleSidebar(){let s=side();if(!s)return;if(innerWidth<1024){let open=!s.classList.contains('open');s.classList.toggle('open',open);overlay()?.classList.toggle('open',open)}else{body.classList.toggle('portal-sidebar-collapsed')}}
function closeSidebar(){side()?.classList.remove('open');overlay()?.classList.remove('open')}
function init(){let v='light';try{let x=localStorage.getItem(KEY);v=(x==='dark'||x==='light')?x:(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light')}catch(e){}apply(v);
document.querySelectorAll('#pmThemeToggle').forEach(b=>b.addEventListener('click',()=>apply(current()==='dark'?'light':'dark')));
document.querySelectorAll('#uxSidebarToggle,#portalSidebarToggle').forEach(b=>b.addEventListener('click',toggleSidebar));
document.getElementById('portalSidebarClose')?.addEventListener('click',closeSidebar);overlay()?.addEventListener('click',closeSidebar);
const nb=document.getElementById('pmNotificationButton'),dd=document.getElementById('pmNotificationDropdown');nb?.addEventListener('click',e=>{e.stopPropagation();dd?.classList.toggle('is-open')});
const modal=document.getElementById('pmAllNotificationsModal');document.getElementById('pmViewAllNotifications')?.addEventListener('click',e=>{e.preventDefault();modal?.classList.add('is-open')});
['pmAllNotificationsClose','pmAllNotificationsDone'].forEach(id=>document.getElementById(id)?.addEventListener('click',()=>modal?.classList.remove('is-open')));
}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init()})();