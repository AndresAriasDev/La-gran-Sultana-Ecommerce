(function () {
    const config = window.SultanaAdminProductImages || {};
    const manager = document.querySelector('[data-sultana-product-images]');

    if (!manager || !config.ajaxUrl) {
        return;
    }

    const input = manager.querySelector('[data-sultana-product-image-input]');
    const trigger = manager.querySelector('[data-sultana-product-image-trigger]');
    const idsInput = manager.querySelector('[data-sultana-product-image-ids]');
    const grid = manager.querySelector('[data-sultana-product-image-grid]');
    const status = manager.querySelector('[data-sultana-product-image-status]');
    const form = manager.closest('form');
    const submitButtons = form ? Array.from(document.querySelectorAll('button[type="submit"][form="' + form.id + '"], #' + form.id + ' button[type="submit"]')) : [];
    const strings = config.strings || {};
    const icons = config.icons || {};
    let images = parseInitialImages();
    let pendingUploads = 0;
    let draggedId = null;

    render();
    syncIds();
    updateSubmitState();

    if (trigger && input) {
        trigger.addEventListener('click', function () {
            input.click();
        });
    }

    if (input) {
        input.addEventListener('change', function () {
            const files = Array.from(input.files || []);
            input.value = '';

            files.forEach(uploadImage);
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (pendingUploads > 0) {
                event.preventDefault();
                setStatus(strings.uploadBlocked || 'Espera a que terminen de subir las imagenes.', true);
            }
        });
    }

    function parseInitialImages() {
        try {
            const parsed = JSON.parse(manager.getAttribute('data-initial-images') || '[]');

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map(normalizeImage)
                .filter(function (image) {
                    return image.id > 0 && image.url;
                });
        } catch (error) {
            return [];
        }
    }

    function normalizeImage(image) {
        return {
            id: parseInt(image.id, 10) || 0,
            url: image.url || '',
            name: image.name || '',
            temporary: Boolean(image.temporary)
        };
    }

    function uploadImage(file) {
        const formData = new FormData();
        formData.append('action', config.uploadAction);
        formData.append('nonce', config.nonce);
        formData.append('image', file);

        pendingUploads += 1;
        setStatus(strings.uploading || 'Subiendo imagenes...', false);
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

                addImage(normalizeImage(payload.data.image));
                setStatus('', false);
            })
            .catch(function (error) {
                setStatus(error.message || strings.uploadError || 'No se pudo subir la imagen.', true);
            })
            .finally(function () {
                pendingUploads = Math.max(0, pendingUploads - 1);
                updateSubmitState();
            });
    }

    function addImage(image) {
        if (!image.id || images.some(function (current) { return current.id === image.id; })) {
            return;
        }

        images.push(image);
        render();
        syncIds();
    }

    function removeImage(imageId) {
        const removed = images.find(function (image) {
            return image.id === imageId;
        });

        images = images.filter(function (image) {
            return image.id !== imageId;
        });

        render();
        syncIds();

        if (removed && removed.temporary) {
            deleteTemporaryImage(imageId);
        }
    }

    function deleteTemporaryImage(imageId) {
        const formData = new FormData();
        formData.append('action', config.deleteAction);
        formData.append('nonce', config.nonce);
        formData.append('attachment_id', String(imageId));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    setStatus(strings.deleteError || 'La imagen se quito de la seleccion, pero no se pudo eliminar el archivo temporal.', true);
                }
            })
            .catch(function () {
                setStatus(strings.deleteError || 'La imagen se quito de la seleccion, pero no se pudo eliminar el archivo temporal.', true);
            });
    }

    function moveImage(fromIndex, toIndex) {
        if (toIndex < 0 || toIndex >= images.length || fromIndex === toIndex) {
            return;
        }

        const moved = images.splice(fromIndex, 1)[0];
        images.splice(toIndex, 0, moved);
        render();
        syncIds();
    }

    function render() {
        if (!grid) {
            return;
        }

        grid.innerHTML = '';

        images.forEach(function (image, index) {
            const item = document.createElement('div');
            item.className = 'sultana-admin-image-item';
            item.draggable = true;
            item.dataset.imageId = String(image.id);

            item.addEventListener('dragstart', function () {
                draggedId = image.id;
                item.classList.add('is-dragging');
            });

            item.addEventListener('dragend', function () {
                draggedId = null;
                item.classList.remove('is-dragging');
            });

            item.addEventListener('dragover', function (event) {
                event.preventDefault();
            });

            item.addEventListener('drop', function (event) {
                event.preventDefault();

                const fromIndex = images.findIndex(function (current) {
                    return current.id === draggedId;
                });

                moveImage(fromIndex, index);
            });

            const badge = document.createElement('span');
            badge.className = 'sultana-admin-image-badge';
            badge.textContent = index === 0 ? (strings.cover || 'Portada') : String(index + 1);

            const imageEl = document.createElement('img');
            imageEl.src = image.url;
            imageEl.alt = image.name || '';

            const remove = actionButton('trash', strings.remove || 'Eliminar imagen', false, function () {
                removeImage(image.id);
            }, 'sultana-admin-image-remove sultana-admin-icon-button sultana-admin-icon-button--danger');

            const controls = document.createElement('div');
            controls.className = 'sultana-admin-image-controls';

            controls.appendChild(actionButton('chevronLeft', strings.moveLeft || 'Mover a la izquierda', index === 0, function () {
                moveImage(index, index - 1);
            }, 'sultana-admin-icon-button'));

            controls.appendChild(actionButton('chevronRight', strings.moveRight || 'Mover a la derecha', index === images.length - 1, function () {
                moveImage(index, index + 1);
            }, 'sultana-admin-icon-button'));

            item.appendChild(imageEl);
            item.appendChild(badge);
            item.appendChild(remove);
            item.appendChild(controls);
            grid.appendChild(item);
        });
    }

    function actionButton(iconName, label, disabled, onClick, className) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className || '';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.disabled = disabled;
        button.addEventListener('click', onClick);

        if (icons[iconName]) {
            const icon = document.createElement('span');
            icon.className = 'sultana-admin-icon';
            icon.style.setProperty('--sultana-admin-icon-url', 'url("' + icons[iconName] + '")');
            icon.setAttribute('aria-hidden', 'true');
            button.appendChild(icon);
        }

        return button;
    }

    function syncIds() {
        if (!idsInput) {
            return;
        }

        idsInput.value = images.map(function (image) {
            return image.id;
        }).join(',');
    }

    function updateSubmitState() {
        submitButtons.forEach(function (button) {
            button.disabled = pendingUploads > 0;
        });

        if (trigger) {
            trigger.disabled = pendingUploads > 0;
        }

        if (input) {
            input.disabled = pendingUploads > 0;
        }
    }

    function setStatus(message, isError) {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('is-error', Boolean(isError));
    }
}());
