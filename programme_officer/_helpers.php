<?php
if (!function_exists('po_has_column')) {
    function po_has_column(mysqli $conn, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        if (!$stmt) return $cache[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $cache[$key] = ((int)($row['total'] ?? 0) > 0);
    }
}
if (!function_exists('po_scope')) {
    function po_scope(mysqli $conn, string $programmeAlias='p', string $cohortAlias='c'): array {
        if (po_has_column($conn,'programmes','programme_officer_id')) return ['condition'=>"{$programmeAlias}.programme_officer_id = ?",'mode'=>'programme'];
        if (po_has_column($conn,'cohorts','programme_officer_id')) return ['condition'=>"{$cohortAlias}.programme_officer_id = ?",'mode'=>'cohort'];
        return ['condition'=>'1 = 0','mode'=>'none'];
    }
}
if (!function_exists('po_status_label')) {
    function po_status_label(?string $status): string { $s=trim((string)$status); return $s===''?'Unknown':ucwords(str_replace('_',' ',$s)); }
}
if (!function_exists('po_status_class')) {
    function po_status_class(?string $status): string {
        return match (strtolower(trim((string)$status))) {
            'active' => 'active', 'completed' => 'completed', 'withdrawn','rejected','inactive' => 'danger',
            'pending','draft','submitted','waitlisted','upcoming' => 'warning',
            'selected','screened','assessment','interview','eligibility_review','onboarded' => 'info', default => 'neutral'
        };
    }
}
if (!function_exists('po_date')) {
    function po_date(?string $date,string $fallback='Not set'): string { if(!$date||$date==='0000-00-00'||$date==='0000-00-00 00:00:00') return $fallback; $ts=strtotime($date); return $ts?date('d M Y',$ts):$fallback; }
}
if (!function_exists('po_datetime')) {
    function po_datetime(?string $date,string $fallback='Not available'): string { if(!$date) return $fallback; $ts=strtotime($date); return $ts?date('d M Y, H:i',$ts):$fallback; }
}
if (!function_exists('po_initials')) {
    function po_initials(string $first,string $last): string { $i=''; if($first!=='')$i.=strtoupper(substr($first,0,1)); if($last!=='')$i.=strtoupper(substr($last,0,1)); return $i!==''?$i:'PO'; }
}
if (!function_exists('po_completion_rate')) {
    function po_completion_rate(int $total,int $completed): int { return $total<=0?0:min(100,max(0,(int)round(($completed/$total)*100))); }
}
if (!function_exists('po_activity_table_exists')) {
    function po_activity_table_exists(mysqli $conn): bool { $r=$conn->query("SHOW TABLES LIKE 'programme_officer_activity_log'"); return $r && $r->num_rows>0; }
}
