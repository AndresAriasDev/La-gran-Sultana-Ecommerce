(function () {
    const pickers = document.querySelectorAll('[data-sultana-category-picker]');

    if (!pickers.length) {
        return;
    }

    pickers.forEach(function (picker) {
        const search = picker.querySelector('[data-sultana-category-search]');
        const results = picker.querySelector('[data-sultana-category-results]');
        const selected = picker.querySelector('[data-sultana-category-selected]');
        const checkboxes = Array.from(picker.querySelectorAll('[data-sultana-category-checkboxes] input[type="checkbox"]'));

        if (!search || !results || !selected || !checkboxes.length) {
            return;
        }

        const categories = checkboxes.map(function (checkbox) {
            const label = checkbox.closest('label');
            const name = label ? (label.textContent || '').trim() : checkbox.value;

            return {
                id: checkbox.value,
                name: name,
                checkbox: checkbox
            };
        });

        picker.classList.add('is-enhanced');
        renderSelected();
        renderResults('');

        search.addEventListener('input', function () {
            renderResults(search.value);
            openResults();
        });

        search.addEventListener('focus', function () {
            renderResults(search.value);
            openResults();
        });

        search.addEventListener('keydown', function (event) {
            const firstOption = results.querySelector('[data-sultana-category-option]');

            if ('Escape' === event.key) {
                closeResults();
                return;
            }

            if ('ArrowDown' === event.key && firstOption) {
                event.preventDefault();
                firstOption.focus();
                return;
            }

            if ('Enter' === event.key && firstOption) {
                event.preventDefault();
                firstOption.click();
            }
        });

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) {
                closeResults();
            }
        });

        function renderSelected() {
            selected.innerHTML = '';

            categories
                .filter(function (category) {
                    return category.checkbox.checked;
                })
                .forEach(function (category) {
                    const chip = document.createElement('button');
                    const text = document.createElement('span');
                    const remove = document.createElement('span');

                    chip.type = 'button';
                    chip.className = 'sultana-admin-category-chip';
                    chip.setAttribute('aria-label', 'Eliminar categoría: ' + category.name);

                    text.className = 'sultana-admin-category-chip__text';
                    text.textContent = category.name;

                    remove.className = 'sultana-admin-category-chip__remove';
                    remove.textContent = '×';
                    remove.setAttribute('aria-hidden', 'true');

                    chip.addEventListener('click', function () {
                        category.checkbox.checked = false;
                        renderSelected();
                        renderResults(search.value);
                        search.focus();
                    });
                    chip.appendChild(text);
                    chip.appendChild(remove);
                    selected.appendChild(chip);
                });
        }

        function renderResults(query) {
            const normalizedQuery = normalize(query);
            const matches = categories.filter(function (category) {
                return !category.checkbox.checked && (!normalizedQuery || normalize(category.name).indexOf(normalizedQuery) !== -1);
            }).slice(0, 8);

            results.innerHTML = '';

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'sultana-admin-category-picker__empty';
                empty.textContent = 'Sin resultados';
                results.appendChild(empty);
                return;
            }

            matches.forEach(function (category) {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'sultana-admin-category-picker__option';
                option.textContent = category.name;
                option.setAttribute('role', 'option');
                option.dataset.sultanaCategoryOption = category.id;
                option.addEventListener('click', function () {
                    category.checkbox.checked = true;
                    search.value = '';
                    renderSelected();
                    renderResults('');
                    closeResults();
                    search.focus();
                });
                option.addEventListener('keydown', function (event) {
                    if ('Escape' === event.key) {
                        closeResults();
                        search.focus();
                    }
                });
                results.appendChild(option);
            });
        }

        function openResults() {
            results.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        }

        function closeResults() {
            results.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        }

        function normalize(value) {
            return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
    });
}());
