<?php
require_once __DIR__ . '/_helpers.php';

$currentPage = $currentPage ?? '';
if (!isset($user) || !is_array($user)) {
    $user = current_user();
}

$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$fullName = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($firstName . ' ' . $lastName)));
if ($fullName === '') $fullName = 'Programme Officer';
$initials = po_initials($firstName, $lastName);

/*
 * HOTFIX HEADER ONLY:
 * Keep the rest of your existing file markup below this point.
 */
