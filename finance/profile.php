<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
require_once __DIR__ . '/_helpers.php';
$currentPage = 'profile';
$pageTitle = 'My Profile';

$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$fullName = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($firstName . ' ' . $lastName)));
if ($fullName === '') $fullName = 'Finance Officer';
$email = (string)($user['email'] ?? $user['email_address'] ?? '');
$phone = (string)($user['phone'] ?? $user['phone_number'] ?? '');
$role = (string)($user['role'] ?? 'finance_officer');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_layout_start.php';
?>
<div class="finance-page-polished finance-profile-page">
    <div class="fo-page-header">
        <div><span>Finance Officer Workspace</span><h2>My Profile</h2><p>View the account details associated with your Finance Officer access.</p></div>
    </div>
    <section class="fo-card">
        <div class="fo-card-header"><h3><i class="fas fa-user-circle"></i> Account information</h3></div>
        <div class="fp-profile-grid">
            <div class="fp-avatar"><i class="fas fa-user"></i></div>
            <div class="fp-details">
                <div><span>Full name</span><strong><?= $escape($fullName) ?></strong></div>
                <div><span>Email address</span><strong><?= $email !== '' ? $escape($email) : 'Not provided' ?></strong></div>
                <div><span>Contact number</span><strong><?= $phone !== '' ? $escape($phone) : 'Not provided' ?></strong></div>
                <div><span>Role</span><strong><?= $escape(ucwords(str_replace('_', ' ', $role))) ?></strong></div>
            </div>
        </div>
        <p class="fo-muted">To change account information, please contact your system administrator.</p>
    </section>
</div>
<style>
.finance-profile-page{font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.5}
.finance-profile-page .fo-page-header{margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border-color,#e5e7eb)}
.finance-profile-page .fo-page-header h2{font-size:1.65rem;margin:5px 0;font-weight:700}
.finance-profile-page .fo-page-header p,.finance-profile-page .fo-muted{font-size:13px;color:var(--muted,#64748b)}
.finance-profile-page .fo-card{padding:20px;border-radius:12px}
.fp-profile-grid{display:flex;align-items:flex-start;gap:22px;flex-wrap:wrap}
.fp-avatar{width:68px;height:68px;border-radius:50%;display:grid;place-items:center;background:rgba(37,99,235,.12);color:#2563eb;font-size:25px}
.fp-details{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:18px;flex:1;min-width:min(100%,300px)}
.fp-details div{display:grid;gap:5px}.fp-details span{font-size:12px;color:var(--muted,#64748b)}.fp-details strong{font-size:14px;overflow-wrap:anywhere}
@media(max-width:600px){.fp-details{grid-template-columns:1fr}}
</style>
<?php require __DIR__ . '/_layout_end.php'; ?>
