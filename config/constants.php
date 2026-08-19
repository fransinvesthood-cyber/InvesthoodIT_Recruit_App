<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Constants
 * ================================================
 * Shared constants for account statuses, user roles,
 * dashboard routing and token types.
 */

// -------------------------------------------------
// Account Statuses
// -------------------------------------------------
const STATUS_PENDING   = 'pending';    // Awaiting email verification
const STATUS_ACTIVE    = 'active';     // Fully verified & active
const STATUS_SUSPENDED = 'suspended';  // Temporarily locked by admin
const STATUS_DISABLED  = 'disabled';   // Permanently disabled

// -------------------------------------------------
// Default Role
// -------------------------------------------------
const ROLE_CANDIDATE = 'candidate';

// -------------------------------------------------
// Role => Dashboard Path Mapping
// -------------------------------------------------
const ROLE_DASHBOARD_MAP = [
    'admin'              => 'admin/dashboard.php',
    'programme_manager'  => 'programme/dashboard.php',
    'programme_officer'  => 'programme_officer/dashboard.php',
    'recruiter'          => 'recruiter/dashboard.php',
    'supervisor'         => 'supervisor/dashboard.php',
    'assessor'           => 'assessor/dashboard.php',
    'finance_officer'    => 'finance/dashboard.php',
    'information_officer'=> 'privacy/dashboard.php',
    'candidate'          => 'candidate/dashboard.php',
];

// -------------------------------------------------
// Token Types (for audit/logging clarity)
// -------------------------------------------------
const TOKEN_EMAIL_VERIFY = 'email_verify';
const TOKEN_PASSWORD_RESET = 'password_reset';
const TOKEN_REMEMBER_ME = 'remember_me';

// -------------------------------------------------
// Allowed Genders & Provinces (server-side whitelist)
// -------------------------------------------------
const ALLOWED_GENDERS = ['male', 'female', 'non-binary', 'prefer-not-to-say'];

const ALLOWED_PROVINCES = [
    'eastern-cape', 'free-state', 'gauteng', 'kwazulu-natal',
    'limpopo', 'mpumalanga', 'northern-cape', 'north-west', 'western-cape',
];

const ALLOWED_EMPLOYMENT_STATUS = [
    'employed', 'unemployed', 'student', 'recent-graduate', 'freelancer', 'other',
];

const ALLOWED_QUALIFICATIONS = [
    'grade-12', 'certificate', 'diploma', 'degree', 'honours', 'masters', 'phd',
];

// -------------------------------------------------
// Candidate Profile - Availability Status Slugs
// (must match availability_statuses table)
// -------------------------------------------------
const AVAILABILITY_AVAILABLE_NOW   = 'available_now';
const AVAILABILITY_AVAILABLE_DATE  = 'available_from_date';
const AVAILABILITY_EMPLOYED_OPEN   = 'employed_open';
const AVAILABILITY_UNAVAILABLE     = 'unavailable';
const AVAILABILITY_DO_NOT_CONTACT  = 'do_not_contact';
const AVAILABILITY_UNKNOWN_STALE   = 'unknown_stale';

// -------------------------------------------------
// Candidate Profile - Consent Purposes
// -------------------------------------------------
const CONSENT_PROGRAMME        = 'programme_administration';
const CONSENT_FUTURE_OPPORTUNITIES = 'future_opportunities';
const CONSENT_TALENT_POOL      = 'talent_pool';
const CONSENT_CLIENT_SUBMISSION = 'client_submission';

// All consent purposes (canonical list used by controllers)
const ALLOWED_CONSENT_PURPOSES = [
    CONSENT_PROGRAMME,
    CONSENT_FUTURE_OPPORTUNITIES,
    CONSENT_TALENT_POOL,
    CONSENT_CLIENT_SUBMISSION,
];

// -------------------------------------------------
// Candidate Profile - Document Types
// -------------------------------------------------
const DOC_TYPE_CV          = 'cv';
const DOC_TYPE_QUALIFICATION = 'qualification';
const DOC_TYPE_SUPPORTING  = 'supporting';

// -------------------------------------------------
// Candidate Profile - Verification Statuses
// -------------------------------------------------
const VERIFY_UNVERIFIED = 'unverified';
const VERIFY_PENDING    = 'pending';
const VERIFY_VERIFIED   = 'verified';
const VERIFY_FAILED     = 'failed';

