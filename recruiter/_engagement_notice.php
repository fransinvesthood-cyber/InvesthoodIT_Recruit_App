<?php
/**
 * Reminder notice for the recruiter portal.
 *
 * Included from sidebar.php, so it runs on every recruiter page. On the first
 * page after login (and then at most every EA_AUTO_INTERVAL seconds) it
 * generates the recruiter's reminders, then exposes:
 *
 *   $eaNotice['due']        number of reminders due now
 *   $eaNotice['high']       how many of those are high priority
 *   $eaNotice['items']      up to 5 most urgent due reminders
 *   $eaNotice['popup']      true when the login pop-up should be shown
 *
 * It never throws and never stops the page from rendering.
 */

$eaNotice = ['due' => 0, 'high' => 0, 'items' => [], 'popup' => false];

try {
    if (
        function_exists('current_user')
        && class_exists('Database')
        && session_status() === PHP_SESSION_ACTIVE
    ) {
        $eaUser = current_user();
        $eaRid  = (int)($eaUser['id'] ?? $eaUser['user_id'] ?? 0);

        if ($eaRid > 0) {

            require_once __DIR__ . '/_engagement_engine.php';

            $eaConn = Database::getConnection();

            // Schema check and default rules: once per login session.
            if (empty($_SESSION['ea_notice_ready_' . $eaRid])) {
                $eaErr   = null;
                $eaReady = ea_ensure_tables($eaConn, $eaErr)
                    && rc_has_table($eaConn, 'applications')
                    && rc_has_table($eaConn, 'opportunities');

                if ($eaReady) {
                    ea_seed_defaults($eaConn, $eaRid);
                    $_SESSION['ea_notice_ready_' . $eaRid] = 1;
                }
            }

            if (!empty($_SESSION['ea_notice_ready_' . $eaRid])) {

                $eaName = trim((string)($eaUser['full_name'] ?? $eaUser['fullname'] ?? trim(($eaUser['first_name'] ?? '') . ' ' . ($eaUser['last_name'] ?? ''))));
                if ($eaName === '') {
                    $eaName = 'Recruiter';
                }

                // Generate reminders on login, then at most every EA_AUTO_INTERVAL seconds.
                $eaKey = 'ea_last_run_' . $eaRid;
                if (
                    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
                    && time() - (int)($_SESSION[$eaKey] ?? 0) >= EA_AUTO_INTERVAL
                ) {
                    $_SESSION[$eaKey] = time();
                    ea_run_all($eaConn, $eaRid, $eaName, rc_has_table($eaConn, 'recruiter_interviews'));
                }

                $eaNow = date('Y-m-d H:i:s');

                $eaRows = ea_fetch_all(
                    $eaConn,
                    "SELECT
                        COUNT(*) AS due,
                        COALESCE(SUM(priority = 'high'), 0) AS high
                     FROM ea_reminders
                     WHERE recruiter_id = ? AND status = 'pending' AND remind_at <= ?",
                    'is',
                    [$eaRid, $eaNow]
                );

                if ($eaRows) {
                    $eaNotice['due']  = (int)$eaRows[0]['due'];
                    $eaNotice['high'] = (int)$eaRows[0]['high'];
                }

                if ($eaNotice['due'] > 0) {
                    $eaNotice['items'] = ea_fetch_all(
                        $eaConn,
                        "SELECT id, title, priority, remind_at
                         FROM ea_reminders
                         WHERE recruiter_id = ? AND status = 'pending' AND remind_at <= ?
                         ORDER BY FIELD(priority, 'high', 'normal', 'low'), remind_at ASC
                         LIMIT 5",
                        'is',
                        [$eaRid, $eaNow]
                    );

                    // Pop-up: once after login, then again only after 4 hours.
                    $eaSeenKey = 'ea_popup_seen_' . $eaRid;
                    if (
                        ($eaCurrentPage ?? ($currentPage ?? '')) !== 'engagement_automation'
                        && time() - (int)($_SESSION[$eaSeenKey] ?? 0) >= 14400
                    ) {
                        $eaNotice['popup'] = true;
                        $_SESSION[$eaSeenKey] = time();
                    }
                }
            }
        }
    }
} catch (Throwable $eaThrown) {
    error_log('_engagement_notice.php: ' . $eaThrown->getMessage());
    $eaNotice = ['due' => 0, 'high' => 0, 'items' => [], 'popup' => false];
}
