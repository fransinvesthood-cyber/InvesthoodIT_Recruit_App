<?php
// ============================================================================
// Progress Update History helpers.
// Include from any supervisor page with:
//     require_once __DIR__ . '/_progress_history.php';
// Every function is guarded and failure-safe: logging can never break a page.
// The table is created automatically on first use (CREATE TABLE IF NOT EXISTS),
// so no manual migration is required.
// ============================================================================

if (!function_exists('sv_ph_stages')) {
    /** Progress stages a supervisor can tag an update with. */
    function sv_ph_stages(): array {
        return [
            'onboarding'        => 'Onboarding',
            'in_progress'       => 'In Progress',
            'mid_review'        => 'Mid-programme Review',
            'assessment'        => 'Assessment',
            'final_review'      => 'Final Review',
            'completion'        => 'Completion',
            'withdrawal'        => 'Withdrawal',
            'other'             => 'Other',
        ];
    }
}

if (!function_exists('sv_ph_stage_label')) {
    function sv_ph_stage_label(?string $stage): string {
        $stage = trim((string)$stage);
        if ($stage === '') return 'Not specified';
        $all = sv_ph_stages();
        return $all[$stage] ?? ucwords(str_replace('_', ' ', $stage));
    }
}

if (!function_exists('sv_ph_ready')) {
    /** Ensures the history table exists. Returns false if it cannot be used. */
    function sv_ph_ready(): bool {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $conn = Database::getConnection();
            $ok = $conn->query(
                "CREATE TABLE IF NOT EXISTS candidate_progress_history (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    candidate_id INT NOT NULL,
                    participation_id INT NULL,
                    cohort_id INT NOT NULL,
                    programme_id INT NULL,
                    cohort_name VARCHAR(255) NULL,
                    programme_name VARCHAR(255) NULL,
                    previous_status VARCHAR(50) NULL,
                    new_status VARCHAR(50) NOT NULL,
                    progress_stage VARCHAR(50) NULL,
                    notes TEXT NULL,
                    updated_by INT NOT NULL,
                    updated_by_name VARCHAR(255) NULL,
                    ip_address VARCHAR(45) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_cph_candidate (candidate_id, created_at),
                    KEY idx_cph_cohort (cohort_id),
                    KEY idx_cph_updater (updated_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $ready = (bool)$ok;
        } catch (Throwable $ex) {
            error_log('sv_ph_ready failed: ' . $ex->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('sv_ph_record')) {
    /**
     * Record one progress update.
     * Returns true when the row was stored. Never throws.
     */
    function sv_ph_record(
        int $candidateId,
        int $cohortId,
        int $supervisorId,
        string $supervisorName,
        string $previousStatus,
        string $newStatus,
        ?string $stage,
        ?string $notes,
        ?int $participationId = null
    ): bool {
        try {
            if ($candidateId <= 0 || $cohortId <= 0 || $supervisorId <= 0 || $newStatus === '') return false;
            if (!sv_ph_ready()) return false;

            // Snapshot programme / cohort info so history stays accurate if they are renamed later.
            $stmt = Database::prepare(
                "SELECT c.name AS cohort_name, p.id AS programme_id, p.name AS programme_name FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE c.id=? LIMIT 1",
                'i',
                [$cohortId]
            );
            $info = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();

            $stage = trim((string)$stage);
            if ($stage !== '' && !array_key_exists($stage, sv_ph_stages())) $stage = 'other';
            $notes = trim((string)$notes);
            $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 2000) : substr($notes, 0, 2000);
            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

            $cohortName = (string)($info['cohort_name'] ?? '');
            $programmeId = isset($info['programme_id']) ? (int)$info['programme_id'] : null;
            $programmeName = (string)($info['programme_name'] ?? '');
            $stageVal = $stage !== '' ? $stage : null;
            $notesVal = $notes !== '' ? $notes : null;

            $ins = Database::prepare(
                "INSERT INTO candidate_progress_history (candidate_id,participation_id,cohort_id,programme_id,cohort_name,programme_name,previous_status,new_status,progress_stage,notes,updated_by,updated_by_name,ip_address,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
                'iiiissssssiss',
                [$candidateId, $participationId, $cohortId, $programmeId, $cohortName, $programmeName, $previousStatus, $newStatus, $stageVal, $notesVal, $supervisorId, $supervisorName, $ip]
            );
            if ($ins) $ins->close();
            return true;
        } catch (Throwable $ex) {
            error_log('sv_ph_record failed: ' . $ex->getMessage());
            return false;
        }
    }
}

if (!function_exists('sv_ph_fetch')) {
    /**
     * Newest-first history for a candidate, restricted to cohorts supervised by $supervisorId.
     * Pass $cohortId > 0 to limit to a single cohort.
     */
    function sv_ph_fetch(int $candidateId, int $supervisorId, int $cohortId = 0, int $limit = 200): array {
        try {
            if ($candidateId <= 0 || $supervisorId <= 0 || !sv_ph_ready()) return [];
            $limit = max(1, min(500, $limit));
            $sql = "SELECT h.* FROM candidate_progress_history h JOIN cohorts c ON c.id=h.cohort_id WHERE h.candidate_id=? AND c.supervisor_id=?";
            $types = 'ii';
            $params = [$candidateId, $supervisorId];
            if ($cohortId > 0) { $sql .= ' AND h.cohort_id=?'; $types .= 'i'; $params[] = $cohortId; }
            $sql .= " ORDER BY h.created_at DESC, h.id DESC LIMIT " . $limit;
            $stmt = Database::prepare($sql, $types, $params);
            $rows = [];
            $res = $stmt->get_result();
            while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
            $stmt->close();
            return $rows;
        } catch (Throwable $ex) {
            error_log('sv_ph_fetch failed: ' . $ex->getMessage());
            return [];
        }
    }
}

if (!function_exists('sv_ph_render')) {
    /** Renders the history timeline (latest first). Self-contained styles. */
    function sv_ph_render(array $rows): void {
        static $css = false;
        if (!$css) {
            $css = true;
            echo '<style>
.sv-ph{position:relative;padding:4px 0 0 4px}
.sv-ph__item{position:relative;display:flex;gap:14px;padding:0 0 22px}
.sv-ph__item:last-child{padding-bottom:4px}
.sv-ph__item:not(:last-child)::before{content:"";position:absolute;left:15px;top:34px;bottom:0;width:2px;background:var(--sv-border,#e4e7ec)}
.sv-ph__dot{width:32px;height:32px;flex:0 0 32px;border-radius:10px;display:grid;place-items:center;font-size:12px;background:rgba(37,99,235,.10);color:#2563eb}
.sv-ph__item--latest .sv-ph__dot{background:#2563eb;color:#fff}
.sv-ph__main{min-width:0;flex:1}
.sv-ph__top{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px}
.sv-ph__change{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:800}
.sv-ph__change i{font-size:10px;color:#98a2b3}
.sv-ph__badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:rgba(37,99,235,.10);color:#2563eb}
.sv-ph__latest{display:inline-block;padding:2px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;background:rgba(22,163,74,.12);color:#16a34a}
.sv-ph__notes{margin:8px 0 0;padding:10px 12px;border-radius:10px;font-size:12px;line-height:1.55;white-space:pre-wrap;background:var(--sv-soft,#f8fafc);border:1px solid var(--sv-border,#e4e7ec)}
.sv-ph__meta{display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:8px;color:var(--sv-muted,#667085);font-size:10px}
.sv-ph__meta span{display:inline-flex;align-items:center;gap:5px}
.sv-ph__empty{padding:34px 16px;text-align:center;color:var(--sv-muted,#667085)}
.sv-ph__empty i{display:block;margin-bottom:10px;font-size:26px;color:#98a2b3}
.sv-ph__empty strong{display:block;font-size:13px;color:inherit}
html[data-theme="dark"] .sv-ph__notes{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09)}
html[data-theme="dark"] .sv-ph__item:not(:last-child)::before{background:rgba(255,255,255,.09)}
</style>';
        }
        if (!$rows) {
            echo '<div class="sv-ph__empty"><i class="fas fa-clock-rotate-left"></i><strong>No progress updates recorded yet</strong><span>Updates saved from the Update Status page will appear here.</span></div>';
            return;
        }
        echo '<div class="sv-ph">';
        foreach ($rows as $i => $r) {
            $prev = trim((string)($r['previous_status'] ?? ''));
            $new  = trim((string)($r['new_status'] ?? ''));
            $prevLabel = $prev !== '' ? sv_status_label($prev) : 'None';
            $newLabel  = sv_status_label($new);
            $changed   = strtolower($prev) !== strtolower($new);
            $stage     = trim((string)($r['progress_stage'] ?? ''));
            $notes     = trim((string)($r['notes'] ?? ''));
            $who       = trim((string)($r['updated_by_name'] ?? '')) ?: 'Supervisor';
            $cohort    = trim((string)($r['cohort_name'] ?? ''));
            $prog      = trim((string)($r['programme_name'] ?? ''));

            echo '<div class="sv-ph__item' . ($i === 0 ? ' sv-ph__item--latest' : '') . '">';
            echo '<div class="sv-ph__dot"><i class="fas ' . ($changed ? 'fa-arrows-rotate' : 'fa-pen') . '"></i></div>';
            echo '<div class="sv-ph__main"><div class="sv-ph__top">';
            if ($changed) {
                echo '<span class="sv-ph__change"><span class="sv-status sv-status--' . e(sv_status_class($prev)) . '">' . e($prevLabel) . '</span><i class="fas fa-arrow-right"></i><span class="sv-status sv-status--' . e(sv_status_class($new)) . '">' . e($newLabel) . '</span></span>';
            } else {
                echo '<span class="sv-ph__change">Status unchanged <span class="sv-status sv-status--' . e(sv_status_class($new)) . '">' . e($newLabel) . '</span></span>';
            }
            if ($stage !== '') echo '<span class="sv-ph__badge">' . e(sv_ph_stage_label($stage)) . '</span>';
            if ($i === 0) echo '<span class="sv-ph__latest">Latest</span>';
            echo '</div>';
            if ($notes !== '') echo '<div class="sv-ph__notes">' . e($notes) . '</div>';
            echo '<div class="sv-ph__meta">';
            echo '<span><i class="fas fa-user"></i>' . e($who) . '</span>';
            echo '<span><i class="fas fa-calendar"></i>' . e(sv_datetime((string)($r['created_at'] ?? ''))) . '</span>';
            if ($cohort !== '') echo '<span><i class="fas fa-layer-group"></i>' . e($cohort) . '</span>';
            if ($prog !== '') echo '<span><i class="fas fa-graduation-cap"></i>' . e($prog) . '</span>';
            echo '</div></div></div>';
        }
        echo '</div>';
    }
}
