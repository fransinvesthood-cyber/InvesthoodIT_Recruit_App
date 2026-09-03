<?php
/**
 * ================================================================
 * SUPERVISOR ACTIVITY LOG HELPERS
 * ================================================================
 */
if (!function_exists('recordSupervisorActivity')) {
    function recordSupervisorActivity(
        int $supervisorId,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null
    ): bool {
        if ($supervisorId <= 0) {
            return false;
        }
        $action = trim($action);
        $description = trim($description);
        if (
            $action === ''
            ||
            $description === ''
        ) {
            return false;
        }
        /*
        |--------------------------------------------------------------------------
        | Keep stored values controlled
        |--------------------------------------------------------------------------
        */
        $action = substr(
            $action,
            0,
            100
        );
        $description = substr(
            $description,
            0,
            500
        );
        if ($entityType !== null) {
            $entityType = substr(
                trim($entityType),
                0,
                50
            );
            if ($entityType === '') {
                $entityType = null;
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Request Information
        |--------------------------------------------------------------------------
        */
        $ipAddress =
            $_SERVER['REMOTE_ADDR']
            ?? null;
        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? null;
        if ($userAgent !== null) {
            $userAgent = substr(
                $userAgent,
                0,
                255
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Insert
        |--------------------------------------------------------------------------
        */
        $stmt = Database::prepare(
            "
                INSERT INTO supervisor_activity_log
                (
                    supervisor_id,
                    action,
                    description,
                    entity_type,
                    entity_id,
                    ip_address,
                    user_agent,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ",
            'isssiss',
            [
                $supervisorId,
                $action,
                $description,
                $entityType,
                $entityId,
                $ipAddress,
                $userAgent
            ]
        );
        $success =
            $stmt->affected_rows > 0;
        $stmt->close();
        return $success;
    }
}