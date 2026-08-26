(function () {
    const config = window.SultanaAdminProductEditor || {};
    const strings = config.strings || {};
    const form = document.querySelector('.sultana-admin-product-form');

    initCategoryPickers();
    initPreSubmitValidation();

    function initCategoryPickers() {
        const pickers = document.querySelectorAll('[data-sultana-category-picker]');

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
                        chip.setAttribute('aria-label', 'Eliminar categoria: ' + category.name);

                        text.className = 'sultana-admin-category-chip__text';
                        text.textContent = category.name;

                        remove.className = 'sultana-admin-category-chip__remove';
                        remove.textContent = 'x';
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
        });
    }

    function initPreSubmitValidation() {
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            const result = validateProductForm();

            if (!result.errors.length) {
                clearFrontendErrors();
                return;
            }

            event.preventDefault();
            renderFrontendErrors(result.errors);
            focusFirstInvalidField(result.focus);
        });
    }

    function validateProductForm() {
        const typeInput = form.querySelector('[name="product_type"]');
        const productType = typeInput ? typeInput.value : 'simple';
        const result = {
            errors: [],
            focus: null
        };

        validateProductName(result);

        if ('variable' === productType) {
            validateVariableProduct(result);
        } else if ('combo' === productType) {
            validateComboProduct(result);
        } else {
            validateSimpleProduct(result);
        }

        result.errors = dedupe(result.errors);
        return result;
    }

    function validateProductName(result) {
        const name = form.querySelector('[name="name"]');

        if (!name || !name.value.trim()) {
            addError(result, message('nameRequired', 'Ingresa el nombre del producto.'), name);
        }
    }

    function validateSimpleProduct(result) {
        const regular = form.querySelector('[name="regular_price"]');
        const sale = form.querySelector('[name="sale_price"]');
        const stock = form.querySelector('[name="stock_quantity"]');
        const weight = form.querySelector('[name="weight"]');
        const regularValue = regular ? parseDecimal(regular.value) : null;

        if (null === regularValue) {
            addError(result, message('regularPriceInvalid', 'Ingresa un precio regular valido.'), regular);
        }

        if (sale && '' !== sale.value.trim()) {
            const saleValue = parseDecimal(sale.value);

            if (null === saleValue) {
                addError(result, message('salePriceInvalid', 'Ingresa un precio de oferta valido.'), sale);
            } else if (null !== regularValue && saleValue >= regularValue) {
                addError(result, message('salePriceLowerThanRegular', 'El precio de oferta debe ser menor al precio regular.'), sale);
            }
        }

        if (!isIntegerString(stock ? stock.value : '')) {
            addError(result, message('stockInvalid', 'Ingresa una cantidad de stock valida.'), stock);
        }

        if (!isPositiveDecimal(weight ? weight.value : '')) {
            addError(result, message('weightInvalid', 'Ingresa un peso valido para el producto.'), weight);
        }
    }

    function validateVariableProduct(result) {
        const variableEditor = window.SultanaAdminProductVariableEditor;

        if (!variableEditor || 'function' !== typeof variableEditor.validatePreSubmit) {
            return;
        }

        const variableResult = variableEditor.validatePreSubmit();

        (variableResult.errors || []).forEach(function (error) {
            addError(result, error);
        });

        if (!result.focus && variableResult.focus) {
            result.focus = variableResult.focus;
        }
    }

    function validateComboProduct(result) {
        const sale = form.querySelector('[name="sale_price"]');
        const rows = Array.from(form.querySelectorAll('.sultana-admin-combo-component'));
        const hasSelectedComponent = rows.some(function (row) {
            return toInt(inputValue(row, '[name$="[selected_id]"]')) || toInt(inputValue(row, '[name$="[product_id]"]')) || toInt(inputValue(row, '[name$="[variation_id]"]'));
        });

        if (!hasSelectedComponent) {
            addError(result, message('comboComponentRequired', 'Selecciona al menos un componente.'), rows[0] ? rows[0].querySelector('input[type="search"]') : null);
        }

        rows.forEach(function (row) {
            const quantity = row.querySelector('[name$="[quantity]"]');

            if (!isPositiveIntegerString(quantity ? quantity.value : '')) {
                addError(result, message('comboQuantityInvalid', 'La cantidad debe ser un numero entero.'), quantity);
            }
        });

        if (sale && '' !== sale.value.trim()) {
            const saleValue = parseDecimal(sale.value);
            const currentValue = comboCurrentValue(rows);

            if (null === saleValue || saleValue <= 0) {
                addError(result, message('comboSaleInvalid', 'Ingresa un precio de oferta valido.'), sale);
            } else if (null !== currentValue && saleValue >= currentValue) {
                addError(result, message('comboSaleLowerThanCurrent', 'El precio de oferta debe ser menor que el precio actual del combo.'), sale);
            }
        }
    }

    function comboCurrentValue(rows) {
        const total = rows.reduce(function (sum, row) {
            const price = parseDecimal(inputValue(row, '[name$="[regular_price]"]'));
            const quantity = parseInt(inputValue(row, '[name$="[quantity]"]'), 10) || 0;

            if (null === price || price <= 0 || quantity <= 0) {
                return sum;
            }

            return sum + (price * quantity);
        }, 0);

        return total > 0 ? total : null;
    }

    function addError(result, text, focus) {
        result.errors.push(text);

        if (!result.focus && focus) {
            result.focus = focus;
        }
    }

    function renderFrontendErrors(errors) {
        const list = frontendErrorList();
        const items = list.querySelector('ul');

        items.innerHTML = '';

        errors.forEach(function (error) {
            const item = document.createElement('li');
            item.textContent = error;
            items.appendChild(item);
        });

        list.hidden = false;
    }

    function frontendErrorList() {
        let list = document.querySelector('[data-sultana-frontend-error-list]');

        if (list) {
            return list;
        }

        list = document.querySelector('.sultana-admin-product-form-screen > .sultana-admin-error-list');

        if (!list) {
            list = document.createElement('div');
            list.className = 'sultana-admin-error-list';
            list.setAttribute('role', 'alert');

            const screen = document.querySelector('.sultana-admin-product-form-screen');

            if (screen && form) {
                screen.insertBefore(list, form);
            }
        }

        list.dataset.sultanaFrontendErrorList = '1';
        list.innerHTML = '';

        const title = document.createElement('strong');
        const items = document.createElement('ul');

        title.textContent = message('reviewForm', 'Revisa el formulario');
        list.appendChild(title);
        list.appendChild(items);

        return list;
    }

    function clearFrontendErrors() {
        const list = document.querySelector('[data-sultana-frontend-error-list]');

        if (list) {
            list.remove();
        }
    }

    function focusFirstInvalidField(focus) {
        if (!focus) {
            return;
        }

        if ('function' === typeof focus) {
            focus();
            return;
        }

        if ('function' === typeof focus.focus) {
            try {
                focus.focus({ preventScroll: true });
            } catch (error) {
                focus.focus();
            }
        }
    }

    function inputValue(root, selector) {
        const input = root.querySelector(selector);
        return input ? input.value : '';
    }

    function dedupe(items) {
        const seen = {};

        return items.filter(function (item) {
            if (seen[item]) {
                return false;
            }

            seen[item] = true;
            return true;
        });
    }

    function parseDecimal(value) {
        const normalized = String(value || '').trim().replace(',', '.');

        if ('' === normalized || !isFinite(normalized)) {
            return null;
        }

        const number = Number(normalized);

        return number >= 0 ? number : null;
    }

    function isPositiveDecimal(value) {
        const number = parseDecimal(value);

        return null !== number && number > 0;
    }

    function isIntegerString(value) {
        return /^\d+$/.test(String(value || '').trim());
    }

    function isPositiveIntegerString(value) {
        return isIntegerString(value) && parseInt(value, 10) > 0;
    }

    function toInt(value) {
        return parseInt(value, 10) || 0;
    }

    function message(key, fallback) {
        return strings[key] || fallback;
    }

    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
}());
