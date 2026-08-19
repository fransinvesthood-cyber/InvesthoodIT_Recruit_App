<?php
/**
 * ================================================
 * INVESTHOOD IT - Database Seeder
 * ================================================
 * Seeds roles (if missing) and creates demo user
 * accounts for every role. Run from the CLI or
 * browser once after importing schema.sql:
 *
 *   php database/seed.php
 *
 * Demo password for all accounts:  Investhood@2025
 */

require_once __DIR__ . '/../includes/bootstrap.php';

echo "============================================\n";
echo " INVESTHOOD IT - Database Seeder\n";
echo "============================================\n\n";

// ---- 1. Ensure roles exist ----
$roles = [
    'admin'              => 'Administrator',
    'programme_manager'  => 'Programme Manager',
    'programme_officer'  => 'Programme Officer',
    'recruiter'          => 'Recruiter',
    'supervisor'         => 'Supervisor',
    'assessor'           => 'Assessor',
    'finance_officer'    => 'Finance Officer',
    'information_officer'=> 'Information Officer',
    'candidate'          => 'Candidate',
];

$roleIds = [];
foreach ($roles as $slug => $name) {
    $existing = Role::findBySlug($slug);
    if ($existing) {
        $roleIds[$slug] = (int) $existing['id'];
        echo "  [OK] Role exists: {$name} ({$slug})\n";
        continue;
    }

    Database::execute(
        "INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)",
        'sss',
        [$name, $slug, ucfirst($slug) . ' role']
    );
    $roleIds[$slug] = Database::lastInsertId();
    echo "  [NEW] Role created: {$name} ({$slug})\n";
}

// ---- 2. Demo users ----
$demoPassword = 'Investhood@2025';
$passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

$demoUsers = [
    'admin' => [
        'first_name' => 'Platform', 'last_name' => 'Administrator',
        'username' => 'admin', 'email' => 'admin@investhoodit.co.za',
        'phone' => '+27 11 234 5678', 'province' => 'gauteng',
        'qualification_level' => 'masters', 'professional_title' => 'Platform Administrator',
    ],
    'programme_manager' => [
        'first_name' => 'Programme', 'last_name' => 'Manager',
        'username' => 'programme.manager', 'email' => 'programme.manager@investhoodit.co.za',
        'phone' => '+27 11 234 5679', 'province' => 'gauteng',
        'qualification_level' => 'masters', 'professional_title' => 'Programme Manager',
    ],
    'programme_officer' => [
        'first_name' => 'Programme', 'last_name' => 'Officer',
        'username' => 'programme.officer', 'email' => 'programme.officer@investhoodit.co.za',
        'phone' => '+27 11 234 5680', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Programme Officer',
    ],
    'recruiter' => [
        'first_name' => 'Recruitment', 'last_name' => 'Specialist',
        'username' => 'recruiter', 'email' => 'recruiter@investhoodit.co.za',
        'phone' => '+27 11 234 5681', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Recruiter',
    ],
    'supervisor' => [
        'first_name' => 'Workplace', 'last_name' => 'Supervisor',
        'username' => 'supervisor', 'email' => 'supervisor@investhoodit.co.za',
        'phone' => '+27 11 234 5682', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Workplace Supervisor',
    ],
    'assessor' => [
        'first_name' => 'Skills', 'last_name' => 'Assessor',
        'username' => 'assessor', 'email' => 'assessor@investhoodit.co.za',
        'phone' => '+27 11 234 5683', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Skills Assessor',
    ],
    'finance_officer' => [
        'first_name' => 'Finance', 'last_name' => 'Officer',
        'username' => 'finance.officer', 'email' => 'finance.officer@investhoodit.co.za',
        'phone' => '+27 11 234 5684', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Finance Officer',
    ],
    'information_officer' => [
        'first_name' => 'Information', 'last_name' => 'Officer',
        'username' => 'info.officer', 'email' => 'info.officer@investhoodit.co.za',
        'phone' => '+27 11 234 5685', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Information Officer',
    ],
    'candidate' => [
        'first_name' => 'John', 'last_name' => 'Doe',
        'username' => 'johndoe', 'email' => 'candidate@investhoodit.co.za',
        'phone' => '+27 12 345 6789', 'province' => 'gauteng',
        'qualification_level' => 'degree', 'professional_title' => 'Software Developer',
    ],
];

echo "\n--- Demo Users ---\n";
foreach ($demoUsers as $slug => $info) {
    $roleId = $roleIds[$slug];

    // Skip if already exists
    if (User::findByEmail($info['email'])) {
        echo "  [SKIP] {$info['email']} already exists.\n";
        continue;
    }

    Database::execute(
        "INSERT INTO users
         (role_id, first_name, last_name, username, email, phone,
          date_of_birth, gender, province, employment_status,
          qualification_level, professional_title, password_hash,
          status, email_verified_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW(), NOW())",
'issssssssssss',
        [
            $roleId,
            $info['first_name'],
            $info['last_name'],
            $info['username'],
            $info['email'],
            $info['phone'],
            '1995-06-15',
            'prefer-not-to-say',
            $info['province'],
            'employed',
            $info['qualification_level'],
            $info['professional_title'],
            $passwordHash,
        ]
    );

    $userId = Database::lastInsertId();
    echo "  [NEW] {$info['email']} (role: {$slug}, id: {$userId})\n";
}

echo "\n============================================\n";
echo " Seeding complete.\n";
echo " Demo password for all accounts: {$demoPassword}\n";
echo "============================================\n";

