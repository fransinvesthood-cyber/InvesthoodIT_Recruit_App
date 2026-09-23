<?php
/**
 * Investhood IT - Cancel Placement (Stage 12)
 */

require_once __DIR__ . '/../php/config/database.php';

session_start();
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login.php'); exit; }

$roleSlug = $user['role_slug'] ?? '';
if (!in_array($roleSlug, ['admin', 'programme_manager', 'programme_officer'])) {
    header('Location: /unauthorized.php'); exit;
}

$currentUserId = (int)$user['id'];
$placementId = (int)($_GET['id'] ?? 0);

if (!$placementId) { header('Location: placements.php'); exit; }

$placement = dbFetchOne("SELECT * FROM placements WHERE id = ?", [$placementId], 'i');
if (!$placement) { header('Location: placements.php'); exit; }

if ($placement['status'] === 'cancelled') { header('Location: view-placement.php?id=' . $placementId); exit; }

// Get cancellation reason
$reason = trim($_POST['reason'] ?? 'Cancelled by administrator');

// Update status
dbExecute("UPDATE placements SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), '\n\n[CANCELLED] ', ?) WHERE id = ?", [$reason, $placementId], 'si');

// Log history
dbExecute("INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, 'status', ?, 'cancelled', ?, ?)", [$placementId, $placement['status'], $currentUserId, 'Placement cancelled' . ($reason ? ": $reason" : '')], 'isss');

// Create notification
dbExecute("INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, ?, 'placement_cancelled', 'Placement Cancelled', ?)", [$placementId, $placement['candidate_id'], $currentUserId, "Your placement has been cancelled. Reason: $reason"], 'iisss');

// Audit log
dbExecute("INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES (?, 'Placement Cancelled', 'placement', ?, ?, ?)", [$currentUserId, $placementId, $reason, $_SERVER['REMOTE_ADDR'] ?? '::1'], 'iiss');

header('Location: view-placement.php?id=' . $placementId . '&cancelled=1');
exit;
