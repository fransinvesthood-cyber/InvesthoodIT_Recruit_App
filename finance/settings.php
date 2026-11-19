<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$conn = Database::getConnection();
require_once __DIR__ . '/_helpers.php';
$currentPage = 'settings';
$pageTitle = 'Settings';
$firstName = trim((string)($user['first_name'] ?? ''));
$lastName = trim((string)($user['last_name'] ?? ''));
$displayName = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($firstName . ' ' . $lastName)));
if ($displayName === '') $displayName = 'Finance Officer';
$email = trim((string)($user['email'] ?? $user['email_address'] ?? ''));
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_layout_start.php';
?>
<div class="finance-page-polished finance-settings-page">
    <div class="fo-page-header fs-page-heading">
        <div>
            <span>Finance Officer Workspace</span>
            <h2>Settings</h2>
            <p>Personalise the appearance and behaviour of your Finance Officer workspace.</p>
        </div>
        <a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/profile.php')) ?>"><i class="fas fa-user-circle"></i> My Profile</a>
    </div>

    <div class="fs-settings-layout">
        <div class="fs-settings-main">
            <section class="fo-card fs-setting-card" aria-labelledby="fs-appearance-title">
                <div class="fs-card-heading">
                    <div class="fs-icon"><i class="fas fa-palette"></i></div>
                    <div><h3 id="fs-appearance-title">Appearance</h3><p>Choose how the Finance workspace looks on this device.</p></div>
                </div>
                <div class="fs-field-block">
                    <div class="fs-field-copy"><strong>Colour theme</strong><span>Switch between light and dark mode.</span></div>
                    <div class="fs-theme-options" role="group" aria-label="Colour theme">
                        <button type="button" class="fs-theme-choice" data-fs-theme="light" aria-pressed="false"><i class="fas fa-sun"></i><span>Light</span></button>
                        <button type="button" class="fs-theme-choice" data-fs-theme="dark" aria-pressed="false"><i class="fas fa-moon"></i><span>Dark</span></button>
                    </div>
                </div>
                <div class="fs-field-block fs-font-block">
                    <div class="fs-field-copy"><strong>Interface text size</strong><span>Adjust the text size for improved readability.</span></div>
                    <div class="fs-font-control"><input id="fsFontSize" type="range" min="13" max="17" step="1" value="14"><output id="fsFontOutput" for="fsFontSize">14 px</output></div>
                </div>
                <div class="fs-field-block fs-last-field">
                    <div class="fs-field-copy"><strong>Compact layout</strong><span>Reduce spacing in tables and content panels to show more information.</span></div>
                    <label class="fs-switch"><input type="checkbox" id="fsCompact"><span class="fs-switch-track"></span><span class="fs-switch-label">Enable</span></label>
                </div>
            </section>

            <section class="fo-card fs-setting-card" aria-labelledby="fs-accessibility-title">
                <div class="fs-card-heading">
                    <div class="fs-icon fs-icon--access"><i class="fas fa-universal-access"></i></div>
                    <div><h3 id="fs-accessibility-title">Accessibility</h3><p>Make the interface more comfortable to use.</p></div>
                </div>
                <div class="fs-field-block fs-last-field">
                    <div class="fs-field-copy"><strong>Reduce interface animations</strong><span>Minimise transitions and movement across the Finance workspace.</span></div>
                    <label class="fs-switch"><input type="checkbox" id="fsReduceMotion"><span class="fs-switch-track"></span><span class="fs-switch-label">Enable</span></label>
                </div>
            </section>

            <section class="fo-card fs-setting-card" aria-labelledby="fs-account-title">
                <div class="fs-card-heading">
                    <div class="fs-icon fs-icon--account"><i class="fas fa-user-shield"></i></div>
                    <div><h3 id="fs-account-title">Account</h3><p>Review the account currently signed in to this workspace.</p></div>
                </div>
                <div class="fs-account-row">
                    <div class="fs-account-avatar"><i class="fas fa-user"></i></div>
                    <div class="fs-account-info"><strong><?= $escape($displayName) ?></strong><span><?= $email !== '' ? $escape($email) : 'Email address not available' ?></span><small>Finance Officer</small></div>
                    <a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/profile.php')) ?>">View profile <i class="fas fa-arrow-right"></i></a>
                </div>
                <p class="fs-account-note"><i class="fas fa-info-circle"></i> Account details are managed by your system administrator.</p>
            </section>
        </div>

        <aside class="fo-card fs-preferences-aside">
            <div class="fs-aside-icon"><i class="fas fa-sliders"></i></div>
            <h3>Your preferences</h3>
            <p>These display preferences are saved in this browser and apply to this device when you return.</p>
            <div class="fs-preference-status"><i class="fas fa-check-circle"></i><span id="fsSaveStatus" role="status" aria-live="polite">Preferences are up to date</span></div>
            <button type="button" class="fo-btn fo-btn--secondary fs-reset-btn" id="fsReset"><i class="fas fa-rotate-left"></i> Reset preferences</button>
            <small>Resetting restores the default light theme, standard text size and normal spacing.</small>
        </aside>
    </div>
