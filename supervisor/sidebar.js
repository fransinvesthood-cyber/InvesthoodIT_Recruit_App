(function () {
    'use strict';

    // Capture the click at document level so other portal scripts cannot
    // accidentally swallow the menu button's click event.
    if (window.__supervisorSidebarToggleBound) return;
    window.__supervisorSidebarToggleBound = true;

    const STORAGE_KEY = 'supervisor-sidebar-collapsed';
    const MOBILE_QUERY = '(max-width: 900px)';

    function getElements() {
        return {
            app: document.querySelector('.ux-app'),
            sidebar: document.getElementById('uxSidebar'),
            button: document.getElementById('uxMenuButton'),
            icon: document.getElementById('uxMenuIcon'),
            overlay: document.getElementById('portalSidebarOverlay'),
            closeButton: document.getElementById('portalSidebarClose')
        };
    }

    function isMobile() {
        return window.matchMedia(MOBILE_QUERY).matches;
    }

    function updateButton(button, icon, state) {
        if (state === 'mobile-open') {
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-label', 'Close navigation menu');
            button.title = 'Close navigation menu';
            if (icon) icon.className = 'fas fa-xmark';
        } else if (state === 'mobile-closed') {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open navigation menu');
            button.title = 'Open navigation menu';
            if (icon) icon.className = 'fas fa-bars';
        } else if (state === 'collapsed') {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Show sidebar');
            button.title = 'Show sidebar';
            if (icon) icon.className = 'fas fa-angles-right';
        } else {
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-label', 'Hide sidebar');
            button.title = 'Hide sidebar';
            if (icon) icon.className = 'fas fa-angles-left';
        }
    }

    function closeMobileDrawer() {
        const el = getElements();
        if (!el.app || !el.button) return;
        el.app.classList.remove('sv-sidebar-open');
        document.body.classList.remove('sv-sidebar-lock');
        if (isMobile()) updateButton(el.button, el.icon, 'mobile-closed');
    }

    function toggleMenu() {
        const el = getElements();
        if (!el.app || !el.sidebar || !el.button) return;

        if (isMobile()) {
            const opening = !el.app.classList.contains('sv-sidebar-open');
            el.app.classList.toggle('sv-sidebar-open', opening);
            document.body.classList.toggle('sv-sidebar-lock', opening);
            updateButton(el.button, el.icon, opening ? 'mobile-open' : 'mobile-closed');
            return;
        }

        const collapsed = !el.app.classList.contains('sv-sidebar-collapsed');
        el.app.classList.toggle('sv-sidebar-collapsed', collapsed);
        try {
            window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (error) {
            // The sidebar still works when browser storage is unavailable.
        }
        updateButton(el.button, el.icon, collapsed ? 'collapsed' : 'expanded');
    }

    function initialise() {
        const el = getElements();
        if (!el.app || !el.sidebar || !el.button) return;

        let collapsed = false;
        try {
            collapsed = !isMobile() && window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {}
        el.app.classList.toggle('sv-sidebar-collapsed', collapsed);
        updateButton(
            el.button,
            el.icon,
            isMobile() ? 'mobile-closed' : (collapsed ? 'collapsed' : 'expanded')
        );

        if (el.closeButton) {
            el.closeButton.addEventListener('click', closeMobileDrawer);
        }
        if (el.overlay) {
            el.overlay.addEventListener('click', closeMobileDrawer);
        }
        el.sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) closeMobileDrawer();
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMobileDrawer();
        });

        const media = window.matchMedia(MOBILE_QUERY);
        const onViewportChange = function () {
            closeMobileDrawer();
            let shouldCollapse = false;
            try {
                shouldCollapse = !isMobile() && window.localStorage.getItem(STORAGE_KEY) === '1';
            } catch (error) {}
            el.app.classList.toggle('sv-sidebar-collapsed', shouldCollapse);
            updateButton(
                el.button,
                el.icon,
                isMobile() ? 'mobile-closed' : (shouldCollapse ? 'collapsed' : 'expanded')
            );
        };
        if (media.addEventListener) media.addEventListener('change', onViewportChange);
        else if (media.addListener) media.addListener(onViewportChange);
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest ? event.target.closest('#uxMenuButton') : null;
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        toggleMenu();
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
