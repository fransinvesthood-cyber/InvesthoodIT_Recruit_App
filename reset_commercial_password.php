
<?php
require_once __DIR__ . '/includes/bootstrap.php';

$conn = Database::getConnection();

$email = 'commercial_manager@investhoodit.co.za';
$newPassword = 'Investhood@2025';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    'UPDATE users
     SET password_hash = ?, updated_at = NOW()
     WHERE email = ?'
);

$stmt->bind_param('ss', $newHash, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo 'Password hash updated successfully.';
} else {
    echo 'No row was updated. Check that the email exists and inspect the current password hash.';
}

$stmt->close();
