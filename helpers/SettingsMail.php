<?php
/**
 * INVESTHOOD IT - Settings-aware mail gate
 * Single place that decides whether a platform email
 * may be sent, based on the admin notification toggles.
 * Never exposes SMTP secrets — those stay in env config.
 */

class SettingsMail
{
    /**
     * Whether any email of a given type may be sent.
     * $type: registration|verification|application_status
     *        |interview|offer|placement|admin
     */
    public static function allowed(string $type): bool
    {
        if (!class_exists('SystemSettings')) {
            return true;
        }
        try {
            if (!SystemSettings::getBool('mail_notifications_enabled', true)) {
                return false;
            }
            $map = [
                'registration' => 'notif_registration',
                'verification' => 'notif_verification',
                'application_status' => 'notif_application_status',
                'interview' => 'notif_interview',
                'offer' => 'notif_offer',
                'placement' => 'notif_placement',
                'admin' => 'notif_admin_alerts',
            ];
            $key = $map[$type] ?? null;
            if ($key === null) {
                return true;
            }
            return SystemSettings::getBool($key, true);
        } catch (Throwable $ex) {
            return true;
        }
    }
}
