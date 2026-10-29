/* ================================================
   INVESTHOOD IT - Candidate Notifications JS
   - Header bell dropdown (recent notifications)
   - Dynamic unread badge with periodic refresh
   - Mark as read / Mark all as read / Delete via the
     CSRF-protected notifications API
   - Dashboard notification centre category filters
   - "View details" navigation to the related record
   ================================================ */
'use strict';
document.addEventListener('DOMContentLoaded', function () {
  var API_URL = (window.APP_URL ? window.APP_URL + '/' : '') + 'candidate/notifications_api.php';
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var CSRF_TOKEN = csrfMeta ? csrfMeta.getAttribute('content') : '';

  function apiPost(data) {
    var body = new URLSearchParams();
    Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
    body.append('csrf_token', CSRF_TOKEN);
    return fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (res) { return res.json(); });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function plainText(value) {
    return String(value == null ? '' : value).replace(/<[^>]*>/g, '');
  }

  // ============================================
  // UNREAD BADGE
  // ============================================
  var badge = document.getElementById('notifHeaderBadge');
  var dropdownCount = document.getElementById('notifDropdownCount');
  var statUnread = document.getElementById('notifStatUnread');

  function applyUnread(unread) {
    unread = parseInt(unread, 10);
    if (isNaN(unread) || unread < 0) unread = 0;
    if (badge) {
      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.classList.toggle('is-hidden', unread === 0);
    }
    if (dropdownCount) dropdownCount.textContent = unread + ' unread';
    if (statUnread) statUnread.textContent = String(unread);
  }

  function refreshUnread() {
    apiPost({ action: 'unread_count' }).then(function (json) {
      if (json && json.success) applyUnread(json.unread);
    }).catch(function () { /* silent — next poll retries */ });
  }

  // Keep the badge fresh without reloading the page (60s interval).
  setInterval(refreshUnread, 60000);

  // ============================================
  // HEADER DROPDOWN
  // ============================================
  var bellBtn = document.getElementById('notifHeaderBtn');
  var dropdown = document.getElementById('notifDropdown');
  var dropdownList = document.getElementById('notifDropdownList');

  function setDropdownOpen(open) {
    if (!dropdown || !bellBtn) return;
    dropdown.classList.toggle('is-open', open);
    dropdown.setAttribute('aria-hidden', open ? 'false' : 'true');
    bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function renderDropdownItems(notifications) {
    if (!dropdownList) return;
    if (!notifications || !notifications.length) {
      dropdownList.innerHTML = '<div class="notif-dropdown__empty"><i class="fas fa-bell-slash"></i> You are all caught up!</div>';
      return;
    }
    dropdownList.innerHTML = notifications.map(function (n) {
      return '<a class="notif-item notif-dropdown__item' + (n.is_read ? '' : ' notif-item--unread') + '"' +
        ' href="' + escapeHtml(n.url) + '" data-notif-id="' + parseInt(n.id, 10) + '" data-notif-url="' + escapeHtml(n.url) + '">' +
        '<div class="notif-item__icon notif-item__icon--' + escapeHtml(n.tone) + '"><i class="fas ' + escapeHtml(n.icon) + '"></i></div>' +
        '<div class="notif-item__content">' +
          '<div class="notif-item__title">' + escapeHtml(n.title) + '</div>' +
          '<div class="notif-item__message">' + escapeHtml(plainText(n.message)) + '</div>' +
          '<div class="notif-item__time">' + escapeHtml(n.time) + '</div>' +
        '</div>' +
      '</a>';
    }).join('');
  }

  if (bellBtn && dropdown) {
    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = !dropdown.classList.contains('is-open');
      setDropdownOpen(willOpen);
      if (willOpen) {
        apiPost({ action: 'recent', limit: 5 }).then(function (json) {
          if (!json || !json.success) return;
          applyUnread(json.unread);
          renderDropdownItems(json.notifications);
        }).catch(function () { /* keep server-rendered fallback */ });
      }
    });
    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { setDropdownOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setDropdownOpen(false);
    });
  }

  // ============================================
  // MARK READ / DELETE / MARK ALL READ
  // ============================================
  function markItemReadUi(item) {
    if (!item) return;
    item.classList.remove('notif-item--unread');
    var dot = item.querySelector('.notif-item__unread-dot');
    if (dot) dot.remove();
    var btn = item.querySelector('[data-notif-mark-read]');
    if (btn) btn.remove();
  }

  // Click a notification: mark it read, then open the related record.
  document.addEventListener('click', function (e) {
    var openLink = e.target.closest('[data-notif-open], .notif-dropdown__item');
    if (!openLink) return;
    var item = openLink.closest('[data-notif-id]');
    if (!item) return;
    var id = parseInt(item.getAttribute('data-notif-id'), 10);
    var url = item.getAttribute('data-notif-url') || openLink.getAttribute('href');
    if (!id || !url) return;
    // Let modified clicks (new tab) behave normally.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    apiPost({ action: 'mark_read', notification_id: id })
      .then(function (json) {
        if (json && json.success) applyUnread(json.unread);
      })
      .catch(function () { /* navigation must never be blocked */ })
      .finally(function () { window.location.href = url; });
  });

  // Mark as read button on feed items.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-notif-mark-read]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var item = btn.closest('[data-notif-id]');
    if (!item) return;
    var id = parseInt(item.getAttribute('data-notif-id'), 10);
    btn.disabled = true;
    apiPost({ action: 'mark_read', notification_id: id }).then(function (json) {
      if (json && json.success) {
        markItemReadUi(item);
        applyUnread(json.unread);
      } else {
        btn.disabled = false;
      }
    }).catch(function () { btn.disabled = false; });
  });

  // Delete button (with confirmation).
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-notif-delete]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var item = btn.closest('[data-notif-id]');
    if (!item) return;
    var id = parseInt(item.getAttribute('data-notif-id'), 10);
    if (!window.confirm('Delete this notification? This cannot be undone.')) return;
    btn.disabled = true;
    apiPost({ action: 'delete', notification_id: id }).then(function (json) {
      if (json && json.success) {
        item.style.transition = 'opacity 0.25s ease';
        item.style.opacity = '0';
        setTimeout(function () {
          item.remove();
          var list = document.getElementById('notifList');
          if (list && !list.querySelector('.notif-item')) {
            window.location.reload(); // show the proper empty state / updated counts
          }
        }, 250);
        applyUnread(json.unread);
      } else {
        btn.disabled = false;
      }
    }).catch(function () { btn.disabled = false; });
  });

  // Mark ALL as read (dropdown footer + notifications page).
  document.querySelectorAll('[data-notif-mark-all]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      btn.disabled = true;
      apiPost({ action: 'mark_all_read' }).then(function (json) {
        btn.disabled = false;
        if (!json || !json.success) return;
        applyUnread(json.unread);
        document.querySelectorAll('.notif-item--unread').forEach(function (item) {
          markItemReadUi(item);
        });
      }).catch(function () { btn.disabled = false; });
    });
  });

  // ============================================
  // DASHBOARD NOTIFICATION CENTRE FILTERS
  // ============================================
  var notifList = document.getElementById('notifList');
  document.querySelectorAll('.notif-centre__filter[data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var filter = btn.getAttribute('data-filter') || 'all';
      document.querySelectorAll('.notif-centre__filter[data-filter]').forEach(function (b) {
        b.classList.toggle('active', b === btn);
      });
      if (!notifList) return;
      var anyVisible = false;
      notifList.querySelectorAll('.notif-item[data-category]').forEach(function (item) {
        var show = filter === 'all' || item.getAttribute('data-category') === filter;
        item.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
      });
      var emptyEl = document.getElementById('notifCentreEmpty');
      if (emptyEl) emptyEl.style.display = anyVisible ? 'none' : '';
    });
  });
});
