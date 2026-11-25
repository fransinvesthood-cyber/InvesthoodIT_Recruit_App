
<?php
/**
 * INVESTHOOD IT - Selection Filter Diagnostics
 *
 * READ-ONLY diagnostic script.
 * Admin access only.
 *
 * Usage:
 * admin/selection_debug.php?status=selected
 *
 * IMPORTANT:
 * Delete this file after debugging.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

header('Content-Type: text/plain; charset=utf-8');

function dbg($label, $value)
{
    echo "\n========================================\n";
    echo "=== " . $label . " ===\n";
    echo "========================================\n";

    if (is_string($value)) {
        echo $value . "\n";
    } else {
        echo print_r($value, true);
    }

    echo "\n";
}

function runDiagnostic($label, $callback)
{
    try {
        $result = $callback();
        dbg($label, $result);
        return $result;
    } catch (Throwable $e) {
        dbg($label . ' ERROR', [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);

        return null;
    }
}

echo "INVESTHOOD IT - SELECTION DIAGNOSTICS\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";

// =====================================================
// 1. GET PARAMETERS
// =====================================================

dbg('GET PARAMETERS', $_GET);

// =====================================================
// 2. NORMALISE STATUS
// =====================================================

$fStatusRaw = strtolower(trim((string)($_GET['status'] ?? '')));

$fStatus = ($fStatusRaw === 'waitlisted')
    ? 'on_hold'
    : $fStatusRaw;

dbg('NORMALISED STATUS', [
    'raw_status' => $_GET['status'] ?? null,
    'normalised_status' => $fStatus
]);

// =====================================================
// 3. APPLICATION STATUS COUNTS
// =====================================================

runDiagnostic(
    'APPLICATIONS GROUPED BY STATUS',
    function () {
        return Database::fetchAll(
            "SELECT status, COUNT(*) AS total
             FROM applications
             GROUP BY status
             ORDER BY status"
        );
    }
);

// =====================================================
// 4. SELECTED APPLICATIONS AND CANDIDATE ROLES
// =====================================================

runDiagnostic(
    'SELECTED APPLICATIONS WITH CANDIDATE DETAILS',
    function () {
        return Database::fetchAll(
            "SELECT
                a.id,
                a.application_reference,
                a.status,
                a.candidate_id,
                u.first_name,
                u.last_name,
                u.role_id,
                r.slug AS role_slug,
                r.name AS role_name
             FROM applications a
             LEFT JOIN users u
                ON u.id = a.candidate_id
             LEFT JOIN roles r
                ON r.id = u.role_id
             WHERE a.status = 'selected'
             ORDER BY a.id DESC
             LIMIT 20"
        );
    }
);

// =====================================================
// 5. ROLE TABLE
// =====================================================

runDiagnostic(
    'AVAILABLE USER ROLES',
    function () {
        return Database::fetchAll(
            "SELECT id, name, slug
             FROM roles
             ORDER BY id"
        );
    }
);

// =====================================================
// 6. CHECK HELPER TABLES
// =====================================================

$helperTables = [
    'interviews',
    'interview_feedback',
    'selection_decisions',
    'offers'
];

foreach ($helperTables as $table) {

    runDiagnostic(
        "TABLE CHECK: " . $table,
        function () use ($table) {

            // Table names are taken only from the fixed whitelist above.
            $result = Database::fetchOne(
                "SHOW TABLES LIKE '$table'"
            );

            return [
                'table' => $table,
                'exists' => $result ? 'YES' : 'NO'
            ];
        }
    );
}

// =====================================================
// 7. INSPECT SELECTION MODEL FILTERS
// =====================================================

runDiagnostic(
    'SELECTION BUILD LIST FILTERS',
    function () use ($fStatus) {

        if (!class_exists('Selection')) {
            throw new RuntimeException(
                'Selection class has not been loaded.'
            );
        }

        if (!method_exists('Selection', 'buildListFilters')) {
            throw new RuntimeException(
                'Selection::buildListFilters() does not exist.'
            );
        }

        $method = new ReflectionMethod(
            'Selection',
            'buildListFilters'
        );

        if (!$method->isStatic()) {
            throw new RuntimeException(
                'buildListFilters() is not a static method.'
            );
        }

        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            ['status' => $fStatus]
        );

        return [
            'status' => $fStatus,
            'filter_result' => $result
        ];
    }
);

// =====================================================
// 8. CHECK SELECTED APPLICATIONS WITHOUT ROLE JOIN
// =====================================================

runDiagnostic(
    'SELECTED COUNT WITHOUT ROLE JOIN',
    function () {

        return Database::fetchOne(
            "SELECT COUNT(*) AS total
             FROM applications a
             WHERE a.status = 'selected'"
        );
    }
);

// =====================================================
// 9. CHECK SELECTED APPLICATIONS WITH ROLE JOIN
// =====================================================

runDiagnostic(
    'SELECTED COUNT WITH CANDIDATE ROLE JOIN',
    function () {

        return Database::fetchOne(
            "SELECT COUNT(*) AS total
             FROM applications a
             INNER JOIN users u
                ON u.id = a.candidate_id
             INNER JOIN roles ur
                ON ur.id = u.role_id
             WHERE a.status = 'selected'
               AND ur.slug = 'candidate'"
        );
    }
);

// =====================================================
// 10. IDENTIFY SELECTED APPLICATIONS EXCLUDED BY JOIN
// =====================================================

runDiagnostic(
    'SELECTED APPLICATIONS WITHOUT VALID CANDIDATE ROLE',
    function () {

        return Database::fetchAll(
            "SELECT
                a.id,
                a.application_reference,
                a.candidate_id,
                a.status,
                u.role_id,
                r.slug AS role_slug
             FROM applications a
             LEFT JOIN users u
                ON u.id = a.candidate_id
             LEFT JOIN roles r
                ON r.id = u.role_id
             WHERE a.status = 'selected'
               AND (
                    u.id IS NULL
                    OR r.id IS NULL
                    OR r.slug <> 'candidate'
                    OR r.slug IS NULL
               )
             LIMIT 50"
        );
    }
);

// =====================================================
// 11. FULL SELECTION MODEL RESULT
// =====================================================

runDiagnostic(
    'SELECTION ADMIN LIST RESULT',
    function () use ($fStatus) {

        if (!class_exists('Selection')) {
            throw new RuntimeException(
                'Selection class has not been loaded.'
            );
        }

        return Selection::adminList(
            ['status' => $fStatus],
            1,
            20
        );
    }
);

// =====================================================
// 12. FINISH
// =====================================================

echo "\n========================================\n";
echo "DIAGNOSTICS COMPLETED\n";
echo "========================================\n";

echo "Requested status: " . $fStatus . "\n";
echo "Diagnostic mode: READ-ONLY\n";

echo "\nIMPORTANT:\n";
echo "Delete admin/selection_debug.php after debugging.\n";

