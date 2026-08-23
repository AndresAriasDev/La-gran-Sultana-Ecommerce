(function () {
    'use strict';

    document.querySelectorAll('[data-review-search]').forEach(function (form) {
        var input = form.querySelector('input[type="search"][name="s"]');
        var select = form.querySelector('select[name="status"]');
        var button = form.querySelector('.sultana-admin-search__button');
        var icon = button ? button.querySelector('.sultana-admin-icon') : null;
        var appliedSearch = (form.getAttribute('data-applied-search') || '').trim();
        var appliedStatus = form.getAttribute('data-applied-status') || '';
        var defaultStatus = form.getAttribute('data-default-status') || '';
        var clearUrl = form.getAttribute('data-clear-url') || form.getAttribute('action') || window.location.pathname;

        if (!button) {
            return;
        }

        function currentSearch() {
            return input ? input.value.trim() : '';
        }

        function currentStatus() {
            return select ? select.value : defaultStatus;
        }

        function hasAppliedFilters() {
            return appliedSearch !== '' || appliedStatus !== defaultStatus;
        }

        function visibleMatchesApplied() {
            return currentSearch() === appliedSearch && currentStatus() === appliedStatus;
        }

        function setMode(mode) {
            var isClear = mode === 'clear';
            var label = button.getAttribute(isClear ? 'data-clear-label' : 'data-search-label') || '';
            var iconUrl = button.getAttribute(isClear ? 'data-clear-icon' : 'data-search-icon') || '';

            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('data-mode', isClear ? 'clear' : 'search');

            if (icon && iconUrl) {
                icon.style.setProperty('--sultana-admin-icon-url', "url('" + iconUrl + "')");
            }
        }

        function syncMode() {
            setMode(hasAppliedFilters() && visibleMatchesApplied() ? 'clear' : 'search');
        }

        function buildFilteredUrl() {
            var url = new URL(form.getAttribute('action') || window.location.pathname, window.location.origin);
            var search = currentSearch();
            var status = currentStatus();

            url.search = '';

            if (search !== '') {
                url.searchParams.set('s', search);
            }

            if (status !== defaultStatus) {
                url.searchParams.set('status', status);
            }

            return url.toString();
        }

        syncMode();

        if (input) {
            input.addEventListener('input', syncMode);
        }

        if (select) {
            select.addEventListener('change', syncMode);
        }

        button.addEventListener('click', function (event) {
            if (button.getAttribute('data-mode') !== 'clear') {
                return;
            }

            event.preventDefault();
            window.location.assign(clearUrl);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.location.assign(buildFilteredUrl());
        });
    });
}());
