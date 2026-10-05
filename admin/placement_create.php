<?php
/**
 * Legacy alias — redirects to the canonical create-placement.php page.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
safe_redirect('admin/create-placement.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
