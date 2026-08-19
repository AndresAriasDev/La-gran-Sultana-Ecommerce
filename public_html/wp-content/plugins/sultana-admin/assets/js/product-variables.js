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
    const strings = config.strings || {};
    const availableAttributes = readJson(editor.getAttribute('data-available-attributes'), []);
    const initialState = readJson(editor.getAttribute('data-initial-state'), {});
    let selectedAttributes = Array.isArray(initialState.attributes) ? normalizeAttributes(initialState.attributes) : [];
    let variations = Array.isArray(initialState.variations) ? normalizeVariations(initialState.variations) : [];
    let attributeCounter = 0;
    let uploadCounter = 0;

    if (!selectedAttributes.length && availableAttributes.length) {
        selectedAttributes.push({ taxonomy: '', term_ids: [] });
    }

    renderAttributes();
    renderVariations();
    updateSubmitState();

    if (addAttributeButton) {
        addAttributeButton.addEventListener('click', function () {
            selectedAttributes.push({ taxonomy: '', term_ids: [] });
            renderAttributes();
        });
    }

    if (generateButton) {
        generateButton.addEventListener('click', function () {
            generateVariations();
        });
    }

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

    function renderAttributes() {
        if (!attributesRoot) {
            return;
        }

        attributesRoot.innerHTML = '';

        selectedAttributes.forEach(function (selected, index) {
            const block = document.createElement('div');
            block.className = 'sultana-admin-variable-attribute';

            const select = document.createElement('select');
            select.name = 'variable_attributes[' + index + '][taxonomy]';
            select.appendChild(option('', strings.selectAttribute || 'Selecciona atributo'));

            availableAttributes.forEach(function (attribute) {
                select.appendChild(option(attribute.taxonomy, attribute.label, selected.taxonomy === attribute.taxonomy));
            });

            select.addEventListener('change', function () {
                selectedAttributes[index] = { taxonomy: select.value, term_ids: [] };
                renderAttributes();
            });

            const terms = document.createElement('div');
            terms.className = 'sultana-admin-variable-terms';
            const attribute = findAttribute(selected.taxonomy);

            if (attribute) {
                attribute.terms.forEach(function (term) {
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'variable_attributes[' + index + '][term_ids][]';
                    checkbox.value = String(term.id);
                    checkbox.checked = selected.term_ids.indexOf(toInt(term.id)) !== -1;
                    checkbox.addEventListener('change', function () {
                        const termId = toInt(term.id);

                        if (checkbox.checked && selected.term_ids.indexOf(termId) === -1) {
                            selected.term_ids.push(termId);
                        }

                        if (!checkbox.checked) {
                            selected.term_ids = selected.term_ids.filter(function (current) {
                                return current !== termId;
                            });
                        }
                    });

                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(term.name));
                    terms.appendChild(label);
                });
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'sultana-admin-muted-action';
            remove.textContent = strings.removeAttribute || 'Quitar atributo';
            remove.addEventListener('click', function () {
                selectedAttributes.splice(index, 1);
                renderAttributes();
            });

            block.appendChild(select);
            block.appendChild(terms);
            block.appendChild(remove);
            attributesRoot.appendChild(block);
        });
    }

    function renderVariations() {
        if (!variationsRoot) {
            return;
        }

        variationsRoot.innerHTML = '';

        if (!variations.length) {
            const empty = document.createElement('p');
            empty.className = 'sultana-admin-field-help';
            empty.textContent = strings.generateFirst || 'Genera variaciones para completar sus datos.';
            variationsRoot.appendChild(empty);
            return;
        }

        variations.forEach(function (variation, index) {
            const card = document.createElement('div');
            card.className = 'sultana-admin-variation-card';

            const title = document.createElement('h3');
            title.textContent = variationLabel(variation);

            const id = hidden('variations[' + index + '][id]', variation.id);
            card.appendChild(title);
            card.appendChild(id);

            Object.keys(variation.attributes).forEach(function (taxonomy) {
                card.appendChild(hidden('variations[' + index + '][attributes][' + taxonomy + ']', variation.attributes[taxonomy]));
            });

            card.appendChild(field('SKU', 'variations[' + index + '][sku]', variation.sku, 'text', false));
            card.appendChild(field('Precio regular', 'variations[' + index + '][regular_price]', variation.regular_price, 'number', true, '0.01'));
            card.appendChild(field('Precio de oferta', 'variations[' + index + '][sale_price]', variation.sale_price, 'number', false, '0.01'));
            card.appendChild(field('Stock', 'variations[' + index + '][stock_quantity]', variation.stock_quantity, 'number', true, '1'));
            card.appendChild(field('Peso (kg)', 'variations[' + index + '][weight]', variation.weight, 'number', true, '0.01', '0.01'));
            card.appendChild(variationImageField(variation, index));
            variationsRoot.appendChild(card);
        });
    }

    function variationImageField(variation, index) {
        const wrap = document.createElement('div');
        wrap.className = 'sultana-admin-variation-image';

        const hiddenInput = hidden('variations[' + index + '][image_id]', variation.image_id);
        const preview = document.createElement('div');
        preview.className = 'sultana-admin-variation-image-preview';

        if (variation.image_url) {
            const image = document.createElement('img');
            image.src = variation.image_url;
            image.alt = '';
            preview.appendChild(image);
        }

        const upload = document.createElement('input');
        upload.type = 'file';
        upload.accept = 'image/jpeg,image/png,image/gif,image/webp';
        upload.addEventListener('change', function () {
            const file = upload.files && upload.files[0];
            upload.value = '';

            if (file) {
                uploadVariationImage(file, variation, hiddenInput, preview);
            }
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'sultana-admin-muted-action';
        remove.textContent = strings.removeImage || 'Quitar imagen';
        remove.addEventListener('click', function () {
            variation.image_id = 0;
            variation.image_url = '';
            hiddenInput.value = '0';
            preview.innerHTML = '';
        });

        wrap.appendChild(document.createTextNode(strings.uploadImage || 'Imagen'));
        wrap.appendChild(hiddenInput);
        wrap.appendChild(preview);
        wrap.appendChild(upload);
        wrap.appendChild(remove);

        return wrap;
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

    function generateVariations() {
        const usable = selectedAttributes.filter(function (attribute) {
            return attribute.taxonomy && attribute.term_ids.length;
        });

        if (!usable.length) {
            setStatus(strings.chooseValues || 'Selecciona valores', true);
            return;
        }

        const groups = usable.map(function (attribute) {
            const available = findAttribute(attribute.taxonomy);

            return attribute.term_ids.map(function (termId) {
                const term = available.terms.find(function (current) {
                    return toInt(current.id) === toInt(termId);
                });

                return {
                    taxonomy: attribute.taxonomy,
                    slug: term ? term.slug : '',
                    label: term ? term.name : ''
                };
            }).filter(function (term) {
                return term.slug;
            });
        });

        const combos = cartesian(groups);
        const existing = {};

        variations.forEach(function (variation) {
            existing[variationKey(variation.attributes)] = variation;
        });

        variations = combos.map(function (combo) {
            const attrs = {};

            combo.forEach(function (term) {
                attrs[term.taxonomy] = term.slug;
            });

            return existing[variationKey(attrs)] || {
                id: 0,
                attributes: attrs,
                sku: '',
                regular_price: '',
                sale_price: '',
                stock_quantity: '',
                weight: '',
                image_id: 0,
                image_url: ''
            };
        });

        setStatus('', false);
        renderVariations();
    }

    function cartesian(groups) {
        return groups.reduce(function (acc, group) {
            const next = [];

            acc.forEach(function (prefix) {
                group.forEach(function (item) {
                    next.push(prefix.concat([item]));
                });
            });

            return next;
        }, [[]]);
    }

    function field(labelText, name, value, type, required, step, min) {
        const label = document.createElement('label');
        const span = document.createElement('span');
        const input = document.createElement('input');

        span.textContent = labelText;
        input.type = type;
        input.name = name;
        input.value = value || '';
        input.required = Boolean(required);

        if ('number' === type) {
            input.min = min || '0';
            input.step = step || '1';
            input.inputMode = 'decimal';
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
        return Object.keys(variation.attributes).map(function (taxonomy) {
            const attribute = findAttribute(taxonomy);
            const term = attribute ? attribute.terms.find(function (current) {
                return current.slug === variation.attributes[taxonomy];
            }) : null;

            return (attribute ? attribute.label : taxonomy) + ': ' + (term ? term.name : variation.attributes[taxonomy]);
        }).join(' / ');
    }

    function variationKey(attributes) {
        return Object.keys(attributes).sort().map(function (taxonomy) {
            return taxonomy + '=' + attributes[taxonomy];
        }).join('|');
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
}());
