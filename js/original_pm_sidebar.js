(() => {
    'use strict';

    const STORAGE_KEY = 'investhood-original-pm-sidebar-collapsed';

    const getSidebar = () => document.getElementById('portalSidebar');
    const getOverlay = () => document.getElementById('portalSidebarOverlay');

    function closeMobileSidebar() {
        getSidebar()?.classList.remove('open');
        getOverlay()?.classList.remove('open');
        document.body.classList.remove('portal-sidebar-mobile-open');
        const button = document.getElementById('uxMenuButton');
        button?.setAttribute('aria-expanded', 'false');
    }

    function setDesktopCollapsed(collapsed) {
        document.body.classList.toggle('portal-sidebar-collapsed', collapsed);

        try {
            localStorage.setItem(
                STORAGE_KEY,
                collapsed ? '1' : '0'
            );
        } catch (error) {
            // Storage can be unavailable in private/restricted contexts.
        }

        const button = document.getElementById('uxMenuButton');
        button?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        button?.setAttribute(
            'aria-label',
            collapsed ? 'Show menu' : 'Hide menu'
        );
        button?.setAttribute(
            'title',
            collapsed ? 'Show menu' : 'Hide menu'
        );
    }

    function toggleSidebar() {
        const sidebar = getSidebar();

        if (!sidebar) {
            return;
        }

        if (window.innerWidth < 1024) {
            const open = !sidebar.classList.contains('open');

            sidebar.classList.toggle('open', open);
            getOverlay()?.classList.toggle('open', open);
            document.body.classList.toggle(
                'portal-sidebar-mobile-open',
                open
            );

            const button = document.getElementById('uxMenuButton');
            button?.setAttribute('aria-expanded', open ? 'true' : 'false');
            button?.setAttribute(
                'aria-label',
                open ? 'Hide menu' : 'Show menu'
            );
            button?.setAttribute(
                'title',
                open ? 'Hide menu' : 'Show menu'
            );

            return;
        }

        const collapsed =
            !document.body.classList.contains(
                'portal-sidebar-collapsed'
            );

        setDesktopCollapsed(collapsed);
    }

    function restoreDesktopState() {
        if (window.innerWidth < 1024) {
            document.body.classList.remove(
                'portal-sidebar-collapsed'
            );
            closeMobileSidebar();
            return;
        }

        try {
            const collapsed =
                localStorage.getItem(STORAGE_KEY) === '1';

            document.body.classList.toggle(
                'portal-sidebar-collapsed',
                collapsed
            );

            const button =
                document.getElementById('uxMenuButton');

            button?.setAttribute(
                'aria-expanded',
                collapsed ? 'false' : 'true'
            );
            button?.setAttribute(
                'aria-label',
                collapsed ? 'Show menu' : 'Hide menu'
            );
            button?.setAttribute(
                'title',
                collapsed ? 'Show menu' : 'Hide menu'
            );
        } catch (error) {
            setDesktopCollapsed(false);
        }
    }

    function init() {
        const menuButton =
            document.getElementById('uxMenuButton');

        if (menuButton) {
            menuButton.addEventListener(
                'click',
                function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    toggleSidebar();
                }
            );
        }

        [
            'uxSidebarToggle',
            'pmSidebarToggle',
            'portalSidebarToggle'
        ].forEach(function (id) {
            document
                .getElementById(id)
                ?.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    toggleSidebar();
                });
        });

        document
            .getElementById('portalSidebarClose')
            ?.addEventListener('click', closeMobileSidebar);

        getOverlay()?.addEventListener(
            'click',
            closeMobileSidebar
        );

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        restoreDesktopState();

        window.addEventListener(
            'resize',
            restoreDesktopState
        );
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();
})();
