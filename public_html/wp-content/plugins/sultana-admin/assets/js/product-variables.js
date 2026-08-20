(function () {
    const config = window.SultanaAdminProductVariables || {};
    const editor = document.querySelector('[data-sultana-variable-editor]');

    if (!editor) {
        return;
    }

    const attributesRoot = editor.querySelector('[data-sultana-variable-attributes]');
    const variationsRoot = editor.querySelector('[data-sultana-variation-list]');
    const addAttributeButton = editor.querySelector('[data-sultana-add-attribute]');
    const generateButton = editor.querySelector('[data-sultana-generate-variations]');
    const status = editor.querySelector('[data-sultana-variable-status]');
    const countStatus = editor.querySelector('[data-sultana-variation-count]');
    const strings = config.strings || {};
    const icons = config.icons || {};
    const availableAttributes = readJson(editor.getAttribute('data-available-attributes'), []);
    const initialState = readJson(editor.getAttribute('data-initial-state'), {});
    let variationClientCounter = 0;
    let selectedAttributes = Array.isArray(initialState.attributes) ? normalizeAttributes(initialState.attributes) : [];
    let variations = Array.isArray(initialState.variations) ? normalizeVariations(initialState.variations) : [];
    let attributeCounter = 0;
    let uploadCounter = 0;
    let openValuePicker = null;
    let deletedVariationIds = [];
    let forcedOpenVariation = null;
    let pendingSelectChange = null;
    const modal = createAdminModal();
    const deletedVariationsRoot = document.createElement('div');
    deletedVariationsRoot.hidden = true;
    editor.appendChild(deletedVariationsRoot);

    if (!selectedAttributes.length) {
        selectedAttributes.push({ taxonomy: '', term_ids: [] });
    }

    renderAttributes();
    renderVariations();
    updateCombinationCount();
    updateGenerateActionState();
    updateSubmitState();

    if (addAttributeButton) {
        addAttributeButton.addEventListener('click', function () {
            selectedAttributes.push({ taxonomy: '', term_ids: [] });
            renderAttributes();
            updateCombinationCount();
            updateGenerateActionState();
        });
    }

    if (generateButton) {
        generateButton.addEventListener('click', function () {
            generateConcreteVariations();
        });
    }

    document.addEventListener('click', function (event) {
        if (openValuePicker && !openValuePicker.picker.contains(event.target)) {
            closeResults(openValuePicker.search, openValuePicker.results);
        }
    });

    function readJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function normalizeAttributes(attributes) {
        return attributes.map(function (attribute) {
            return {
                taxonomy: attribute.taxonomy || '',
                term_ids: Array.isArray(attribute.term_ids) ? attribute.term_ids.map(toInt).filter(Boolean) : []
            };
        });
    }

    function normalizeVariations(items) {
        return items.map(function (variation) {
            return {
                id: toInt(variation.id),
                client_uid: variation.client_uid || nextVariationClientUid(),
                attributes: variation.attributes || {},
                sku: variation.sku || '',
                regular_price: variation.regular_price || '',
                sale_price: variation.sale_price || '',
                stock_quantity: variation.stock_quantity || '',
                weight: variation.weight || '',
                image_id: toInt(variation.image_id),
                image_url: variation.image_url || ''
            };
        });
    }

    function toInt(value) {
        return parseInt(value, 10) || 0;
    }

    function nextVariationClientUid() {
        variationClientCounter += 1;
        return 'variation-' + variationClientCounter;
    }

    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function renderAttributes() {
        if (!attributesRoot) {
            return;
        }

        attributesRoot.innerHTML = '';

        selectedAttributes.forEach(function (selected, index) {
            const block = document.createElement('div');
            block.className = 'sultana-admin-variable-attribute';

            const header = document.createElement('div');
            header.className = 'sultana-admin-variable-attribute__header';
            header.classList.toggle('sultana-admin-variable-attribute__header--removable', index > 0);

            const select = document.createElement('select');
            select.name = 'variable_attributes[' + index + '][taxonomy]';
            select.setAttribute('aria-label', strings.selectAttribute || 'Selecciona atributo');
            select.appendChild(option('', strings.selectAttribute || 'Selecciona atributo'));

            availableAttributes.forEach(function (attribute) {
                const item = option(attribute.taxonomy, attribute.label, selected.taxonomy === attribute.taxonomy);
                item.disabled = selectedAttributes.some(function (current, currentIndex) {
                    return currentIndex !== index && current.taxonomy === attribute.taxonomy;
                });
                select.appendChild(item);
            });

            select.addEventListener('change', function () {
                selectedAttributes[index] = { taxonomy: select.value, term_ids: [] };
                renderAttributes();
                updateCombinationCount();
                updateGenerateActionState();
            });

            const terms = document.createElement('div');
            terms.className = 'sultana-admin-variable-terms';
            const selectedValues = document.createElement('div');
            selectedValues.className = 'sultana-admin-attribute-value-picker__selected';
            selectedValues.setAttribute('aria-live', 'polite');
            const attribute = findAttribute(selected.taxonomy);
            let termItems = [];

            if (attribute) {
                termItems = attribute.terms.map(function (term) {
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    const termId = toInt(term.id);

                    checkbox.type = 'checkbox';
                    checkbox.name = 'variable_attributes[' + index + '][term_ids][]';
                    checkbox.value = String(term.id);
                    checkbox.checked = selected.term_ids.indexOf(termId) !== -1;
                    checkbox.addEventListener('change', function () {
                        if (checkbox.checked && selected.term_ids.indexOf(termId) === -1) {
                            selected.term_ids.push(termId);
                        }

                        if (!checkbox.checked) {
                            selected.term_ids = selected.term_ids.filter(function (current) {
                                return current !== termId;
                            });
                        }

                        updateCombinationCount();
                        updateGenerateActionState();
                    });

                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(term.name));
                    terms.appendChild(label);

                    return {
                        id: termId,
                        name: term.name || '',
                        checkbox: checkbox
                    };
                });
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sultana-admin-icon-button sultana-admin-icon-button--danger sultana-admin-variable-attribute__remove';
            remove.setAttribute('aria-label', strings.removeAttribute || 'Quitar atributo');
            remove.setAttribute('title', strings.removeAttribute || 'Quitar atributo');
            appendIcon(remove, 'trash');
            remove.addEventListener('click', function () {
                selectedAttributes.splice(index, 1);
                renderAttributes();
                updateCombinationCount();
                updateGenerateActionState();
            });

            header.appendChild(select);
            if (attribute) {
                header.appendChild(valuePicker(attribute, termItems, selectedValues));
            }
            if (index > 0) {
                header.appendChild(remove);
            }
            block.appendChild(header);
            if (attribute) {
                block.appendChild(selectedValues);
            }
            block.appendChild(terms);
            attributesRoot.appendChild(block);
        });
    }

    function valuePicker(attribute, termItems, selected) {
        const picker = document.createElement('div');
        const searchId = 'sultana-admin-attribute-value-search-' + attributeCounter++;
        const resultsId = 'sultana-admin-attribute-value-results-' + attributeCounter++;

        picker.className = 'sultana-admin-attribute-value-picker';

        const searchLabel = document.createElement('label');
        searchLabel.className = 'sultana-admin-visually-hidden';
        searchLabel.setAttribute('for', searchId);
        searchLabel.textContent = 'Buscar valores de ' + attribute.label;

        const search = document.createElement('input');
        search.id = searchId;
        search.className = 'sultana-admin-attribute-value-picker__search';
        search.type = 'search';
        search.placeholder = 'Buscar valores de ' + attribute.label + '...';
        search.autocomplete = 'off';
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-autocomplete', 'list');
        search.setAttribute('aria-expanded', 'false');
        search.setAttribute('aria-controls', resultsId);

        const results = document.createElement('div');
        results.id = resultsId;
        results.className = 'sultana-admin-attribute-value-picker__results';
        results.setAttribute('role', 'listbox');
        results.hidden = true;

        picker.appendChild(searchLabel);
        picker.appendChild(search);
        picker.appendChild(results);

        search.addEventListener('input', function () {
            renderValueResults(termItems, search, results, selected);
            openResults(picker, search, results);
        });

        search.addEventListener('focus', function () {
            renderValueResults(termItems, search, results, selected);
            openResults(picker, search, results);
        });

        search.addEventListener('keydown', function (event) {
            const firstOption = results.querySelector('[data-sultana-attribute-value-option]');

            if ('Escape' === event.key) {
                closeResults(search, results);
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

        renderSelectedValues(termItems, search, results, selected);
        renderValueResults(termItems, search, results, selected);

        return picker;
    }

    function renderSelectedValues(termItems, search, results, selected) {
        selected.innerHTML = '';

        termItems.filter(function (term) {
            return term.checkbox.checked;
        }).forEach(function (term) {
            const chip = document.createElement('button');
            const text = document.createElement('span');
            const remove = document.createElement('span');

            chip.type = 'button';
            chip.className = 'sultana-admin-category-chip sultana-admin-attribute-value-chip';
            chip.setAttribute('aria-label', 'Eliminar valor: ' + term.name);

            text.className = 'sultana-admin-category-chip__text';
            text.textContent = term.name;

            remove.className = 'sultana-admin-category-chip__remove';
            remove.textContent = 'x';
            remove.setAttribute('aria-hidden', 'true');

            chip.addEventListener('click', function () {
                setTermChecked(term.checkbox, false);
                renderSelectedValues(termItems, search, results, selected);
                renderValueResults(termItems, search, results, selected);
                updateGenerateActionState();
                search.focus();
            });

            chip.appendChild(text);
            chip.appendChild(remove);
            selected.appendChild(chip);
        });
    }

    function renderValueResults(termItems, search, results, selected) {
        const normalizedQuery = normalize(search.value);
        const matches = termItems.filter(function (term) {
            return !term.checkbox.checked && (!normalizedQuery || normalize(term.name).indexOf(normalizedQuery) !== -1);
        }).slice(0, 8);

        results.innerHTML = '';

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'sultana-admin-attribute-value-picker__empty';
            empty.textContent = 'Sin resultados';
            results.appendChild(empty);
            return;
        }

        matches.forEach(function (term) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'sultana-admin-attribute-value-picker__option';
            option.textContent = term.name;
            option.setAttribute('role', 'option');
            option.dataset.sultanaAttributeValueOption = String(term.id);

            option.addEventListener('click', function () {
                setTermChecked(term.checkbox, true);
                search.value = '';
                renderSelectedValues(termItems, search, results, selected);
                renderValueResults(termItems, search, results, selected);
                updateGenerateActionState();
                closeResults(search, results);
                search.focus();
            });

            option.addEventListener('keydown', function (event) {
                if ('Escape' === event.key) {
                    closeResults(search, results);
                    search.focus();
                }
            });

            results.appendChild(option);
        });
    }

    function setTermChecked(checkbox, checked) {
        if (checkbox.checked === checked) {
            return;
        }

        checkbox.checked = checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function openResults(picker, search, results) {
        if (openValuePicker && openValuePicker.results !== results) {
            closeResults(openValuePicker.search, openValuePicker.results);
        }

        openValuePicker = { picker: picker, search: search, results: results };
        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    function closeResults(search, results) {
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');

        if (openValuePicker && openValuePicker.results === results) {
            openValuePicker = null;
        }
    }

    function renderVariations() {
        if (!variationsRoot) {
            return;
        }

        variationsRoot.innerHTML = '';

        if (!variations.length) {
            return;
        }

        variations.forEach(function (variation, index) {
            const card = document.createElement('div');
            card.className = 'sultana-admin-variation-card';
            const panelId = 'sultana-admin-variation-panel-' + index;
            const isOpen = forcedOpenVariation === variation || (!forcedOpenVariation && !window.matchMedia('(max-width: 760px)').matches && 0 === index);
            const header = document.createElement('div');
            header.className = 'sultana-admin-variation-card__header';

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'sultana-admin-variation-card__toggle';
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-controls', panelId);

            const titleWrap = document.createElement('span');
            titleWrap.className = 'sultana-admin-variation-card__title-wrap';

            const title = document.createElement('span');
            title.className = 'sultana-admin-variation-card__title';
            title.textContent = variationLabel(variation);

            const summary = document.createElement('span');
            summary.className = 'sultana-admin-variation-card__summary';
            renderVariationSummary(summary, variation);

            const chevron = document.createElement('span');
            chevron.className = 'sultana-admin-variation-card__chevron';
            appendIcon(chevron, 'chevron');

            titleWrap.appendChild(title);

            titleWrap.appendChild(summary);

            toggle.appendChild(titleWrap);
            toggle.appendChild(chevron);

            const removeVariation = document.createElement('button');
            removeVariation.type = 'button';
            removeVariation.className = 'sultana-admin-icon-button sultana-admin-icon-button--danger sultana-admin-variation-card__remove';
            removeVariation.setAttribute('aria-label', 'Eliminar variacion');
            removeVariation.setAttribute('title', 'Eliminar variacion');
            appendIcon(removeVariation, 'trash');
            removeVariation.addEventListener('click', function () {
                removeVariationAt(index);
            });

            const panel = document.createElement('div');
            panel.id = panelId;
            panel.className = 'sultana-admin-variation-card__panel';

            toggle.addEventListener('click', function () {
                const expanded = 'true' === toggle.getAttribute('aria-expanded');

                if (expanded) {
                    setVariationPanelState(toggle, panel, false);
                    return;
                }

                closeOpenVariationPanels(toggle);
                setVariationPanelState(toggle, panel, true);
            });

            const id = hidden('variations[' + index + '][id]', variation.id);
            card.appendChild(id);

            panel.appendChild(variationAttributeFields(variation, index, title));

            panel.appendChild(field('SKU', 'variations[' + index + '][sku]', variation.sku, 'text', false, '', '', 'sku', function (value) {
                variation.sku = value;
                renderVariationSummary(summary, variation);
            }));
            panel.appendChild(field('Disponible', 'variations[' + index + '][stock_quantity]', variation.stock_quantity, 'number', true, '1', '', 'stock', function (value) {
                variation.stock_quantity = value;
                renderVariationSummary(summary, variation);
            }));
            panel.appendChild(field('Peso (kg)', 'variations[' + index + '][weight]', variation.weight, 'number', true, '0.01', '0.01', 'weight'));
            panel.appendChild(field('Precio regular', 'variations[' + index + '][regular_price]', variation.regular_price, 'number', true, '0.01', '', 'regular-price', function (value) {
                variation.regular_price = value;
                renderVariationSummary(summary, variation);
            }));
            panel.appendChild(field('Precio de oferta', 'variations[' + index + '][sale_price]', variation.sale_price, 'number', false, '0.01', '', 'sale-price', function (value) {
                variation.sale_price = value;
                renderVariationSummary(summary, variation);
            }));
            panel.appendChild(variationImageField(variation, index));
            setVariationPanelState(toggle, panel, isOpen);

            header.appendChild(toggle);
            header.appendChild(removeVariation);
            card.appendChild(header);
            card.appendChild(panel);
            variationsRoot.appendChild(card);
        });

        forcedOpenVariation = null;
    }

    function variationImageField(variation, index) {
        const wrap = document.createElement('div');
        wrap.className = 'sultana-admin-variation-image sultana-admin-variation-field sultana-admin-variation-field--image';

        const hiddenInput = hidden('variations[' + index + '][image_id]', variation.image_id);
        const preview = document.createElement('div');
        preview.className = 'sultana-admin-variation-image-preview';
        const label = document.createElement('span');
        label.className = 'sultana-admin-variation-field__label';
        label.textContent = strings.uploadImage || 'Imagen';

        if (variation.image_url) {
            const image = document.createElement('img');
            image.src = variation.image_url;
            image.alt = '';
            preview.appendChild(image);
        }

        const upload = document.createElement('input');
        upload.type = 'file';
        upload.className = 'sultana-admin-variation-image-input';
        upload.accept = 'image/jpeg,image/png,image/gif,image/webp';
        upload.addEventListener('change', function () {
            const file = upload.files && upload.files[0];
            upload.value = '';

            if (file) {
                uploadVariationImage(file, variation, hiddenInput, preview);
            }
        });

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'sultana-admin-variation-image-trigger';
        trigger.setAttribute('aria-label', strings.uploadImage || 'Imagen');
        trigger.addEventListener('click', function () {
            upload.click();
        });

        const triggerIcon = document.createElement('span');
        triggerIcon.className = 'sultana-admin-variation-image-trigger__icon';
        appendIcon(triggerIcon, variation.image_url ? '' : 'images');

        const triggerText = document.createElement('span');
        triggerText.className = 'sultana-admin-variation-image-trigger__text';
        triggerText.textContent = variation.image_url ? '' : (strings.uploadImage || 'Imagen');

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'sultana-admin-icon-button sultana-admin-icon-button--danger sultana-admin-variation-image-remove';
        remove.setAttribute('aria-label', strings.removeImage || 'Quitar imagen');
        remove.setAttribute('title', strings.removeImage || 'Quitar imagen');
        remove.hidden = !variation.image_id && !variation.image_url;
        appendIcon(remove, 'trash');
        remove.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            variation.image_id = 0;
            variation.image_url = '';
            hiddenInput.value = '0';
            preview.innerHTML = '';
            remove.hidden = true;
            triggerIcon.innerHTML = '';
            appendIcon(triggerIcon, 'images');
            triggerText.textContent = strings.uploadImage || 'Imagen';
        });

        trigger.appendChild(preview);
        trigger.appendChild(triggerIcon);
        trigger.appendChild(triggerText);

        wrap.appendChild(label);
        wrap.appendChild(hiddenInput);
        wrap.appendChild(upload);
        wrap.appendChild(trigger);
        wrap.appendChild(remove);

        return wrap;
    }

    function variationAttributeFields(variation, index, title) {
        const wrap = document.createElement('div');
        wrap.className = 'sultana-admin-variation-attributes';
        const renderedTaxonomies = [];

        selectedAttributes.filter(function (attribute) {
            return attribute.taxonomy && attribute.term_ids.length;
        }).forEach(function (selected) {
            const attribute = findAttribute(selected.taxonomy);

            if (!attribute) {
                return;
            }

            renderedTaxonomies.push(selected.taxonomy);

            const label = document.createElement('label');
            const span = document.createElement('span');
            const select = document.createElement('select');
            const currentValue = variation.attributes[selected.taxonomy] || '';

            label.className = 'sultana-admin-variation-attribute-field';
            span.className = 'sultana-admin-variation-field__label';
            span.textContent = attribute.label;

            select.name = 'variations[' + index + '][attributes][' + selected.taxonomy + ']';
            select.dataset.sultanaVariationAttribute = selected.taxonomy;
            select.appendChild(option('', anyAttributeLabel(attribute.label), '' === currentValue));

            const selectedTerms = selectedTermObjects(attribute, selected.term_ids);

            selectedTerms.forEach(function (term) {
                select.appendChild(option(term.slug, term.name, currentValue === term.slug));
            });

            if (currentValue && !selectedTerms.some(function (term) {
                return term.slug === currentValue;
            })) {
                select.appendChild(option(currentValue, currentValue, true));
            }

            if (typeof variation.attributes[selected.taxonomy] === 'undefined') {
                variation.attributes[selected.taxonomy] = '';
            }

            let previousValue = variation.attributes[selected.taxonomy] || '';

            select.addEventListener('change', function () {
                const nextValue = select.value;
                const previousAttributes = Object.assign({}, variation.attributes);
                const nextAttributes = Object.assign({}, variation.attributes);
                nextAttributes[selected.taxonomy] = nextValue;
                const absorbed = absorbableVariationsFor(nextAttributes, variation);
                const conflicting = overlappingVariationsFor(nextAttributes, variation).filter(function (conflict) {
                    return absorbed.indexOf(conflict) === -1;
                });

                if (isExactVariationUnavailable(variation, nextAttributes) || conflicting.length) {
                    select.value = previousValue;
                    refreshVariationAttributeOptions(wrap, variation);
                    setStatus('', false);
                    return;
                }

                if (absorbed.length) {
                    pendingSelectChange = {
                        variation: variation,
                        select: select,
                        taxonomy: selected.taxonomy,
                        value: nextValue,
                        previousValue: previousValue,
                        previousAttributes: previousAttributes,
                        title: title,
                        wrap: wrap,
                        remove: absorbed
                    };
                    select.value = previousValue;
                    openReplacementModal(absorbed);
                    return;
                }

                applyVariationAttributeChange(variation, selected.taxonomy, nextValue, title, wrap);
                previousValue = nextValue;
            });

            label.appendChild(span);
            label.appendChild(select);
            wrap.appendChild(label);
        });

        Object.keys(variation.attributes).forEach(function (taxonomy) {
            if (renderedTaxonomies.indexOf(taxonomy) !== -1) {
                return;
            }

            wrap.appendChild(hidden('variations[' + index + '][attributes][' + taxonomy + ']', variation.attributes[taxonomy]));
        });

        refreshVariationAttributeOptions(wrap, variation);

        return wrap;
    }

    function refreshVariationAttributeOptions(wrap, variation) {
        Array.prototype.forEach.call(wrap.querySelectorAll('select[data-sultana-variation-attribute]'), function (select) {
            const taxonomy = select.dataset.sultanaVariationAttribute || '';
            const currentValue = variation.attributes[taxonomy] || '';

            Array.prototype.forEach.call(select.options, function (item) {
                const candidate = Object.assign({}, variation.attributes);
                candidate[taxonomy] = item.value;
                const absorbed = absorbableVariationsFor(candidate, variation);
                const hasBlockingConflict = overlappingVariationsFor(candidate, variation).some(function (conflict) {
                    return absorbed.indexOf(conflict) === -1;
                });
                item.disabled = item.value !== currentValue && (isExactVariationUnavailable(variation, candidate) || hasBlockingConflict);
            });
        });
    }

    function selectedTermObjects(attribute, termIds) {
        return termIds.map(function (termId) {
            return attribute.terms.find(function (term) {
                return toInt(term.id) === toInt(termId);
            });
        }).filter(Boolean);
    }

    function anyAttributeLabel(label) {
        return 'Cualquier ' + String(label || '').toLowerCase();
    }

    function uploadVariationImage(file, variation, hiddenInput, preview) {
        const formData = new FormData();
        formData.append('action', config.uploadAction);
        formData.append('nonce', config.nonce);
        formData.append('image', file);

        uploadCounter += 1;
        setStatus(strings.uploading || 'Subiendo imagen...', false);
        updateSubmitState();

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data || !payload.data.image) {
                    throw new Error((payload && payload.data && payload.data.message) || strings.uploadError || 'No se pudo subir la imagen.');
                }

                variation.image_id = toInt(payload.data.image.id);
                variation.image_url = payload.data.image.url || '';
                hiddenInput.value = String(variation.image_id);
                preview.innerHTML = '';

                if (variation.image_url) {
                    const image = document.createElement('img');
                    image.src = variation.image_url;
                    image.alt = '';
                    preview.appendChild(image);
                }

                const wrap = preview.closest('.sultana-admin-variation-image');
                const remove = wrap ? wrap.querySelector('.sultana-admin-variation-image-remove') : null;
                const triggerIcon = wrap ? wrap.querySelector('.sultana-admin-variation-image-trigger__icon') : null;
                const triggerText = wrap ? wrap.querySelector('.sultana-admin-variation-image-trigger__text') : null;

                if (remove) {
                    remove.hidden = false;
                }

                if (triggerIcon) {
                    triggerIcon.innerHTML = '';
                }

                if (triggerText) {
                    triggerText.textContent = '';
                }

                setStatus('', false);
            })
            .catch(function (error) {
                setStatus(error.message || strings.uploadError || 'No se pudo subir la imagen.', true);
            })
            .finally(function () {
                uploadCounter = Math.max(0, uploadCounter - 1);
                updateSubmitState();
            });
    }

    function generateConcreteVariations() {
        if (!isAttributeConfigurationValid()) {
            setStatus('', false);
            updateGenerateActionState();
            return;
        }

        const plan = buildVariationSyncPlan();

        if (plan.remove.length) {
            openSyncModal(plan);
            return;
        }

        applyVariationSyncPlan(plan);
    }

    function buildVariationSyncPlan() {
        const existing = {};
        const candidates = cartesian(configuredAttributeGroups());
        const remove = [];

        variations.forEach(function (variation) {
            existing[variationKey(variation.attributes)] = true;

            if (!variationWithinConfiguredDomain(variation)) {
                remove.push(variation);
            }
        });

        const created = [];

        variations.forEach(function (variation) {
            if (remove.indexOf(variation) !== -1 || !hasWildcard(variation.attributes)) {
                return;
            }

            variations.forEach(function (covered) {
                if (variation === covered || remove.indexOf(covered) !== -1) {
                    return;
                }

                if (combinationCovers(variation.attributes, covered.attributes)) {
                    remove.push(covered);
                }
            });
        });

        candidates.forEach(function (attributes) {
            const key = variationKey(attributes);

            if (existing[key]) {
                return;
            }

            if (variations.some(function (variation) {
                return remove.indexOf(variation) === -1 && combinationCovers(variation.attributes, attributes);
            })) {
                return;
            }

            const variation = {
                id: 0,
                client_uid: nextVariationClientUid(),
                attributes: attributes,
                sku: '',
                regular_price: '',
                sale_price: '',
                stock_quantity: '',
                weight: '',
                image_id: 0,
                image_url: ''
            };

            existing[key] = true;
            created.push(variation);
        });

        return {
            created: created,
            remove: remove
        };
    }

    function applyVariationSyncPlan(plan) {
        removeVariations(plan.remove || []);

        if (plan.created.length) {
            variations = plan.created.concat(variations);
            forcedOpenVariation = plan.created[0];
        }

        setStatus('', false);
        renderVariations();
        updateCombinationCount();
        updateGenerateActionState();
        renderDeletedVariationInputs();
    }

    function removeVariationAt(index) {
        const variation = variations[index];

        if (!variation) {
            return;
        }

        removeVariations([variation]);
        setStatus('', false);
        renderVariations();
        updateCombinationCount();
        updateGenerateActionState();
        renderDeletedVariationInputs();
    }

    function removeVariations(items) {
        items.forEach(function (variation) {
            const index = variations.indexOf(variation);

            if (index === -1) {
                return;
            }

            if (variation.id && deletedVariationIds.indexOf(variation.id) === -1) {
                deletedVariationIds.push(variation.id);
            }

            variations.splice(index, 1);
        });
    }

    function renderDeletedVariationInputs() {
        deletedVariationsRoot.innerHTML = '';

        deletedVariationIds.forEach(function (variationId) {
            deletedVariationsRoot.appendChild(hidden('deleted_variation_ids[]', variationId));
        });
    }

    function updateCombinationCount() {
        if (!countStatus) {
            return;
        }

        const total = variations.length;

        if (1 === total) {
            countStatus.textContent = '1 variacion';
            countStatus.classList.remove('is-error');
            return;
        }

        if (total > 1) {
            countStatus.textContent = total + ' variaciones';
            countStatus.classList.remove('is-error');
            return;
        }

        if (!variations.length) {
            countStatus.textContent = '';
            countStatus.classList.remove('is-error');
            return;
        }

        countStatus.textContent = '';
        countStatus.classList.remove('is-error');
    }

    function updateGenerateActionState() {
        if (!generateButton) {
            return;
        }

        generateButton.disabled = !isAttributeConfigurationValid();
        generateButton.textContent = variations.length ? 'Actualizar variaciones' : 'Crear variaciones';
    }

    function isAttributeConfigurationValid() {
        return Boolean(configuredAttributeGroups().length && selectedAttributes.every(function (selected) {
            return Boolean(selected.taxonomy && selected.term_ids.length);
        }));
    }

    function configuredAttributeGroups() {
        return selectedAttributes.filter(function (selected) {
            return selected.taxonomy && selected.term_ids.length;
        }).map(function (selected) {
            const attribute = findAttribute(selected.taxonomy);
            const values = [];

            if (attribute) {
                selectedTermObjects(attribute, selected.term_ids).forEach(function (term) {
                    values.push({
                        taxonomy: selected.taxonomy,
                        slug: term.slug
                    });
                });
            }

            return values;
        }).filter(function (values) {
            return values.length;
        });
    }

    function cartesian(groups) {
        return groups.reduce(function (acc, group) {
            const next = [];

            acc.forEach(function (prefix) {
                group.forEach(function (term) {
                    const candidate = Object.assign({}, prefix);
                    candidate[term.taxonomy] = term.slug;
                    next.push(candidate);
                });
            });

            return next;
        }, [{}]);
    }

    function field(labelText, name, value, type, required, step, min, modifier, onInput) {
        const label = document.createElement('label');
        const span = document.createElement('span');
        const input = document.createElement('input');

        label.className = 'sultana-admin-variation-field' + (modifier ? ' sultana-admin-variation-field--' + modifier : '');
        span.className = 'sultana-admin-variation-field__label';
        span.textContent = labelText;
        input.type = type;
        input.name = name;
        input.value = value || '';
        input.dataset.sultanaRequired = required ? '1' : '0';

        if ('number' === type) {
            input.min = min || '0';
            input.step = step || '1';
            input.inputMode = 'decimal';
        }

        if ('function' === typeof onInput) {
            input.addEventListener('input', function () {
                onInput(input.value);
            });
        }

        label.appendChild(span);
        label.appendChild(input);

        return label;
    }

    function hidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value || '');

        return input;
    }

    function option(value, text, selected) {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = text;
        item.selected = Boolean(selected);

        return item;
    }

    function findAttribute(taxonomy) {
        return availableAttributes.find(function (attribute) {
            return attribute.taxonomy === taxonomy;
        });
    }

    function variationLabel(variation) {
        return Object.keys(variation.attributes).sort().map(function (taxonomy) {
            const attribute = findAttribute(taxonomy);
            const value = variation.attributes[taxonomy] || '';
            const term = attribute ? attribute.terms.find(function (current) {
                return current.slug === value;
            }) : null;

            return (attribute ? attribute.label : taxonomy) + ': ' + (value ? (term ? term.name : value) : anyAttributeLabel(attribute ? attribute.label : taxonomy));
        }).join(' / ');
    }

    function renderVariationSummary(target, variation) {
        target.innerHTML = '';

        appendSummaryText(target, variation.sku ? 'SKU: ' + variation.sku : '');
        appendSummaryText(target, variation.stock_quantity !== '' ? 'Disponible: ' + variation.stock_quantity : '');
        appendSummaryPrice(target, variation);
    }

    function appendSummaryText(target, text) {
        if (!text) {
            return;
        }

        appendSummarySeparator(target);
        target.appendChild(document.createTextNode(text));
    }

    function appendSummaryPrice(target, variation) {
        const regularPrice = String(variation.regular_price || '').trim();
        const salePrice = String(variation.sale_price || '').trim();

        if (!regularPrice) {
            return;
        }

        appendSummarySeparator(target);

        if (salePrice) {
            const regular = document.createElement('span');
            const sale = document.createElement('span');

            regular.className = 'sultana-admin-variation-summary__regular-price';
            regular.textContent = 'C$' + regularPrice;

            sale.className = 'sultana-admin-variation-summary__sale-price';
            sale.textContent = 'C$' + salePrice;

            target.appendChild(regular);
            target.appendChild(document.createTextNode(' '));
            target.appendChild(sale);
            return;
        }

        target.appendChild(document.createTextNode('C$' + regularPrice));
    }

    function appendSummarySeparator(target) {
        if (target.childNodes.length) {
            target.appendChild(document.createTextNode(' · '));
        }
    }

    function variationKey(attributes) {
        return Object.keys(attributes).sort().map(function (taxonomy) {
            return taxonomy + '=' + attributes[taxonomy];
        }).join('|');
    }

    function isExactVariationUnavailable(variation, candidate) {
        const candidateKey = variationKey(candidate);

        return variations.some(function (compare) {
            if (isSameVariation(variation, compare)) {
                return false;
            }

            return variationKey(compare.attributes) === candidateKey;
        });
    }

    function hasWildcard(attributes) {
        return Object.keys(attributes).some(function (taxonomy) {
            return !attributes[taxonomy];
        });
    }

    function combinationCovers(covering, covered) {
        const taxonomies = Object.keys(Object.assign({}, covering, covered));

        return taxonomies.every(function (taxonomy) {
            const coveringValue = covering[taxonomy] || '';
            const coveredValue = covered[taxonomy] || '';

            return !coveringValue || coveringValue === coveredValue;
        });
    }

    function combinationsOverlap(first, second) {
        const taxonomies = Object.keys(Object.assign({}, first, second));

        return taxonomies.every(function (taxonomy) {
            const firstValue = first[taxonomy] || '';
            const secondValue = second[taxonomy] || '';

            return !firstValue || !secondValue || firstValue === secondValue;
        });
    }

    function absorbableVariationsFor(attributes, currentVariation) {
        if (!hasWildcard(attributes)) {
            return [];
        }

        return variations.filter(function (variation) {
            return !isSameVariation(variation, currentVariation) && combinationCovers(attributes, variation.attributes);
        });
    }

    function overlappingVariationsFor(attributes, currentVariation) {
        return variations.filter(function (variation) {
            return !isSameVariation(variation, currentVariation) && combinationsOverlap(attributes, variation.attributes);
        });
    }

    function variationWithinConfiguredDomain(variation) {
        const allowed = configuredAttributeSlugMap();

        return Object.keys(variation.attributes).every(function (taxonomy) {
            const value = variation.attributes[taxonomy] || '';

            return allowed[taxonomy] && (!value || allowed[taxonomy].indexOf(value) !== -1);
        });
    }

    function configuredAttributeSlugMap() {
        const allowed = {};

        configuredAttributeGroups().forEach(function (group) {
            group.forEach(function (term) {
                if (!allowed[term.taxonomy]) {
                    allowed[term.taxonomy] = [];
                }

                allowed[term.taxonomy].push(term.slug);
            });
        });

        return allowed;
    }

    function isSameVariation(first, second) {
        if (first === second) {
            return true;
        }

        if (first.id && second.id && first.id === second.id) {
            return true;
        }

        return Boolean(first.client_uid && second.client_uid && first.client_uid === second.client_uid);
    }

    function openSyncModal(plan) {
        openVariationModal({
            title: 'Actualizar variaciones',
            message: 'Algunos cambios eliminaran variaciones existentes.',
            detail: 'Se eliminaran ' + plan.remove.length + ' variaciones que ya no corresponden a los atributos seleccionados.',
            items: plan.remove,
            confirmText: 'Confirmar cambios',
            variant: 'warning',
            onConfirm: function () {
                applyVariationSyncPlan(plan);
            }
        });
    }

    function openReplacementModal(items) {
        openVariationModal({
            title: 'Reemplazar variaciones',
            message: 'Esta combinacion reemplazara variaciones existentes.',
            items: items,
            confirmText: 'Reemplazar variaciones',
            variant: 'danger',
            onCancel: cancelPendingSelectChange,
            onConfirm: confirmPendingSelectChange
        });
    }

    function openVariationModal(options) {
        modal.open({
            title: options.title,
            message: options.message,
            detail: options.detail || '',
            items: (options.items || []).map(function (variation) {
                return variationLabel(variation);
            }),
            confirmText: options.confirmText,
            variant: options.variant,
            onCancel: options.onCancel,
            onConfirm: options.onConfirm
        });
    }

    function cancelPendingSelectChange() {
        if (!pendingSelectChange) {
            return;
        }

        pendingSelectChange.variation.attributes = pendingSelectChange.previousAttributes;
        pendingSelectChange.select.value = pendingSelectChange.previousValue;
        pendingSelectChange = null;
    }

    function confirmPendingSelectChange() {
        if (!pendingSelectChange) {
            return;
        }

        removeVariations(pendingSelectChange.remove);
        applyVariationAttributeChange(
            pendingSelectChange.variation,
            pendingSelectChange.taxonomy,
            pendingSelectChange.value,
            pendingSelectChange.title,
            pendingSelectChange.wrap
        );
        pendingSelectChange = null;
        renderVariations();
        updateCombinationCount();
        updateGenerateActionState();
        renderDeletedVariationInputs();
    }

    function applyVariationAttributeChange(variation, taxonomy, value, title, wrap) {
        variation.attributes[taxonomy] = value;
        title.textContent = variationLabel(variation);
        setStatus('', false);
        refreshVariationAttributeOptions(wrap, variation);
        updateGenerateActionState();
    }

    function createAdminModal() {
        const root = document.createElement('div');
        const dialog = document.createElement('div');
        const title = document.createElement('h2');
        const message = document.createElement('p');
        const detail = document.createElement('p');
        const list = document.createElement('ul');
        const actions = document.createElement('div');
        const cancel = document.createElement('button');
        const confirm = document.createElement('button');
        const titleId = 'sultana-admin-modal-title-' + Date.now();
        let previousFocus = null;
        let currentOptions = {};

        root.className = 'sultana-admin-modal';
        root.hidden = true;
        dialog.className = 'sultana-admin-modal__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', titleId);
        title.id = titleId;
        title.className = 'sultana-admin-modal__title';
        message.className = 'sultana-admin-modal__message';
        detail.className = 'sultana-admin-modal__detail';
        list.className = 'sultana-admin-modal__list';
        actions.className = 'sultana-admin-modal__actions';
        cancel.type = 'button';
        cancel.className = 'sultana-admin-muted-action';
        cancel.textContent = 'Cancelar';
        confirm.type = 'button';

        actions.appendChild(cancel);
        actions.appendChild(confirm);
        dialog.appendChild(title);
        dialog.appendChild(message);
        dialog.appendChild(detail);
        dialog.appendChild(list);
        dialog.appendChild(actions);
        root.appendChild(dialog);
        document.body.appendChild(root);

        cancel.addEventListener('click', function () {
            close(false);
        });

        confirm.addEventListener('click', function () {
            close(true);
        });

        root.addEventListener('click', function (event) {
            if (event.target === root) {
                close(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (root.hidden || 'Escape' !== event.key) {
                return;
            }

            event.preventDefault();
            close(false);
        });

        function open(options) {
            currentOptions = options || {};
            previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            title.textContent = currentOptions.title || '';
            message.textContent = currentOptions.message || '';
            detail.textContent = currentOptions.detail || '';
            detail.hidden = !currentOptions.detail;
            confirm.textContent = currentOptions.confirmText || 'Confirmar';
            confirm.className = 'sultana-admin-modal__confirm sultana-admin-modal__confirm--' + (currentOptions.variant || 'warning');
            renderModalList(currentOptions.items || []);
            root.hidden = false;
            confirm.focus();
        }

        function renderModalList(items) {
            list.innerHTML = '';
            list.hidden = !items.length;

            items.slice(0, 6).forEach(function (item) {
                const row = document.createElement('li');
                row.textContent = item;
                list.appendChild(row);
            });

            if (items.length > 6) {
                const more = document.createElement('li');
                more.textContent = '+ ' + (items.length - 6) + ' variaciones mas';
                list.appendChild(more);
            }
        }

        function close(confirmed) {
            const callback = confirmed ? currentOptions.onConfirm : currentOptions.onCancel;
            root.hidden = true;
            currentOptions = {};

            if ('function' === typeof callback) {
                callback();
            }

            if (previousFocus && 'function' === typeof previousFocus.focus) {
                previousFocus.focus();
            }
        }

        return {
            open: open
        };
    }

    function setStatus(message, isError) {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('is-error', Boolean(isError));
    }

    function updateSubmitState() {
        const submit = editor.closest('form') ? editor.closest('form').querySelector('button[type="submit"]') : null;

        if (submit) {
            submit.disabled = uploadCounter > 0;
        }
    }

    function setVariationPanelState(toggle, panel, isOpen) {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        panel.hidden = !isOpen;

        panel.querySelectorAll('[data-sultana-required]').forEach(function (input) {
            input.required = isOpen && '1' === input.dataset.sultanaRequired;
        });
    }

    function closeOpenVariationPanels(currentToggle) {
        if (!variationsRoot) {
            return;
        }

        variationsRoot.querySelectorAll('.sultana-admin-variation-card__toggle[aria-expanded="true"]').forEach(function (toggle) {
            if (toggle === currentToggle) {
                return;
            }

            const panelId = toggle.getAttribute('aria-controls');
            const panel = panelId ? document.getElementById(panelId) : null;

            if (panel) {
                setVariationPanelState(toggle, panel, false);
            }
        });
    }

    function appendIcon(target, iconName) {
        if (!iconName || !icons[iconName]) {
            return;
        }

        const icon = document.createElement('span');
        icon.className = 'sultana-admin-icon';
        icon.style.setProperty('--sultana-admin-icon-url', 'url("' + icons[iconName] + '")');
        icon.setAttribute('aria-hidden', 'true');
        target.appendChild(icon);
    }
}());
