(function () {
    'use strict';

    document.querySelectorAll('[data-sultana-mobile-menu]').forEach(function (root) {
        var toggle = root.querySelector('[data-sultana-mobile-menu-toggle]');
        var panel = root.querySelector('[data-sultana-mobile-menu-panel]');
        var overlay = document.querySelector('[data-sultana-mobile-menu-overlay]');
        var closeTimer = 0;
        var lockedScrollY = 0;
        var isMenuOpen = false;
        var isClosing = false;
        var isScrollLocked = false;
        var bodyStyleSnapshot = {};

        if (!toggle || !panel || !overlay) {
            return;
        }

        toggle.addEventListener('click', function () {
            if (isMenuOpen) {
                closeMobileMenu();
                return;
            }

            openMobileMenu();
        });

        overlay.addEventListener('click', function (event) {
            event.stopPropagation();
            closeMobileMenu();
        });

        document.addEventListener('click', function (event) {
            if (isMenuOpen && !root.contains(event.target) && event.target !== overlay) {
                closeMobileMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isMenuOpen) {
                closeMobileMenu();
                toggle.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (toggle.getAttribute('aria-expanded') === 'true') {
                updateOverlayOffset();
            }
        });

        window.addEventListener('orientationchange', function () {
            if (toggle.getAttribute('aria-expanded') === 'true') {
                window.setTimeout(updateOverlayOffset, 180);
            }
        });

        function openMobileMenu() {
            window.clearTimeout(closeTimer);

            isMenuOpen = true;
            isClosing = false;
            panel.hidden = false;
            overlay.hidden = false;
            overlay.classList.remove('is-closing');
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            root.classList.remove('is-closing');
            root.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            lockScroll();
            updateOverlayOffset();
        }

        function closeMobileMenu() {
            if (!isMenuOpen && !isClosing) {
                unlockScroll();
                return;
            }

            if (isClosing) {
                return;
            }

            window.clearTimeout(closeTimer);

            isMenuOpen = false;
            isClosing = true;
            toggle.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
            root.classList.add('is-closing');
            overlay.classList.remove('is-open');
            overlay.classList.add('is-closing');
            overlay.setAttribute('aria-hidden', 'true');
            unlockScroll();

            closeTimer = window.setTimeout(finishClose, prefersReducedMotion() ? 1 : 170);
        }

        function finishClose() {
            panel.hidden = true;
            overlay.hidden = true;
            overlay.classList.remove('is-closing');
            root.classList.remove('is-closing');
            isClosing = false;
            unlockScroll();
        }

        function updateOverlayOffset() {
            var bottom = panel.getBoundingClientRect().bottom;
            overlay.style.setProperty('--sultana-admin-mobile-menu-overlay-top', Math.max(0, Math.ceil(bottom)) + 'px');
        }

        function lockScroll() {
            if (isScrollLocked) {
                return;
            }

            lockedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
            bodyStyleSnapshot = {
                position: document.body.style.position,
                top: document.body.style.top,
                left: document.body.style.left,
                right: document.body.style.right,
                width: document.body.style.width
            };
            isScrollLocked = true;
            document.body.classList.add('sultana-admin-mobile-menu-lock');
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + lockedScrollY + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
        }

        function unlockScroll() {
            if (!isScrollLocked) {
                return;
            }

            document.body.classList.remove('sultana-admin-mobile-menu-lock');
            document.body.style.position = bodyStyleSnapshot.position || '';
            document.body.style.top = bodyStyleSnapshot.top || '';
            document.body.style.left = bodyStyleSnapshot.left || '';
            document.body.style.right = bodyStyleSnapshot.right || '';
            document.body.style.width = bodyStyleSnapshot.width || '';
            isScrollLocked = false;
            window.scrollTo(0, lockedScrollY);
        }

        function prefersReducedMotion() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }
    });
}());
