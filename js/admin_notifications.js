'use strict';
document.addEventListener('DOMContentLoaded', function () {
  var API = (window.APP_URL ? window.APP_URL + '/' : '') + 'admin/notifications_api.php';
  var meta = document.querySelector('meta[name="csrf-token"]');
  var TOKEN = meta ? meta.getAttribute('content') : '';
  function post(d) {
    var b = new URLSearchParams();
    Object.keys(d).forEach(function (k) { b.append(k, d[k]); });
    b.append('csrf_token', TOKEN);
    return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': TOKEN }, body: b.toString(), credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function esc(v) { return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
  var badge = document.getElementById('adminNotifBadge');
  var ddCount = document.getElementById('adminNotifDropdownCount');
  function applyUnread(n) {
    n = parseInt(n, 10); if (isNaN(n) || n < 0) n = 0;
    if (badge) { badge.textContent = n > 99 ? '99+' : String(n); badge.classList.toggle('is-hidden', n === 0); }
    if (ddCount) ddCount.textContent = n + ' unread';
    var s = document.getElementById('adminNotifStatUnread');
    if (s) s.textContent = String(n);
  }
  setInterval(function () { post({ action: 'unread_count' }).then(function (j) { if (j && j.success) applyUnread(j.unread); }).catch(function () {}); }, 60000);
  var btn = document.getElementById('adminNotifBtn');
  var dd = document.getElementById('adminNotifDropdown');
  var list = document.getElementById('adminNotifDropdownList');
  function setOpen(o) { if (!dd || !btn) return; dd.classList.toggle('is-open', o); dd.setAttribute('aria-hidden', o ? 'false' : 'true'); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); }
  function render(items) {
    if (!list) return;
    if (!items || !items.length) { list.innerHTML = '<div class="notif-dropdown__empty"><i class="fas fa-bell-slash"></i> You are all caught up!</div>'; return; }
    list.innerHTML = items.map(function (n) {
      return '<a class="notif-item notif-dropdown__item' + (n.is_read ? '' : ' notif-item--unread') + '" href="' + esc(n.url) + '" data-notif-id="' + n.id + '" data-notif-url="' + esc(n.url) + '"><div class="notif-item__icon notif-item__icon--' + esc(n.tone) + '"><i class="fas ' + esc(n.icon) + '"></i></div><div class="notif-item__content"><div class="notif-item__title">' + esc(n.title) + '</div><div class="notif-item__message">' + esc(n.message) + '</div><div class="notif-item__time">' + esc(n.time) + '</div></div></a>';
    }).join('');
  }
  if (btn && dd) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') { try { e.stopImmediatePropagation(); } catch (err) {} }
      var open = !dd.classList.contains('is-open');
      setOpen(open);
      if (open) { post({ action: 'recent', limit: 5 }).then(function (j) { if (j && j.success) { applyUnread(j.unread); render(j.notifications); } }).catch(function () {}); }
    }, true);
    dd.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
  }
  function markUi(item) {
    if (!item) return;
    item.classList.remove('notif-item--unread');
    var dot = item.querySelector('.notif-item__unread-dot'); if (dot) dot.remove();
    var b = item.querySelector('[data-notif-mark-read]'); if (b) b.remove();
  }
  document.addEventListener('click', function (e) {
    var link = e.target.closest('[data-notif-open], .notif-dropdown__item');
    if (link) {
      var item = link.closest('[data-notif-id]');
      if (!item) return;
      var id = parseInt(item.getAttribute('data-notif-id'), 10);
      var url = item.getAttribute('data-notif-url') || link.getAttribute('href');
      if (!id || !url) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      post({ action: 'mark_read', notification_id: id }).then(function (j) { if (j && j.success) applyUnread(j.unread); }).catch(function () {}).finally(function () { window.location.href = url; });
      return;
    }
    var mr = e.target.closest('[data-notif-mark-read]');
    if (mr) {
      e.preventDefault(); e.stopPropagation();
      var it = mr.closest('[data-notif-id]'); if (!it) return;
      mr.disabled = true;
      post({ action: 'mark_read', notification_id: parseInt(it.getAttribute('data-notif-id'), 10) }).then(function (j) { if (j && j.success) { markUi(it); applyUnread(j.unread); } else { mr.disabled = false; } }).catch(function () { mr.disabled = false; });
      return;
    }
    var del = e.target.closest('[data-notif-delete]');
    if (del) {
      e.preventDefault(); e.stopPropagation();
      var dit = del.closest('[data-notif-id]'); if (!dit) return;
      if (!window.confirm('Delete this notification? This cannot be undone.')) return;
      del.disabled = true;
      post({ action: 'delete', notification_id: parseInt(dit.getAttribute('data-notif-id'), 10) }).then(function (j) {
        if (j && j.success) { dit.style.opacity = '0'; setTimeout(function () { dit.remove(); var l = document.getElementById('adminNotifList'); if (l && !l.querySelector('.notif-item')) window.location.reload(); }, 250); applyUnread(j.unread); }
        else { del.disabled = false; }
      }).catch(function () { del.disabled = false; });
    }
  });
  document.querySelectorAll('[data-notif-mark-all]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.preventDefault(); b.disabled = true;
      post({ action: 'mark_all_read' }).then(function (j) { b.disabled = false; if (!j || !j.success) return; applyUnread(j.unread); document.querySelectorAll('.notif-item--unread').forEach(function (it) { markUi(it); }); }).catch(function () { b.disabled = false; });
    });
  });
});
