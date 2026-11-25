<?php
/**
 * Commercial Manager portal compatibility route.
 *
 * The previous file was an incomplete copy of the candidate Skills page:
 * it contained an unterminated PHP block and enforced the candidate role.
 * Skills management belongs to the candidate portal, not this commercial
 * management workspace. Keep this route safe for any stale bookmarks by
 * authenticating the Commercial Manager and returning them to their portal.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('commercial_manager');

header('Location: ' . url('commercial_manager/dashboard.php'), true, 302);
exit;
