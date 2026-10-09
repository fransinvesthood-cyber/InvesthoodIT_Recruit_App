<?php
/**
 * Candidate Offers list — controller bootstrap only.
 * Markup lives in offers_body.php (keeps file edits small).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');

$candidateId = (int) current_user_id();
$user        = current_user();

$filters = CandidateOffersController::normaliseFilters($_GET);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$result  = CandidateOffersController::listOffers($candidateId, $filters, $page);
$summary = CandidateOffersController::dashboard($candidateId);

$flashes = render_flashes();
require __DIR__ . '/offers_body.php';
