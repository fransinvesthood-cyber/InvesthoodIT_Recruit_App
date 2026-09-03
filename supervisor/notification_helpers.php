<?php
/**
 * ================================================================
 * INVESTHOOD IT - SUPERVISOR NOTIFICATION HELPERS
 * ================================================================
 */

if (!function_exists('createSupervisorNotification')) {

    /**
     * Create a Supervisor notification.
     */
    function createSupervisorNotification(
        int $supervisorId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null
    ): bool {

        if ($supervisorId <= 0) {
            return false;
        }

        $title = trim($title);
        $message = trim($message);
        $type = strtolower(trim($type));

        if (
            $title === ''
            ||
            $message === ''
        ) {
            return false;
        }

        $allowedTypes = [
            'info',
            'success',
            'warning',
            'danger',
            'candidate',
            'cohort'
        ];

        if (
            !in_array(
                $type,
                $allowedTypes,
                true
            )
        ) {
            $type = 'info';
        }

        /*
        |--------------------------------------------------------------------------
        | Store internal application path only
        |--------------------------------------------------------------------------
        */

        if ($link !== null) {

            $link = trim($link);

            /*
             * Prevent javascript:, data:, external URLs, etc.
             */
            if (
                $link === ''
                ||
                preg_match(
                    '/^(?:javascript|data|vbscript):/i',
                    $link
                )
                ||
                preg_match(
                    '#^https?://#i',
                    $link
                )
                ||
                str_starts_with(
                    $link,
                    '//'
                )
            ) {
                $link = null;
            }
        }

        $stmt = Database::prepare(
            "
                INSERT INTO supervisor_notifications
                (
                    supervisor_id,
                    title,
                    message,
                    type,
                    link,
                    is_read,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    0,
                    NOW()
                )
            ",
            'issss',
            [
                $supervisorId,
                $title,
                $message,
                $type,
                $link
            ]
        );

        $success =
            $stmt->affected_rows > 0;

        $stmt->close();

        return $success;
    }
}

if (!function_exists('getSupervisorUnreadNotificationCount')) {

    /**
     * Count unread notifications.
     */
    function getSupervisorUnreadNotificationCount(
        int $supervisorId
    ): int {

        if ($supervisorId <= 0) {
            return 0;
        }

        $stmt = Database::prepare(
            "
                SELECT
                    COUNT(*) AS total

                FROM supervisor_notifications

                WHERE supervisor_id = ?
                  AND is_read = 0
            ",
            'i',
            [$supervisorId]
        );

        $row = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return (int)(
            $row['total']
            ?? 0
        );
    }
}

if (!function_exists('getSupervisorNotifications')) {

    /**
     * Return recent Supervisor notifications.
     */
    function getSupervisorNotifications(
        int $supervisorId,
        int $limit = 8
    ): array {

        if ($supervisorId <= 0) {
            return [];
        }

        $limit = max(
            1,
            min(
                20,
                $limit
            )
        );

        /*
         * LIMIT cannot reliably be parameterised through every
         * mysqli wrapper, so the integer is clamped first.
         */
        $sql = "
            SELECT
                id,
                title,
                message,
                type,
                link,
                is_read,
                read_at,
                created_at

            FROM supervisor_notifications

            WHERE supervisor_id = ?

            ORDER BY
                is_read ASC,
                created_at DESC,
                id DESC

            LIMIT {$limit}
        ";

        $stmt = Database::prepare(
            $sql,
            'i',
            [$supervisorId]
        );

        $result = $stmt->get_result();

        $notifications = [];

        while (
            $row = $result->fetch_assoc()
        ) {
            $notifications[] = $row;
        }

        $stmt->close();

        return $notifications;
    }
}

if (!function_exists('markSupervisorNotificationRead')) {

    /**
     * Mark one notification read.
     *
     * Supervisor ID is always part of the UPDATE so one Supervisor
     * cannot mark another Supervisor's notification.
     */
    function markSupervisorNotificationRead(
        int $notificationId,
        int $supervisorId
    ): bool {

        if (
            $notificationId <= 0
            ||
            $supervisorId <= 0
        ) {
            return false;
        }

        $stmt = Database::prepare(
            "
                UPDATE supervisor_notifications

                SET
                    is_read = 1,
                    read_at = COALESCE(
                        read_at,
                        NOW()
                    )

                WHERE id = ?
                  AND supervisor_id = ?

                LIMIT 1
            ",
            'ii',
            [
                $notificationId,
                $supervisorId
            ]
        );

        $success =
            $stmt->affected_rows >= 0;

        $stmt->close();

        return $success;
    }
}

if (!function_exists('markAllSupervisorNotificationsRead')) {

    /**
     * Mark all Supervisor notifications read.
     */
    function markAllSupervisorNotificationsRead(
        int $supervisorId
    ): bool {

        if ($supervisorId <= 0) {
            return false;
        }

        $stmt = Database::prepare(
            "
                UPDATE supervisor_notifications

                SET
                    is_read = 1,
                    read_at = COALESCE(
                        read_at,
                        NOW()
                    )

                WHERE supervisor_id = ?
                  AND is_read = 0
            ",
            'i',
            [$supervisorId]
        );

        $success =
            $stmt->affected_rows >= 0;

        $stmt->close();

        return $success;
    }
}

if (!function_exists('supervisorNotificationTimeAgo')) {

    /**
     * Human readable notification time.
     */
    function supervisorNotificationTimeAgo(
        ?string $date
    ): string {

        if (empty($date)) {
            return 'Recently';
        }

        $timestamp =
            strtotime($date);

        if (!$timestamp) {
            return 'Recently';
        }

        $difference =
            time() - $timestamp;

        if ($difference < 0) {
            return date(
                'd M Y, H:i',
                $timestamp
            );
        }

        if ($difference < 60) {
            return 'Just now';
        }

        if ($difference < 3600) {

            $minutes =
                (int)floor(
                    $difference / 60
                );

            return $minutes .
                (
                    $minutes === 1
                        ? ' minute ago'
                        : ' minutes ago'
                );
        }

        if ($difference < 86400) {

            $hours =
                (int)floor(
                    $difference / 3600
                );

            return $hours .
                (
                    $hours === 1
                        ? ' hour ago'
                        : ' hours ago'
                );
        }

        if ($difference < 604800) {

            $days =
                (int)floor(
                    $difference / 86400
                );

            return $days .
                (
                    $days === 1
                        ? ' day ago'
                        : ' days ago'
                );
        }

        return date(
            'd M Y, H:i',
            $timestamp
        );
    }
}

if (!function_exists('supervisorNotificationIcon')) {

    function supervisorNotificationIcon(
        string $type
    ): string {

        switch (
            strtolower($type)
        ) {

            case 'candidate':
                return 'fa-user';

            case 'cohort':
                return 'fa-people-group';

            case 'success':
                return 'fa-circle-check';

            case 'warning':
                return 'fa-triangle-exclamation';

            case 'danger':
                return 'fa-circle-exclamation';

            default:
                return 'fa-bell';
        }
    }
}

if (!function_exists('supervisorNotificationTypeClass')) {

    function supervisorNotificationTypeClass(
        string $type
    ): string {

        $allowed = [
            'info',
            'success',
            'warning',
            'danger',
            'candidate',
            'cohort'
        ];

        $type = strtolower(
            trim($type)
        );

        return in_array(
            $type,
            $allowed,
            true
        )
            ? $type
            : 'info';
    }
}