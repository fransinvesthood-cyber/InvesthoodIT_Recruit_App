<?php
/**
 * Commercial Manager Dashboard
 * Restored role-specific landing page for the Commercial Manager portal.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('commercial_manager');

$user = current_user();
$flashes = render_flashes();
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$managerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($managerId < 1) {
    http_response_code(403);
    exit('Invalid Commercial Manager account.');
}

$currentPage = 'dashboard';
$pageTitle = 'Commercial Manager Dashboard';

/** Return a manager-scoped count without failing when an optional module is not installed. */
function cm_dashboard_count(mysqli $db, string $table, int $managerId): int {
    $allowed = [
        'commercial_contracts',
        'commercial_clients',
        'commercial_sales_opportunities',
        'commercial_proposals',
        'commercial_risks',
        'commercial_compliance_obligations'
    ];
    if (!in_array($table, $allowed, true) || !cm_table($db, $table)) {
        return 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM `{$table}` WHERE commercial_manager_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('i', $managerId);
    if (!$stmt->execute()) { $stmt->close(); return 0; }
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

$stats = [
    ['label' => 'Client Accounts', 'value' => cm_dashboard_count($conn, 'commercial_clients', $managerId), 'icon' => 'fa-handshake', 'url' => 'client_relationships.php', 'description' => 'Manage client relationships and account growth'],
    ['label' => 'Sales Opportunities', 'value' => cm_dashboard_count($conn, 'commercial_sales_opportunities', $managerId), 'icon' => 'fa-chart-line', 'url' => 'sales_pipeline.php', 'description' => 'Track prospects, pipeline stages and forecasts'],
    ['label' => 'Proposals & Quotations', 'value' => cm_dashboard_count($conn, 'commercial_proposals', $managerId), 'icon' => 'fa-file-invoice-dollar', 'url' => 'proposals.php', 'description' => 'Prepare and monitor commercial proposals'],
    ['label' => 'Contracts', 'value' => cm_dashboard_count($conn, 'commercial_contracts', $managerId), 'icon' => 'fa-file-contract', 'url' => 'contracts.php', 'description' => 'Monitor contract delivery and performance'],
    ['label' => 'Commercial Risks', 'value' => cm_dashboard_count($conn, 'commercial_risks', $managerId), 'icon' => 'fa-shield-halved', 'url' => 'risk_compliance.php', 'description' => 'Review commercial risks and controls'],
    ['label' => 'Compliance Obligations', 'value' => cm_dashboard_count($conn, 'commercial_compliance_obligations', $managerId), 'icon' => 'fa-clipboard-check', 'url' => 'risk_compliance.php', 'description' => 'Monitor due dates and compliance obligations'],
];

$name = trim((string)($user['full_name'] ?? $user['fullname'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
if ($name === '') $name = 'Commercial Manager';

require __DIR__ . '/_layout_start.php';
?>
<style>
.cm-dashboard-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;margin:8px 0 24px}
.cm-dashboard-head h2{margin:0 0 8px;font-size:clamp(1.45rem,2.5vw,2rem);color:var(--text)}
.cm-dashboard-head p{margin:0;color:var(--muted,#64748b)}
.cm-dashboard-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.cm-dashboard-card{display:flex;gap:15px;align-items:flex-start;padding:21px;border:1px solid var(--line);border-radius:16px;background:var(--surface);color:var(--text);text-decoration:none;min-width:0;transition:transform .18s ease,box-shadow .18s ease}
.cm-dashboard-card:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(15,23,42,.09)}
.cm-dashboard-icon{width:46px;height:46px;flex:0 0 46px;display:grid;place-items:center;border-radius:13px;background:var(--surface2);color:var(--primary);font-size:19px}
.cm-dashboard-card strong{display:block;font-size:1.65rem;line-height:1.2;margin-bottom:5px}
.cm-dashboard-card h3{font-size:1rem;margin:0 0 7px;color:var(--text)}
.cm-dashboard-card p{font-size:.86rem;line-height:1.45;margin:0;color:var(--muted,#64748b)}
.cm-dashboard-section{margin:28px 0 14px;font-size:1.1rem;color:var(--text)}
.cm-dashboard-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.cm-dashboard-link{padding:15px 17px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text);text-decoration:none;display:flex;align-items:center;gap:11px}
.cm-dashboard-link i{color:var(--primary)}
@media(max-width:1000px){.cm-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:620px){.cm-dashboard-grid,.cm-dashboard-links{grid-template-columns:1fr}.cm-dashboard-card{padding:17px}}
</style>

<section class="cm-dashboard-head">
  <div>
    <h2>Welcome back, <?= e($name) ?></h2>
    <p>Manage commercial performance, client relationships, sales opportunities and compliance from one workspace.</p>
  </div>
</section>

<div class="cm-dashboard-grid">
<?php foreach ($stats as $stat): ?>
  <a class="cm-dashboard-card" href="<?= e(url('commercial_manager/' . $stat['url'])) ?>">
    <span class="cm-dashboard-icon"><i class="fas <?= e($stat['icon']) ?>"></i></span>
    <span><strong><?= number_format((int)$stat['value']) ?></strong><h3><?= e($stat['label']) ?></h3><p><?= e($stat['description']) ?></p></span>
  </a>
<?php endforeach; ?>
</div>

<h3 class="cm-dashboard-section">Commercial workspace</h3>
<div class="cm-dashboard-links">
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/saved_searches.php')) ?>"><i class="fas fa-magnifying-glass"></i> Saved Talent Searches</a>
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/capability_packs.php')) ?>"><i class="fas fa-file-shield"></i> Capability Packs</a>
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/privacy_rules.php')) ?>"><i class="fas fa-user-shield"></i> Candidate Privacy Rules</a>
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/reports.php')) ?>"><i class="fas fa-chart-column"></i> Commercial Reports</a>
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/activity_log.php')) ?>"><i class="fas fa-clock-rotate-left"></i> Activity Log</a>
  <a class="cm-dashboard-link" href="<?= e(url('commercial_manager/settings.php')) ?>"><i class="fas fa-gear"></i> Portal Settings</a>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
