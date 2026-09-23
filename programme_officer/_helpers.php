<?php

if (!function_exists('po_has_column')) {
    function po_has_column(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");

        if (!$stmt) {
            return $cache[$key] = false;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $cache[$key] = ((int)($row['total'] ?? 0) > 0);
    }
}

if (!function_exists('po_scope')) {
    function po_scope(
        mysqli $conn,
        string $programmeAlias = 'p',
        string $cohortAlias = 'c'
    ): array {
        if (po_has_column($conn, 'programmes', 'programme_officer_id')) {
            return [
                'condition' => "{$programmeAlias}.programme_officer_id = ?",
                'mode' => 'programme',
            ];
        }

        if (po_has_column($conn, 'cohorts', 'programme_officer_id')) {
            return [
                'condition' => "{$cohortAlias}.programme_officer_id = ?",
                'mode' => 'cohort',
            ];
        }

        /*
         * The original Investhood schema supplied with this project has no
         * programmes.programme_officer_id or cohorts.programme_officer_id.
         * Programme Officers are therefore a portfolio-level operational role.
         * Keep an authenticated-user placeholder so existing prepared statements
         * remain compatible while exposing the real programme portfolio.
         */
        return [
            'condition' => '(? > 0)',
            'mode' => 'portfolio',
        ];
    }
}

if (!function_exists('po_scope_mode')) {
    function po_scope_mode(array $scope): string
    {
        return (string)($scope['mode'] ?? 'none');
    }
}

if (!function_exists('po_scope_condition')) {
    function po_scope_condition(array $scope): string
    {
        return (string)($scope['condition'] ?? '1 = 0');
    }
}

if (!function_exists('po_assignment_count')) {
    function po_assignment_count(mysqli $conn, int $officerId): int
    {
        if ($officerId <= 0) {
            return 0;
        }

        if (po_has_column($conn, 'programmes', 'programme_officer_id')) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM programmes
                WHERE programme_officer_id = ?
            ");
        } elseif (po_has_column($conn, 'cohorts', 'programme_officer_id')) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM cohorts
                WHERE programme_officer_id = ?
            ");
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM programmes");
            if (!$stmt) {
                return 0;
            }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int)($row['total'] ?? 0);
        }

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $officerId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('po_status_label')) {
    function po_status_label(?string $status): string
    {
        $status = trim((string)$status);

        return $status === ''
            ? 'Unknown'
            : ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('po_status_class')) {
    function po_status_class(?string $status): string
    {
        return match (strtolower(trim((string)$status))) {
            'active' => 'active',
            'completed' => 'completed',
            'withdrawn', 'rejected', 'inactive' => 'danger',
            'pending', 'draft', 'submitted', 'waitlisted', 'upcoming' => 'warning',
            'selected', 'screened', 'assessment', 'interview', 'eligibility_review', 'onboarded' => 'info',
            default => 'neutral',
        };
    }
}

if (!function_exists('po_date')) {
    function po_date(?string $date, string $fallback = 'Not set'): string
    {
        if (
            !$date
            || $date === '0000-00-00'
            || $date === '0000-00-00 00:00:00'
        ) {
            return $fallback;
        }

        $timestamp = strtotime($date);

        return $timestamp
            ? date('d M Y', $timestamp)
            : $fallback;
    }
}

if (!function_exists('po_datetime')) {
    function po_datetime(?string $date, string $fallback = 'Not available'): string
    {
        if (!$date) {
            return $fallback;
        }

        $timestamp = strtotime($date);

        return $timestamp
            ? date('d M Y, H:i', $timestamp)
            : $fallback;
    }
}

if (!function_exists('po_initials')) {
    function po_initials(string $first, string $last): string
    {
        $first = trim($first);
        $last = trim($last);

        $initials = '';

        if ($first !== '') {
            $initials .= strtoupper(substr($first, 0, 1));
        }

        if ($last !== '') {
            $initials .= strtoupper(substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'PO';
    }
}

if (!function_exists('po_completion_rate')) {
    function po_completion_rate(int $total, int $completed): int
    {
        if ($total <= 0) {
            return 0;
        }

        return min(
            100,
            max(
                0,
                (int)round(($completed / $total) * 100)
            )
        );
    }
}

if (!function_exists('po_activity_table_exists')) {
    function po_activity_table_exists(mysqli $conn): bool
    {
        $result = $conn->query("
            SHOW TABLES LIKE 'programme_officer_activity_log'
        ");

        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('po_query_value')) {
    function po_query_value(string $key, string $default = ''): string
    {
        return trim((string)($_GET[$key] ?? $default));
    }
}

if (!function_exists('po_query_int')) {
    function po_query_int(string $key, int $default = 0): int
    {
        return (int)($_GET[$key] ?? $default);
    }
}

if (!function_exists('po_notifications')) { function po_notifications(mysqli $c,int $uid,int $limit=6): array { if($uid<=0)return ['items'=>[],'unread'=>0,'available'=>false];$r=$c->query("SHOW TABLES LIKE 'notifications'");if(!$r||!$r->num_rows)return ['items'=>[],'unread'=>0,'available'=>false];$cols=[];$cr=$c->query("SHOW COLUMNS FROM notifications");while($cr&&$x=$cr->fetch_assoc())$cols[$x['Field']]=true;$uc=isset($cols['user_id'])?'user_id':(isset($cols['recipient_id'])?'recipient_id':null);$mc=isset($cols['message'])?'message':(isset($cols['body'])?'body':(isset($cols['description'])?'description':null));$tc=isset($cols['title'])?'title':null;$rc=isset($cols['is_read'])?'is_read':(isset($cols['read_at'])?'read_at':null);$dc=isset($cols['created_at'])?'created_at':null;if(!$uc||!$mc)return ['items'=>[],'unread'=>0,'available'=>false];$te=$tc?$tc:"'Notification'";$de=$dc?$dc:'NULL';$re=$rc==='is_read'?'is_read':($rc==='read_at'?'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END':'1');$sql="SELECT id,$te AS title,$mc AS message,$de AS created_at,$re AS is_read FROM notifications WHERE $uc=? ORDER BY ".($dc?$dc:'id')." DESC LIMIT ?";$st=$c->prepare($sql);if(!$st)return ['items'=>[],'unread'=>0,'available'=>false];$st->bind_param('ii',$uid,$limit);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();$unread=0;foreach($items as $n)if((int)($n['is_read']??1)===0)$unread++;return ['items'=>$items,'unread'=>$unread,'available'=>true]; } }
