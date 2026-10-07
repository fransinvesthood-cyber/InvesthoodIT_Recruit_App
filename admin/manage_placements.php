<?php
/**
 * Legacy alias — redirects to the canonical placements.php list page.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
safe_redirect('admin/placements.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
