(function () {
    'use strict';

    var config = window.SultanaAdminProductList || {};
    var strings = config.strings || {};
    var confirmedTrashForms = new WeakSet();
    var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 760px)') : null;
    var lists = [
        {
            root: document.querySelector('.sultana-admin-product-cards'),
            header: '.sultana-admin-product-card__header'
        },
        {
            root: document.querySelector('.sultana-admin-order-cards'),
            header: '.sultana-admin-order-card__header'
        },
        {
            root: document.querySelector('.sultana-admin-customer-cards'),
            header: '.sultana-admin-customer-card__header'
        },
        {
            root: document.querySelector('.sultana-admin-review-cards'),
            header: '.sultana-admin-review-card__header'
        }
    ];

    document.querySelectorAll('[data-sultana-product-trash-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (confirmedTrashForms.has(form)) {
                disableSubmitButtons(form);
                return;
            }

            event.preventDefault();

            if (!window.SultanaAdminModal || 'function' !== typeof window.SultanaAdminModal.open) {
                return;
            }

            window.SultanaAdminModal.open({
                message: strings.deleteProductMessage || '¿Quieres eliminar este producto?',
                messageAsTitle: true,
                detail: strings.deleteProductDetail || 'El producto sera enviado a la papelera y dejara de mostrarse en la tienda.',
                confirmText: strings.deleteProductConfirm || 'Eliminar producto',
                cancelText: strings.cancel || 'Cancelar',
                variant: 'danger',
                onConfirm: function () {
                    confirmedTrashForms.add(form);
                    disableSubmitButtons(form);

                    if ('function' === typeof form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }
            });
        });
    });

    document.querySelectorAll('.sultana-admin-search').forEach(function (searchForm) {
        if (searchForm.hasAttribute('data-review-search')) {
            return;
        }

        var searchInput = searchForm.querySelector('input[type="search"][name="s"]');
        var searchButton = searchForm.querySelector('.sultana-admin-search__button');
        var searchIcon = searchButton ? searchButton.querySelector('.sultana-admin-icon') : null;
        var appliedSearch = searchForm.getAttribute('data-applied-search') || '';
        var clearUrl = searchForm.getAttribute('data-clear-url') || searchForm.getAttribute('action') || '';
        var mobileClearOnly = searchForm.getAttribute('data-mobile-clear-only') === 'true';

        function isMobileSearchMode() {
            return !mobileClearOnly || !mobileQuery || mobileQuery.matches;
        }

        function setSearchMode(mode) {
            var isClear = mode === 'clear' && isMobileSearchMode();
            var isDesktop = mobileClearOnly && !isMobileSearchMode();
            var label = isDesktop
                ? searchButton.getAttribute('data-desktop-label') || searchButton.getAttribute('data-search-label') || ''
                : searchButton.getAttribute(isClear ? 'data-clear-label' : 'data-search-label') || '';
            var icon = isDesktop
                ? searchButton.getAttribute('data-desktop-icon') || searchButton.getAttribute('data-search-icon') || ''
                : searchButton.getAttribute(isClear ? 'data-clear-icon' : 'data-search-icon') || '';

            searchButton.setAttribute('aria-label', label);
            searchButton.setAttribute('title', label);
            searchButton.setAttribute('data-mode', isClear ? 'clear' : 'search');

            if (searchIcon && icon) {
                searchIcon.style.setProperty('--sultana-admin-icon-url', "url('" + icon + "')");
            }
        }

        function syncSearchMode() {
            var currentValue = searchInput ? searchInput.value : '';

            setSearchMode(appliedSearch !== '' && currentValue === appliedSearch ? 'clear' : 'search');
        }

        if (searchInput && searchButton) {
            syncSearchMode();
            searchInput.addEventListener('input', syncSearchMode);

            if (mobileQuery && mobileQuery.addEventListener) {
                mobileQuery.addEventListener('change', syncSearchMode);
            }

            searchButton.addEventListener('click', function (event) {
                if (searchButton.getAttribute('data-mode') !== 'clear') {
                    return;
                }

                event.preventDefault();
                window.location.assign(clearUrl);
            });
        }
    });

    function disableSubmitButtons(form) {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.disabled = true;
        });
    }

    function setCardState(button, expanded) {
        var panelId = button.getAttribute('aria-controls');
        var panel = panelId ? document.getElementById(panelId) : null;

        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        if (panel) {
            panel.hidden = !expanded;
        }
    }

    lists.forEach(function (config) {
        var list = config.root;

        if (!list) {
            return;
        }

        list.addEventListener('click', function (event) {
            var button = event.target.closest(config.header);

            if (!button || !list.contains(button)) {
                return;
            }

            var shouldOpen = button.getAttribute('aria-expanded') !== 'true';
            var buttons = list.querySelectorAll(config.header + '[aria-expanded="true"]');

            buttons.forEach(function (openButton) {
                if (openButton !== button) {
                    setCardState(openButton, false);
                }
            });

            setCardState(button, shouldOpen);
        });
    });
}());
