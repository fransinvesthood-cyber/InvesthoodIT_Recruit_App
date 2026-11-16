</div></main></div><script src="<?= url('js/finance_officer.js') ?>"></script><script src="<?= url('js/unified_portal.js') ?>?v=1"></script><script src="<?=url('js/original_pm_sidebar.js')?>"></script>
<script src="<?= url('js/dashboard_card_modals.js') ?>?v=20260919"></script>
<script>
(function () {
    function initFinanceNavbarDropdowns() {
        const profileButton = document.getElementById('uxProfileButton');
        const profileDropdown = document.getElementById('uxProfileDropdown');
        const notificationButton = document.getElementById('uxNotificationButton');
        const notificationDropdown = document.getElementById('uxNotificationDropdown');

        if (!profileButton || !profileDropdown) return;

        function setDropdown(button, dropdown, open) {
            if (!button || !dropdown) return;
            dropdown.hidden = !open;
            dropdown.classList.toggle('is-open', open);
            dropdown.style.display = open ? 'block' : 'none';
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeAll() {
            setDropdown(profileButton, profileDropdown, false);
            setDropdown(notificationButton, notificationDropdown, false);
        }

        profileButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const shouldOpen = profileDropdown.hidden || !profileDropdown.classList.contains('is-open');
            closeAll();
            setDropdown(profileButton, profileDropdown, shouldOpen);
        });

        if (notificationButton && notificationDropdown) {
            notificationButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                const shouldOpen = notificationDropdown.hidden || !notificationDropdown.classList.contains('is-open');
                closeAll();
                setDropdown(notificationButton, notificationDropdown, shouldOpen);
            });
        }

        document.addEventListener('click', function (event) {
            if (!profileDropdown.contains(event.target) && !profileButton.contains(event.target) &&
                (!notificationDropdown || (!notificationDropdown.contains(event.target) && !notificationButton.contains(event.target)))) {
                closeAll();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeAll();
        });

        // Ensure a clean initial state even if cached markup/styles were applied.
        closeAll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFinanceNavbarDropdowns, { once: true });
    } else {
        initFinanceNavbarDropdowns();
    }
})();
</script>

<script src="<?= e(url('finance/assets/finance-ui.js')) ?>?v=20261105-5"></script></body></html>