// -------------------------------------------------
// Candidate Profile - Skill Categories
// -------------------------------------------------
const SKILL_TECHNICAL = 'technical';
const SKILL_SOFT      = 'soft';

// -------------------------------------------------
// Candidate Profile - Upload Limits
// -------------------------------------------------
const MAX_PROFILE_IMAGE_SIZE = 2 * 1024 * 1024;   // 2 MB
const MAX_DOCUMENT_SIZE      = 10 * 1024 * 1024;  // 10 MB

// Allowed profile image mime types
const ALLOWED_PROFILE_IMAGE_MIMES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

// -------------------------------------------------
// Programme & Cohort Management - Constants
// -------------------------------------------------
const PROGRAMME_TYPE_LABELS = [
    'graduate_programme' => 'Graduate Programme',
    'internship'         => 'Internship',
    'learnership'        => 'Learnership',
    'wil'                => 'Work Integrated Learning',
    'skills_development' => 'Skills Development',
    'other'              => 'Other',
];

const COHORT_DELIVERY_LABELS = [
    'on_site' => 'On-site',
    'remote'  => 'Remote',
    'hybrid'  => 'Hybrid',
];

const PROGRAMME_STATUS_LABELS = [
    'draft'     => 'Draft',
    'active'    => 'Active',
    'paused'    => 'Paused',
    'completed' => 'Completed',
    'archived'  => 'Archived',
];

const COHORT_STATUS_LABELS = [
    'draft'     => 'Draft',
    'open'      => 'Open',
    'closed'    => 'Closed',
    'active'    => 'Active',
    'completed' => 'Completed',
    'archived'  => 'Archived',
];

const WORKFLOW_STAGE_LABELS = [
    'application'          => 'Application',
    'eligibility_review'   => 'Eligibility Review',
    'screening'            => 'Screening',
    'assessment'           => 'Assessment',
    'interview'            => 'Interview',
    'selection'            => 'Selection',
    'onboarding'           => 'Onboarding',
    'active_participant'   => 'Active Participant',
];

// Alias used by cohort workspace views
const COHORT_WORKFLOW_LABELS = WORKFLOW_STAGE_LABELS;

const QUALIFICATION_LEVEL_LABELS = [
    'certificate' => 'Certificate',
    'diploma'     => 'Diploma',
    'degree'      => 'Degree',
    'honours'     => 'Honours',
    'other'       => 'Other',
];

// -------------------------------------------------
// Opportunity Management - Constants
// -------------------------------------------------
const OPPORTUNITY_TYPE_LABELS = [
    'graduate_programme' => 'Graduate Programme',
    'internship'         => 'Internship',
    'learnership'        => 'Learnership',
    'wil'                => 'Work Integrated Learning',
    'skills_development' => 'Skills Development',
    'mentorship'         => 'Mentorship',
    'other'              => 'Other',
];

const OPPORTUNITY_WORK_ARRANGEMENT_LABELS = [
    'on_site' => 'On-site',
    'remote'  => 'Remote',
    'hybrid'  => 'Hybrid',
];

const OPPORTUNITY_STATUS_LABELS = [
    'draft'        => 'Draft',
    'published'    => 'Published',
    'closing_soon' => 'Closing Soon',
    'closed'       => 'Closed',
    'archived'     => 'Archived',
];

const OPPORTUNITY_SKILL_CATEGORIES = [
    'required_technical' => 'Required Technical Skills',
    'preferred_technical' => 'Preferred Technical Skills',
    'required_soft'       => 'Required Soft Skills',
];

// Allowed document mime types
const ALLOWED_DOCUMENT_MIMES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
    'image/webp',
    'text/plain',
];

// -------------------------------------------------
// Opportunity Management - Extended Constants
// -------------------------------------------------
const OPPORTUNITY_RESPONSIBILITY_SECTIONS = [
    'key_responsibilities' => 'Key Responsibilities',
    'duties'               => 'Duties',
    'programme_activities' => 'Programme Activities',
    'learning_outcomes'    => 'Learning Outcomes',
];

const OPPORTUNITY_DOCUMENT_TYPES = [
    'CV'                      => 'CV',
    'Qualification Certificate' => 'Qualification Certificate',
    'ID'                      => 'ID',
    'Academic Transcript'     => 'Academic Transcript',
    'Cover Letter'            => 'Cover Letter',
    'Other Supporting Documents' => 'Other Supporting Documents',
];

