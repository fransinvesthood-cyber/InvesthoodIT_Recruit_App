/* ===============================================
   INVESTHOOD IT - Learning & Skills JS
   - Mark lesson complete via AJAX (with debounce)
   - Start course refresh
   - Safe external link handling
   ================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // ========================
  // MARK LESSON COMPLETE
  // ========================
  var checkInterval = 500;
  var debounceTimer = null;

  function markLessonComplete(url, $form) {
    var btn = $form.querySelector('button[type="submit"]');
    var lessonId = $form.dataset.lessonId;
    var assignmentId = $form.dataset.courseId;

    if (!lessonId || !assignmentId) return;

    // Only mark after the change settles.
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      if (btn.disabled) return;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking complete...';

      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'mark_lesson_complete',
          csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          assignment_id: assignmentId,
          lesson_id: lessonId
        }).toString(),
        credentials: 'same-origin'
      }).then(function (res) { return res.json(); }).then(function (json) {
        if (json && json.success) {
          var done = document.getElementById('lesson-' + lessonId);
          if (done) done.classList.add('lesson-nav__item--complete');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check"></i> Lesson complete';
          // Scroll to the next incomplete lesson
          markNextIncomplete(lessonId);
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-check"></i> Mark complete';
        }
      }).catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Mark complete';
      });
    }, checkInterval);
  }

  function markNextIncomplete(currentId) {
    var items = document.querySelectorAll('.lesson-nav__item:not(.lesson-nav__item--complete)');
    if (!items.length) return;
    for (var i = 0; i < items.length; i++) {
      var id = items[i].querySelector('.lesson-checkbox').getAttribute('data-lesson-id');
      if (id != currentId) {
        items[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
        var chk = items[i].querySelector('.lesson-checkbox');
        chk.checked = true;
        markLessonComplete(window.APP_URL + '/candidate/learning_api.php', chk.closest('form'));
        return;
      }
    }
  }

  // Attach to lesson checkboxes (debounced save)
  var checkboxes = document.querySelectorAll('.lesson-checkbox');
  for (var i = 0; i < checkboxes.length; i++) {
    (function (chk) {
      chk.addEventListener('change', function () {
        if (chk.checked) {
          var form = chk.closest('form');
          if (form) markLessonComplete(window.APP_URL + '/candidate/learning_api.php', form);
        }
      });
    })(checkboxes[i]);
  }

  // ========================
  // START COURSE
  // ========================
  var startForms = document.querySelectorAll('form[data-action="start_course"]');
  for (var s = 0; s < startForms.length; s++) {
    startForms[s].addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = this.querySelector('button[type="submit"]');
      var formData = new FormData(this);
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
      fetch(window.APP_URL + '/candidate/learning_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'start_course',
          csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          assignment_id: formData.get('assignment_id')
        }).toString(),
        credentials: 'same-origin'
      }).then(function (res) { return res.json(); }).then(function (json) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Start';
        if (json && json.success) {
          window.location.href = json.redirect || window.APP_URL + '/candidate/learning.php';
        } else {
          alert(json.message || 'Unable to start course.');
        }
      }).catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Start';
      });
    });
  }

  // ========================
  // SAFE EXTERNAL LINKS
  // ========================
  document.querySelectorAll('a[rel~="noopener"]').forEach(function (a) {
    a.addEventListener('click', function () {
      var href = a.getAttribute('href');
      if (href && (href.indexOf('http') === 0 || href.indexOf('mailto:') === 0)) {
        // Always open external links in a new tab to keep the portal open.
        return;
      }
    });
  });
});
