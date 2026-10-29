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
    var completeForm = document.querySelector('.lesson-complete-form');
    if (!items.length) return;
    for (var i = 0; i < items.length; i++) {
      var id = items[i].querySelector('.lesson-checkbox').getAttribute('data-lesson-id');
      if (id != currentId) {
        items[i].scrollIntoView({ behavior: 'smooth', block: 'center' });
        var chk = items[i].querySelector('.lesson-checkbox');
        chk.checked = true;
        if (completeForm) {
          completeForm.dataset.lessonId = id;
          markLessonComplete(window.APP_URL + '/candidate/learning_api.php', completeForm);
        }
        return;
      }
    }
  }

  // Select a lesson and expose its stored learning resource.
  var lessonItems = document.querySelectorAll('.lesson-nav__item[data-lesson-id]');
  var viewer = document.querySelector('.lesson-content__body');
  var title = document.getElementById('active-lesson-title');
  var type = document.getElementById('active-lesson-type');
  var resource = document.getElementById('active-lesson-resource');
  var completeForm = document.querySelector('.lesson-complete-form');
  var completeButton = document.getElementById('markLessonBtn');

  function selectLesson(item) {
    for (var i = 0; i < lessonItems.length; i++) {
      lessonItems[i].classList.remove('is-selected');
      lessonItems[i].setAttribute('aria-pressed', 'false');
    }
    item.classList.add('is-selected');
    item.setAttribute('aria-pressed', 'true');
    var lessonId = item.getAttribute('data-lesson-id');
    var lessonTitle = item.getAttribute('data-lesson-title') || 'Selected lesson';
    var lessonType = item.getAttribute('data-lesson-type') || 'Learning resource';
    var lessonUrl = item.getAttribute('data-lesson-url');
    var lessonText = item.getAttribute('data-lesson-text');
    var duration = parseInt(item.getAttribute('data-lesson-duration') || '0', 10);

    title.textContent = lessonTitle;
    type.textContent = lessonType + (duration > 0 ? ' · ' + duration + ' min' : '');
    viewer.classList.add('lesson-content__body--active');
    viewer.querySelector('p').textContent = lessonText || 'Use the resource below to complete this lesson.';
    var resourceHtml = '';
    if (/^https?:\/\//i.test(lessonUrl)) {
      var safeLessonUrl = lessonUrl.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
      resourceHtml = '<span class="lesson-resource__type"><i class="fas fa-link"></i> Lesson resource</span>' +
        '<a class="btn btn--primary btn--sm lesson-resource__link" href="' + safeLessonUrl + '" target="_blank" rel="noopener">' +
        '<i class="fas fa-external-link-alt"></i> Open lesson resource</a>';
    } else {
      resourceHtml = '<span class="lesson-resource__type"><i class="fas fa-info-circle"></i> Resource details</span>' +
        '<span class="lesson-resource__text">No external link was added for this lesson. Follow the lesson instructions above.</span>';
    }
    resource.innerHTML = resourceHtml;
    resource.hidden = false;
    if (completeForm) {
      completeForm.dataset.lessonId = lessonId;
      completeForm.querySelector('input[name="lesson_id"]').value = lessonId;
    }
    if (completeButton) {
      var isComplete = item.classList.contains('lesson-nav__item--complete');
      completeButton.disabled = isComplete;
      completeButton.innerHTML = isComplete
        ? '<i class="fas fa-check-circle"></i> Lesson completed'
        : '<i class="fas fa-check"></i> Mark lesson complete';
    }
  }

  for (var l = 0; l < lessonItems.length; l++) {
    (function (item) {
      item.addEventListener('click', function (event) {
        if (event.target.closest('.lesson-checkbox')) return;
        selectLesson(item);
      });
      item.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          selectLesson(item);
        }
      });
    })(lessonItems[l]);
  }
  if (lessonItems.length) selectLesson(lessonItems[0]);

  // Attach to lesson checkboxes (debounced save)
  var checkboxes = document.querySelectorAll('.lesson-checkbox');
  for (var i = 0; i < checkboxes.length; i++) {
    (function (chk) {
      chk.addEventListener('change', function () {
        if (chk.checked) {
          var item = chk.closest('.lesson-nav__item');
          if (item) selectLesson(item);
          if (completeForm) markLessonComplete(window.APP_URL + '/candidate/learning_api.php', completeForm);
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
