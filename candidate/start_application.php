<?php
/**
 * ================================================
 * INVESTHOOD IT - Start Application
 * ================================================
 * Handles form submission from opportunity detail page
 * to start a new application or re-apply.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request.', 'Please try again.');
    redirect('candidate/opportunities.php');
}

// CSRF protection
if (!validate_csrf()) {
    set_flash('error', 'Invalid security token.', 'Please refresh the page and try again.');
    redirect('candidate/opportunities.php');
}

$candidateId = (int) current_user_id();
$opportunityId = (int) ($_POST['opportunity_id'] ?? 0);

if ($opportunityId <= 0) {
    set_flash('error', 'Invalid opportunity.', 'The requested opportunity could not be found.');
    redirect('candidate/opportunities.php');
}

// Start or re-apply for the opportunity
$result = CandidateApplicationsController::startApplication($candidateId, $opportunityId);

if ($result['success']) {
    // Redirect to the application start page
    redirect($result['redirect']);
} else {
    // Set flash message and redirect
    set_flash('error', 'Unable to start application.', $result['message']);
    if (!empty($result['redirect'])) {
        redirect($result['redirect']);
    } else {
        redirect('candidate/opportunity_detail.php?id=' . $opportunityId);
    }
}