</div>

<style>
.finance-settings-page{--fs-line:var(--border-color,#e5e7eb);--fs-muted:var(--muted,#64748b);font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.55;color:var(--text-color,inherit)}
.finance-settings-page .fs-page-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px;padding-bottom:17px;border-bottom:1px solid var(--fs-line)}
.finance-settings-page .fo-page-header h2{font-size:1.7rem;margin:5px 0;font-weight:750;letter-spacing:-.035em}
.finance-settings-page .fo-page-header p{font-size:13px;color:var(--fs-muted);margin:0}
.fs-settings-layout{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:18px;align-items:start}
.fs-settings-main{display:grid;gap:17px;min-width:0}
.finance-settings-page .fs-setting-card,.fs-preferences-aside{padding:21px;border:1px solid var(--fs-line);border-radius:14px;background:var(--card-bg,var(--surface-color,#fff));box-shadow:0 4px 16px rgba(15,23,42,.035)}
.fs-card-heading{display:flex;align-items:center;gap:12px;padding-bottom:17px;border-bottom:1px solid var(--fs-line)}
.fs-icon{width:39px;height:39px;flex:0 0 39px;display:grid;place-items:center;border-radius:11px;background:rgba(37,99,235,.11);color:#2563eb;font-size:16px}
.fs-icon--access{background:rgba(14,165,233,.11);color:#0284c7}.fs-icon--account{background:rgba(16,185,129,.11);color:#059669}
.fs-card-heading h3,.fs-preferences-aside h3{font-size:15px;font-weight:700;margin:0 0 3px}.fs-card-heading p{font-size:12px;color:var(--fs-muted);margin:0}
.fs-field-block{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:17px 0;border-bottom:1px solid var(--fs-line)}.fs-last-field{border-bottom:0;padding-bottom:1px}
.fs-field-copy{display:grid;gap:3px;min-width:0}.fs-field-copy strong{font-size:13px;font-weight:650}.fs-field-copy span{font-size:12px;color:var(--fs-muted)}
.fs-theme-options{display:flex;gap:8px;flex:0 0 auto}.fs-theme-choice{min-width:88px;display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border:1px solid var(--fs-line);border-radius:9px;background:transparent;color:inherit;font:600 12px Inter,system-ui,sans-serif;cursor:pointer;transition:border-color .18s,background .18s}.fs-theme-choice:hover{border-color:#64748b}.fs-theme-choice[aria-pressed="true"]{border-color:#2563eb;background:rgba(37,99,235,.09);color:#2563eb}
.fs-font-control{display:flex;align-items:center;gap:12px;width:min(260px,45%)}.fs-font-control input{width:100%;accent-color:#2563eb}.fs-font-control output{min-width:43px;font-size:12px;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}
.fs-switch{display:inline-flex;align-items:center;gap:9px;cursor:pointer;flex:0 0 auto}.fs-switch input{position:absolute;opacity:0;width:1px;height:1px}.fs-switch-track{position:relative;width:38px;height:22px;border-radius:20px;background:#94a3b8;transition:background .18s}.fs-switch-track:after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:transform .18s}.fs-switch input:checked+.fs-switch-track{background:#2563eb}.fs-switch input:checked+.fs-switch-track:after{transform:translateX(16px)}.fs-switch input:focus-visible+.fs-switch-track{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}.fs-switch-label{font-size:11px;color:var(--fs-muted)}
.fs-account-row{display:flex;align-items:center;gap:13px;padding-top:17px}.fs-account-avatar{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;background:rgba(37,99,235,.11);color:#2563eb}.fs-account-info{display:grid;gap:2px;min-width:0;flex:1}.fs-account-info strong{font-size:13px;overflow-wrap:anywhere}.fs-account-info span,.fs-account-info small{font-size:11px;color:var(--fs-muted);overflow-wrap:anywhere}.fs-account-row .fo-btn{white-space:nowrap;font-size:11px;padding:8px 10px}.fs-account-note{font-size:11px;color:var(--fs-muted);margin:15px 0 0}
.fs-preferences-aside{position:sticky;top:18px}.fs-aside-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:rgba(99,102,241,.11);color:#6366f1;font-size:17px;margin-bottom:13px}.fs-preferences-aside h3{font-size:15px}.fs-preferences-aside>p{font-size:12px;color:var(--fs-muted);margin:7px 0 16px}.fs-preference-status{display:flex;align-items:center;gap:8px;padding:10px 11px;border-radius:9px;background:rgba(16,185,129,.09);color:#059669;font-size:11px;font-weight:600}.fs-reset-btn{width:100%;justify-content:center;margin-top:14px}.fs-preferences-aside>small{display:block;color:var(--fs-muted);font-size:10px;line-height:1.5;margin-top:9px}
html.fs-compact .finance-settings-page .fs-setting-card{padding:16px}html.fs-compact .finance-settings-page .fs-field-block{padding-top:12px;padding-bottom:12px}

/* Explicit dark-theme styling: follows the same data-theme attribute used by the portal navbar. */
html[data-theme="dark"] .finance-settings-page{
    --fs-line:rgba(148,163,184,.22);
    --fs-muted:#a8b4c5;
    color:#e5eaf2;
}
html[data-theme="dark"] .finance-settings-page .fs-setting-card,
html[data-theme="dark"] .finance-settings-page .fs-preferences-aside{
    background:#172235;
    border-color:#334155;
    color:#e5eaf2;
    box-shadow:0 8px 24px rgba(0,0,0,.18);
}
html[data-theme="dark"] .finance-settings-page .fs-card-heading h3,
html[data-theme="dark"] .finance-settings-page .fs-preferences-aside h3,
html[data-theme="dark"] .finance-settings-page .fs-field-copy strong,
html[data-theme="dark"] .finance-settings-page .fs-account-info strong{
    color:#f1f5f9;
}
html[data-theme="dark"] .finance-settings-page .fs-card-heading p,
html[data-theme="dark"] .finance-settings-page .fs-field-copy span,
html[data-theme="dark"] .finance-settings-page .fs-account-info span,
html[data-theme="dark"] .finance-settings-page .fs-account-info small,
html[data-theme="dark"] .finance-settings-page .fs-account-note,
html[data-theme="dark"] .finance-settings-page .fs-preferences-aside>p,
html[data-theme="dark"] .finance-settings-page .fs-preferences-aside>small,
html[data-theme="dark"] .finance-settings-page .fs-switch-label{
    color:#a8b4c5;
}
html[data-theme="dark"] .finance-settings-page .fs-theme-choice{
    color:#e2e8f0;
    border-color:#475569;
    background:#1e293b;
}
html[data-theme="dark"] .finance-settings-page .fs-theme-choice[aria-pressed="true"]{
    color:#93c5fd;
    border-color:#60a5fa;
    background:rgba(37,99,235,.2);
}
html[data-theme="dark"] .finance-settings-page .fs-font-control output{color:#e2e8f0}
html[data-theme="dark"] .finance-settings-page .fs-account-avatar{background:rgba(96,165,250,.16);color:#93c5fd}
html[data-theme="dark"] .finance-settings-page .fs-preference-status{background:rgba(16,185,129,.14);color:#6ee7b7}
html[data-theme="dark"] .finance-settings-page .fo-btn--secondary{
    background:#243449;
    border-color:#475569;
    color:#e2e8f0;
}
html[data-theme="dark"] .finance-settings-page .fo-btn--secondary:hover{background:#30445e;border-color:#64748b}
html[data-theme="dark"] .finance-settings-page .fs-switch-track{background:#64748b}
html[data-theme="dark"] .finance-settings-page .fs-switch input:checked+.fs-switch-track{background:#3b82f6}
html[data-theme="dark"] .finance-settings-page input[type="range"]{accent-color:#60a5fa}
html.fs-reduce-motion *,html.fs-reduce-motion *:before,html.fs-reduce-motion *:after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
@media(max-width:900px){.fs-settings-layout{grid-template-columns:1fr}.fs-preferences-aside{position:static}.fs-preferences-aside{order:-1}}
@media(max-width:600px){.finance-settings-page .fs-page-heading{align-items:flex-start;flex-direction:column}.finance-settings-page .fs-setting-card,.fs-preferences-aside{padding:16px}.fs-field-block{align-items:flex-start;flex-direction:column;gap:12px}.fs-theme-options{width:100%}.fs-theme-choice{flex:1}.fs-font-control{width:100%}.fs-account-row{flex-wrap:wrap}.fs-account-row .fo-btn{margin-left:55px}.fs-account-info{flex-basis:calc(100% - 60px)}}
</style>
<script>
(function(){
    const key='investhood-finance-preferences';
    const themeKey='investhood-unified-theme';
    const defaults={theme:(localStorage.getItem(themeKey)||localStorage.getItem('investhood-fo-theme')||document.documentElement.getAttribute('data-theme')||'light')==='dark'?'dark':'light',fontSize:14,compact:false,reduceMotion:false};
    const root=document.documentElement;
    const themeButtons=[...document.querySelectorAll('[data-fs-theme]')];
    const font=document.getElementById('fsFontSize');
    const fontOutput=document.getElementById('fsFontOutput');
    const compact=document.getElementById('fsCompact');
    const motion=document.getElementById('fsReduceMotion');
    const status=document.getElementById('fsSaveStatus');
    let prefs={...defaults};
    try{prefs={...defaults,...JSON.parse(localStorage.getItem(key)||'{}')};}catch(e){}
    prefs.theme=defaults.theme;
    prefs.fontSize=Math.min(17,Math.max(13,Number(prefs.fontSize)||14));

    function apply(){
        root.setAttribute('data-theme',prefs.theme);root.setAttribute('data-ux-theme',prefs.theme);document.body.classList.toggle('dark',prefs.theme==='dark');document.body.classList.toggle('dark-mode',prefs.theme==='dark');
        root.style.setProperty('--finance-ui-font-size',prefs.fontSize+'px');
        root.classList.toggle('fs-compact',!!prefs.compact);
        root.classList.toggle('fs-reduce-motion',!!prefs.reduceMotion);
        try{localStorage.setItem(themeKey,prefs.theme);localStorage.setItem('investhood-fo-theme',prefs.theme);localStorage.setItem(key,JSON.stringify(prefs));}catch(e){}
        themeButtons.forEach(btn=>btn.setAttribute('aria-pressed',btn.dataset.fsTheme===prefs.theme?'true':'false'));
        font.value=String(prefs.fontSize);fontOutput.value=prefs.fontSize+' px';fontOutput.textContent=prefs.fontSize+' px';
        compact.checked=!!prefs.compact;motion.checked=!!prefs.reduceMotion;
        if(status)status.textContent='Preferences saved in this browser';
    }
    themeButtons.forEach(btn=>btn.addEventListener('click',()=>{prefs.theme=btn.dataset.fsTheme;apply();}));
    font.addEventListener('input',()=>{prefs.fontSize=Number(font.value);apply();});
    compact.addEventListener('change',()=>{prefs.compact=compact.checked;apply();});
    motion.addEventListener('change',()=>{prefs.reduceMotion=motion.checked;apply();});
    document.getElementById('fsReset').addEventListener('click',()=>{prefs={...defaults};apply();});
    apply();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
