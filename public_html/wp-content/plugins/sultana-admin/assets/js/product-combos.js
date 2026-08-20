(function () {
    const config = window.SultanaAdminProductCombos || {};
    const editor = document.querySelector('[data-sultana-combo-editor]');

    if (!editor) {
        return;
    }

    const root = editor.querySelector('[data-sultana-combo-components]');
    const addButton = editor.querySelector('[data-sultana-add-combo-component]');
    const status = editor.querySelector('[data-sultana-combo-status]');
    const currentPrice = document.querySelector('[data-sultana-combo-current-price]');
    const strings = config.strings || {};
    const icons = config.icons || {};
    const currencySymbol = config.currencySymbol || 'C$';
    const initialState = readJson(editor.getAttribute('data-initial-state'), {});
    let components = Array.isArray(initialState.components) ? initialState.components.map(normalizeComponent) : [];
    let searchCounter = 0;

    if (!components.length) {
        components.push(emptyComponent());
    }

    render();

    if (addButton) {
        addButton.addEventListener('click', function () {
            components.push(emptyComponent());
            render();
        });
    }

    function readJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function toInt(value) {
        return parseInt(value, 10) || 0;
    }

    function emptyComponent() {
        return {
            product_id: 0,
            variation_id: 0,
            selected_id: 0,
            label: '',
            quantity: '1',
            regular_price: '',
            query: '',
            results: [],
            loading: false
        };
    }

    function normalizeComponent(component) {
        return {
            product_id: toInt(component.product_id),
            variation_id: toInt(component.variation_id),
            selected_id: toInt(component.selected_id) || toInt(component.variation_id) || toInt(component.product_id),
            label: component.label || '',
            quantity: component.quantity || '1',
            regular_price: component.regular_price || '',
            query: '',
            results: [],
            loading: false
        };
    }

    function render() {
        if (!root) {
            return;
        }

        root.innerHTML = '';

        components.forEach(function (component, index) {
            const row = document.createElement('div');
            row.className = 'sultana-admin-combo-component';

            row.appendChild(hidden('combo_components[' + index + '][product_id]', component.product_id));
            row.appendChild(hidden('combo_components[' + index + '][variation_id]', component.variation_id));
            row.appendChild(hidden('combo_components[' + index + '][selected_id]', component.selected_id));
            row.appendChild(hidden('combo_components[' + index + '][label]', component.label));
            row.appendChild(hidden('combo_components[' + index + '][regular_price]', component.regular_price));

            const searchWrap = document.createElement('div');
            searchWrap.className = 'sultana-admin-combo-search';

            const searchLabel = document.createElement('label');
            searchLabel.textContent = strings.component || 'Producto o variacion';
            searchWrap.appendChild(searchLabel);

            const input = document.createElement('input');
            input.type = 'search';
            input.autocomplete = 'off';
            input.value = component.label || component.query || '';
            input.placeholder = strings.searchPlaceholder || 'Buscar producto o variacion';
            input.addEventListener('input', function () {
                component.query = input.value;
                component.label = component.selected_id ? component.label : '';

                if (component.selected_id && input.value !== component.label) {
                    clearSelection(component);
                    clearHiddenSelection(row);
                }

                queueSearch(component, index, input.value);
            });
            searchWrap.appendChild(input);

            if (component.loading) {
                const loading = document.createElement('p');
                loading.className = 'sultana-admin-field-help';
                loading.textContent = strings.searching || 'Buscando...';
                searchWrap.appendChild(loading);
            }

            if (component.results.length) {
                const list = document.createElement('div');
                list.className = 'sultana-admin-combo-results';

                component.results.forEach(function (result) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = result.label;
                    button.addEventListener('click', function () {
                        components[index] = normalizeComponent({
                            product_id: result.product_id,
                            variation_id: result.variation_id,
                            selected_id: result.selected_id,
                            label: result.label,
                            regular_price: result.regular_price,
                            quantity: component.quantity || '1'
                        });
                        setStatus('');
                        render();
                    });
                    list.appendChild(button);
                });

                searchWrap.appendChild(list);
            }

            row.appendChild(searchWrap);

            const quantityWrap = document.createElement('div');
            const quantityLabel = document.createElement('label');
            quantityLabel.textContent = strings.quantity || 'Cantidad';
            quantityWrap.appendChild(quantityLabel);

            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.name = 'combo_components[' + index + '][quantity]';
            quantity.min = '1';
            quantity.step = '1';
            quantity.inputMode = 'numeric';
            quantity.value = component.quantity;
            quantity.required = true;
            quantity.addEventListener('input', function () {
                component.quantity = quantity.value;
                updateCurrentPrice();
            });
            quantityWrap.appendChild(quantity);
            row.appendChild(quantityWrap);

            const actions = document.createElement('div');
            actions.className = 'sultana-admin-combo-component-actions';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sultana-admin-icon-button sultana-admin-icon-button--danger';
            remove.setAttribute('aria-label', strings.remove || 'Quitar producto');
            remove.setAttribute('title', strings.remove || 'Quitar producto');
            appendIcon(remove, 'trash');
            remove.addEventListener('click', function () {
                components.splice(index, 1);

                if (!components.length) {
                    components.push(emptyComponent());
                }

                render();
            });
            actions.appendChild(remove);
            row.appendChild(actions);

            root.appendChild(row);
        });

        updateCurrentPrice();
    }

    function hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value || '');
        return input;
    }

    function clearSelection(component) {
        component.product_id = 0;
        component.variation_id = 0;
        component.selected_id = 0;
        component.label = '';
        component.regular_price = '';
    }

    function clearHiddenSelection(row) {
        row.querySelectorAll('input[type="hidden"]').forEach(function (input) {
            input.value = '';
        });
    }

    function queueSearch(component, index, query) {
        const term = query.trim();
        const token = ++searchCounter;

        if (term.length < 2) {
            component.results = [];
            component.loading = false;
            return;
        }

        component.loading = true;

        window.setTimeout(function () {
            if (token !== searchCounter) {
                return;
            }

            search(term)
                .then(function (results) {
                    if (token !== searchCounter || !components[index]) {
                        return;
                    }

                    components[index].results = results;
                    components[index].loading = false;
                    render();
                })
                .catch(function () {
                    if (token !== searchCounter || !components[index]) {
                        return;
                    }

                    components[index].results = [];
                    components[index].loading = false;
                    setStatus(strings.searchError || 'No se pudo buscar componentes.');
                    render();
                });
        }, 250);
    }

    function search(term) {
        const params = new URLSearchParams();
        params.set('action', config.searchAction || '');
        params.set('nonce', config.nonce || '');
        params.set('term', term);
        params.set('limit', '20');

        selectedIds().forEach(function (id) {
            params.append('exclude[]', String(id));
        });

        return window.fetch((config.ajaxUrl || '') + '?' + params.toString(), {
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error('combo_search_failed');
                }

                return Array.isArray(payload.data.components)
                    ? payload.data.components.filter(function (component) {
                        return selectedIds().indexOf(toInt(component.selected_id)) === -1;
                    })
                    : [];
            });
    }

    function selectedIds() {
        return components.reduce(function (ids, component) {
            const selectedId = toInt(component.selected_id);

            if (selectedId && ids.indexOf(selectedId) === -1) {
                ids.push(selectedId);
            }

            return ids;
        }, []);
    }

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function updateCurrentPrice() {
        if (!currentPrice) {
            return;
        }

        const total = components.reduce(function (sum, component) {
            const price = parseFloat(String(component.regular_price || '').replace(',', '.'));
            const quantity = parseInt(component.quantity, 10) || 0;

            if (!isFinite(price) || price <= 0 || quantity <= 0) {
                return sum;
            }

            return sum + (price * quantity);
        }, 0);

        currentPrice.value = total > 0 ? currencySymbol + formatMoney(total) : '';
    }

    function formatMoney(value) {
        return value.toLocaleString('es-NI', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function appendIcon(target, iconName) {
        const url = icons[iconName] || '';

        if (!url) {
            return;
        }

        const icon = document.createElement('span');
        icon.className = 'sultana-admin-icon';
        icon.style.setProperty('--sultana-admin-icon-url', 'url("' + url + '")');
        icon.setAttribute('aria-hidden', 'true');
        target.appendChild(icon);
    }
})();
