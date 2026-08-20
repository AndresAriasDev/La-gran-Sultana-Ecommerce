(function () {
    'use strict';

    var list = document.querySelector('.sultana-admin-product-cards');
    var searchForm = document.querySelector('.sultana-admin-search');

    if (searchForm) {
        var searchInput = searchForm.querySelector('input[type="search"][name="s"]');
        var searchButton = searchForm.querySelector('.sultana-admin-search__button');
        var searchIcon = searchButton ? searchButton.querySelector('.sultana-admin-icon') : null;
        var appliedSearch = searchForm.getAttribute('data-applied-search') || '';
        var clearUrl = searchForm.getAttribute('data-clear-url') || searchForm.getAttribute('action') || '';

        function setSearchMode(mode) {
            var isClear = mode === 'clear';
            var label = searchButton.getAttribute(isClear ? 'data-clear-label' : 'data-search-label') || '';
            var icon = searchButton.getAttribute(isClear ? 'data-clear-icon' : 'data-search-icon') || '';

            searchButton.setAttribute('aria-label', label);
            searchButton.setAttribute('title', label);
            searchButton.setAttribute('data-mode', mode);

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

            searchButton.addEventListener('click', function (event) {
                if (searchButton.getAttribute('data-mode') !== 'clear') {
                    return;
                }

                event.preventDefault();
                window.location.assign(clearUrl);
            });
        }
    }

    if (!list) {
        return;
    }

    function setCardState(button, expanded) {
        var panelId = button.getAttribute('aria-controls');
        var panel = panelId ? document.getElementById(panelId) : null;

        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        if (panel) {
            panel.hidden = !expanded;
        }
    }

    list.addEventListener('click', function (event) {
        var button = event.target.closest('.sultana-admin-product-card__header');

        if (!button || !list.contains(button)) {
            return;
        }

        var shouldOpen = button.getAttribute('aria-expanded') !== 'true';
        var buttons = list.querySelectorAll('.sultana-admin-product-card__header[aria-expanded="true"]');

        buttons.forEach(function (openButton) {
            if (openButton !== button) {
                setCardState(openButton, false);
            }
        });

        setCardState(button, shouldOpen);
    });
}());
