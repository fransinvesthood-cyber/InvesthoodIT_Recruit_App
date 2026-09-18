<?php
require_once __DIR__ . '/includes/bootstrap.php';

echo "Testing database queries...\n\n";

// Test 1: Featured programmes
echo "Test 1: Featured Programmes Query\n";
try {
    $result = Database::fetchAll(
        "SELECT p.id, p.name, p.type, p.description, p.duration, p.start_date, p.end_date,
                COUNT(DISTINCT c.id) AS cohort_count,
                COUNT(DISTINCT o.id) AS opportunity_count
         FROM programmes p
         LEFT JOIN cohorts c ON c.programme_id = p.id
         LEFT JOIN opportunities o ON o.programme_id = p.id AND o.status = 'published'
         WHERE p.status = 'active'
         GROUP BY p.id
         ORDER BY p.created_at DESC
         LIMIT 6"
    );
    echo "✓ Success: " . count($result) . " programmes found\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Open opportunities
echo "\nTest 2: Open Opportunities Query\n";
try {
    $today = date('Y-m-d');
    echo "Today's date: $today\n";
    $result = Database::fetchAll(
        "SELECT o.id, o.title, o.type, o.organisation, o.short_description, 
                o.province, o.city, o.work_arrangement, o.application_close_date,
                o.available_positions, o.applications_count,
                p.id AS programme_id, p.name AS programme_name, p.type AS programme_type,
                c.id AS cohort_id, c.name AS cohort_name
         FROM opportunities o
         INNER JOIN programmes p ON p.id = o.programme_id
         LEFT JOIN cohorts c ON c.id = o.cohort_id
         WHERE o.status = 'published'
           AND p.status = 'active'
           AND (o.application_open_date IS NULL OR o.application_open_date <= ?)
           AND (o.application_close_date IS NULL OR o.application_close_date >= ?)
         ORDER BY o.created_at DESC
         LIMIT 6",
        'ss',
        [$today, $today]
    );
    echo "✓ Success: " . count($result) . " opportunities found\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Active opportunities count
echo "\nTest 3: Active Opportunities Count\n";
try {
    $result = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM opportunities 
         WHERE status = 'published' 
         AND programme_id IN (SELECT id FROM programmes WHERE status = 'active')"
    );
    echo "✓ Success: " . $result['cnt'] . " active opportunities\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Active programmes count
echo "\nTest 4: Active Programmes Count\n";
try {
    $result = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM programmes WHERE status = 'active'"
    );
    echo "✓ Success: " . $result['cnt'] . " active programmes\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Registered candidates
echo "\nTest 5: Registered Candidates\n";
try {
    $result = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM users WHERE role = 'candidate'"
    );
    echo "✓ Success: " . $result['cnt'] . " registered candidates\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 6: Submitted applications
echo "\nTest 6: Submitted Applications Count\n";
try {
    $result = Database::fetchOne(
        "SELECT COUNT(DISTINCT a.candidate_id) AS cnt 
         FROM applications a
         WHERE a.status = 'submitted'"
    );
    echo "✓ Success: " . $result['cnt'] . " placed candidates\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nAll tests completed.\n";
?>
