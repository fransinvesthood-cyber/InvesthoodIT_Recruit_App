  function renderAdminInterviews(data) {
    if (!adminInterviewGrid) return;
    adminInterviewGrid.innerHTML = data.map(function (iv) {
      var baseUrl = window.APP_URL || '';
      var detailUrl = baseUrl + '/admin/interview.php?id=' + iv.id;
      var rescheduleUrl = baseUrl + '/admin/interview_schedule.php?id=' + iv.id;
      var confirmDisabled = (iv.status === 'Confirmed' || iv.status === 'Completed') ? ' style="pointer-events:none;opacity:0.5;"' : '';
      return '<div class="interview-card">' +
        '<div class="interview-card__header"><h3 class="interview-card__title">' + iv.title + '</h3><span class="interview-card__type">' + iv.type + '</span></div>' +
        '<div class="interview-card__countdown"><i class="fas fa-clock"></i><span class="interview-card__countdown-time">' + iv.cd + '</span></div>' +
        '<div class="interview-card__details">' +
          '<div class="interview-card__detail"><i class="fas fa-user"></i> ' + iv.candidate + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-calendar"></i> ' + iv.dt + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-building"></i> ' + iv.with + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-video"></i> ' + iv.mode + '</div>' +
        '</div>' +
        '<div class="interview-card__actions">' +
          '<a href="' + detailUrl + '" class="btn btn--primary btn--sm"' + confirmDisabled + '><i class="fas fa-check"></i> Confirm</a>' +
          '<a href="' + rescheduleUrl + '" class="btn btn--outline btn--sm"><i class="fas fa-clock"></i> Reschedule</a>' +
          '<a href="' + detailUrl + '" class="btn btn--ghost btn--sm">Details</a>' +
        '</div>';
    }).join('');
  }
  renderAdminInterviews(adminInterviews);