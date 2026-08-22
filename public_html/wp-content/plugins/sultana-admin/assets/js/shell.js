(function () {
    'use strict';

    document.querySelectorAll('[data-sultana-mobile-menu]').forEach(function (root) {
        var toggle = root.querySelector('[data-sultana-mobile-menu-toggle]');
        var panel = root.querySelector('[data-sultana-mobile-menu-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });

        function setOpen(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
            root.classList.toggle('is-open', open);
        }
    });
}());
