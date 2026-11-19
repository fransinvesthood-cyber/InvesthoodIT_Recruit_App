<?php
$currentPage = $currentPage ?? '';
if (!isset($user) || !is_array($user)) $user = current_user();
$name = trim((string)($user['full_name'] ?? $user['fullname'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
if ($name === '') $name = 'Recruiter';
$eaNotice = ['due' => 0, 'high' => 0, 'items' => [], 'popup' => false];
$eaNoticeFile = __DIR__ . '/_engagement_notice.php';
if (is_file($eaNoticeFile)) { require $eaNoticeFile; }
?>
<aside class="sidebar" id="portalSidebar">
  <div class="sidebar__header"><a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a><button class="sidebar__close" id="portalSidebarClose" type="button" aria-label="Close menu"><i class="fas fa-times"></i></button></div>
  <nav class="sidebar__nav">
    <div class="sidebar__section-label">Recruiter</div><div class="sidebar__section-label">Main</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= in_array($currentPage,['dashboard'],true)?'active':'' ?>" href="<?= url('recruiter/dashboard.php') ?>"><i class="fas fa-grid-2"></i> Dashboard</a></li></ul>
    <div class="sidebar__section-label">Recruitment</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= in_array($currentPage,['vacancies','vacancy_view'],true)?'active':'' ?>" href="<?= url('recruiter/vacancies.php') ?>"><i class="fas fa-briefcase"></i> Vacancies</a></li></ul>
    <div class="sidebar__section-label">Talent</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= in_array($currentPage,['search'],true)?'active':'' ?>" href="<?= url('recruiter/search.php') ?>"><i class="fas fa-magnifying-glass"></i> Find Candidates</a></li><li><a class="sidebar__link <?= in_array($currentPage,['candidate_matching'],true)?'active':'' ?>" href="<?= url('recruiter/candidate_matching.php') ?>"><i class="fas fa-bullseye"></i> Candidate Matching</a></li><li><a class="sidebar__link <?= in_array($currentPage,['shortlists','shortlist_view'],true)?'active':'' ?>" href="<?= url('recruiter/shortlists.php') ?>"><i class="fas fa-list-check"></i> Shortlists</a></li><li><a class="sidebar__link <?= in_array($currentPage,['follow_up'],true)?'active':'' ?>" href="<?= url('recruiter/follow_up.php') ?>"><i class="fas fa-route"></i> Follow-Up Pipeline</a></li><li><a class="sidebar__link <?= in_array($currentPage,['communications'],true)?'active':'' ?>" href="<?= url('recruiter/communications.php') ?>"><i class="fas fa-comments"></i> Communications</a></li><li><a class="sidebar__link <?= in_array($currentPage,['engagement_automation'],true)?'active':'' ?>" href="<?= url('recruiter/engagement_automation.php') ?>"><i class="fas fa-bell"></i> Engagement &amp; Reminders<?php if (!empty($eaNotice['due'])): ?> <span class="ea-nav-badge" title="<?= (int)$eaNotice['due'] ?> reminder(s) due"><?= (int)$eaNotice['due'] > 99 ? '99+' : (int)$eaNotice['due'] ?></span><?php endif; ?></a></li><li><a class="sidebar__link <?= in_array($currentPage,['interviews'],true)?'active':'' ?>" href="<?= url('recruiter/interviews.php') ?>"><i class="fas fa-calendar-check"></i> Interviews</a></li><li><a class="sidebar__link <?= in_array($currentPage,['interview_workspace'],true)?'active':'' ?>" href="<?= url('recruiter/interview_workspace.php') ?>"><i class="fas fa-calendar-days"></i> Interview Workspace</a></li><li><a class="sidebar__link <?= in_array($currentPage,['interview_decisions'],true)?'active':'' ?>" href="<?= url('recruiter/interview_decisions.php') ?>"><i class="fas fa-gavel"></i> Results &amp; Decisions</a></li><li><a class="sidebar__link <?= in_array($currentPage,['talent_pool'],true)?'active':'' ?>" href="<?= url('recruiter/talent_pool.php') ?>"><i class="fas fa-users"></i> Talent Pool</a></li><li><a class="sidebar__link <?= in_array($currentPage,['candidate_duplicates'],true)?'active':'' ?>" href="<?= url('recruiter/candidate_duplicates.php') ?>"><i class="fas fa-copy"></i> Duplicate Detection</a></li></ul>
    <div class="sidebar__section-label">Offers</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= in_array($currentPage,['offers'],true)?'active':'' ?>" href="<?= url('recruiter/offers.php') ?>"><i class="fas fa-file-signature"></i> Offers &amp; Placements</a></li></ul>
    <div class="sidebar__section-label">Insights</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= in_array($currentPage,['reports'],true)?'active':'' ?>" href="<?= url('recruiter/reports.php') ?>"><i class="fas fa-chart-column"></i> Reports</a></li><li><a class="sidebar__link <?= in_array($currentPage,['analytics'],true)?'active':'' ?>" href="<?= url('recruiter/analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics &amp; Reporting</a></li><li><a class="sidebar__link <?= in_array($currentPage,['productivity'],true)?'active':'' ?>" href="<?= url('recruiter/productivity.php') ?>"><i class="fas fa-gauge-high"></i> Activity &amp; Productivity</a></li><li><a class="sidebar__link <?= in_array($currentPage,['pipeline_bottlenecks'],true)?'active':'' ?>" href="<?= url('recruiter/pipeline_bottlenecks.php') ?>"><i class="fas fa-filter"></i> Pipeline Bottlenecks</a></li><li><a class="sidebar__link <?= in_array($currentPage,['activity_log'],true)?'active':'' ?>" href="<?= url('recruiter/activity_log.php') ?>"><i class="fas fa-clock-rotate-left"></i> Activity Log</a></li></ul>
    <div class="sidebar__section-label">Communication</div><ul class="sidebar__menu"><li><a class="sidebar__link <?= ($currentPage ?? '') === 'announcements' ? 'active' : '' ?>" href="<?= url('communications/announcements.php') ?>"><i class="fas fa-bullhorn"></i> Announcements</a></li></ul>
  </nav>
  <div class="sidebar__footer"><div class="sidebar__user"><div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a56db&color=fff&size=80" alt=""></div><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($name) ?></span><span class="sidebar__user-role">Recruiter</span></div></div><a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
</aside><div class="sidebar-overlay" id="portalSidebarOverlay"></div>
<?php if (!empty($eaNotice['due'])): ?>
<style>
.ea-nav-badge{display:inline-block;min-width:20px;padding:1px 6px;margin-left:8px;border-radius:999px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;line-height:18px;text-align:center}
.ea-toast{position:fixed;right:20px;bottom:20px;z-index:9999;width:min(380px,calc(100vw - 32px));border-radius:16px;background:#fff;color:#172b4d;box-shadow:0 18px 60px rgba(15,23,42,.30);border:1px solid rgba(127,127,127,.18);overflow:hidden}
.ea-toast__head{display:flex;align-items:center;gap:10px;padding:14px 16px;background:#2454d8;color:#fff}
.ea-toast__head strong{flex:1;font-size:15px}
.ea-toast__close{border:0;background:rgba(255,255,255,.18);color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer}
.ea-toast__list{list-style:none;margin:0;padding:8px 16px}
.ea-toast__list li{display:flex;gap:8px;align-items:flex-start;padding:7px 0;font-size:13px;border-bottom:1px solid rgba(127,127,127,.14)}
.ea-toast__list li:last-child{border-bottom:0}
.ea-toast__dot{flex:0 0 8px;height:8px;margin-top:6px;border-radius:50%;background:#94a3b8}
.ea-toast__dot.is-high{background:#dc2626}
.ea-toast__foot{padding:10px 16px 14px;display:flex;gap:8px}
.ea-toast__btn{flex:1;display:block;text-align:center;padding:9px 12px;border-radius:10px;background:#2454d8;color:#fff;font-weight:600;text-decoration:none;font-size:14px}
.ea-toast__btn--ghost{background:rgba(127,127,127,.14);color:inherit;border:0;cursor:pointer;font:inherit;font-weight:600}
html[data-theme="dark"] .ea-toast,body.dark-mode .ea-toast{background:#111827;color:#f8fafc}
</style>
<?php if (!empty($eaNotice['popup'])): ?>
<div class="ea-toast" id="eaToast" role="alert" aria-live="polite">
  <div class="ea-toast__head">
    <i class="fas fa-bell"></i>
    <strong><?= (int)$eaNotice['due'] ?> follow-up reminder<?= (int)$eaNotice['due'] === 1 ? '' : 's' ?> due<?= !empty($eaNotice['high']) ? ' · ' . (int)$eaNotice['high'] . ' high priority' : '' ?></strong>
    <button type="button" class="ea-toast__close" id="eaToastClose" aria-label="Dismiss"><i class="fas fa-times"></i></button>
  </div>
  <ul class="ea-toast__list">
    <?php foreach ($eaNotice['items'] as $eaItem): ?>
      <li><span class="ea-toast__dot <?= ($eaItem['priority'] ?? '') === 'high' ? 'is-high' : '' ?>"></span><span><?= e((string)$eaItem['title']) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <div class="ea-toast__foot">
    <a class="ea-toast__btn" href="<?= url('recruiter/engagement_automation.php') ?>">Review reminders</a>
    <button type="button" class="ea-toast__btn ea-toast__btn--ghost" id="eaToastLater">Later</button>
  </div>
</div>
<script>
(function(){var t=document.getElementById('eaToast');if(!t)return;function c(){t.style.display='none';}
['eaToastClose','eaToastLater'].forEach(function(i){var b=document.getElementById(i);if(b)b.addEventListener('click',c);});})();
</script>
<?php endif; ?>
<?php endif; ?>
