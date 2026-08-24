(function () {
    const config = window.SultanaAdminBanners || {};
    const screen = document.querySelector('[data-sultana-banners-screen]');

    if (!screen || !config.ajaxUrl) {
        return;
    }

    const strings = config.strings || {};
    const titleInput = screen.querySelector('[data-sultana-promotion-title]');
    const destinationType = screen.querySelector('[data-sultana-promotion-destination-type]');
    const destinationFields = Array.from(screen.querySelectorAll('[data-sultana-promotion-destination-field]'));

    setupDestinationFields();
    screen.querySelectorAll('[data-sultana-promotion-image]').forEach(setupImageField);

    function setupImageField(root) {
        const slot = root.getAttribute('data-sultana-promotion-image') || '';
        const input = root.querySelector('[data-sultana-promotion-image-input]');
        const trigger = root.querySelector('[data-sultana-promotion-image-trigger]');
        const remove = root.querySelector('[data-sultana-promotion-image-remove]');
        const hidden = root.querySelector('[data-sultana-promotion-image-id]');
        const preview = root.querySelector('[data-sultana-promotion-image-preview]');
        const meta = root.querySelector('[data-sultana-promotion-image-meta]');
        const status = root.querySelector('[data-sultana-promotion-image-status]');
        const emptyLabel = root.querySelector('[data-sultana-promotion-image-empty-label]');
        let image = parseInitialImage(root);
        let pending = false;

        if (trigger && input) {
            trigger.addEventListener('click', function () {
                if (!pending) {
                    input.click();
                }
            });
        }

        if (input) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0] ? input.files[0] : null;
                input.value = '';

                if (file) {
                    uploadImage(file);
                }
            });
        }

        if (remove) {
            remove.addEventListener('click', function () {
                const removed = image;
                image = {};
                renderImage();

                if (removed && removed.temporary && removed.id) {
                    deleteTemporaryImage(removed.id);
                }
            });
        }

        function uploadImage(file) {
            const formData = new FormData();
            formData.append('action', config.uploadAction);
            formData.append('nonce', config.nonce);
            formData.append('slot', slot);
            formData.append('image', file);
            formData.append('promotion_title', titleInput ? titleInput.value || '' : '');
            formData.append('image_index', slot === 'mobile' ? '2' : '1');

            pending = true;
            setDisabled(true);
            setStatus(strings.uploading || 'Subiendo banner...', false);

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
                        throw new Error((payload && payload.data && payload.data.message) || strings.uploadError || 'No se pudo subir el banner.');
                    }

                    const previous = image;
                    image = normalizeImage(payload.data.image);
                    renderImage();
                    setStatus('', false);

                    if (previous && previous.temporary && previous.id && previous.id !== image.id) {
                        deleteTemporaryImage(previous.id);
                    }
                })
                .catch(function (error) {
                    setStatus(error.message || strings.uploadError || 'No se pudo subir el banner.', true);
                })
                .finally(function () {
                    pending = false;
                    setDisabled(false);
                });
        }

        function deleteTemporaryImage(attachmentId) {
            const formData = new FormData();
            formData.append('action', config.deleteAction);
            formData.append('nonce', config.nonce);
            formData.append('attachment_id', String(attachmentId));

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

        function renderImage() {
            const id = image && image.id ? image.id : 0;

            if (hidden) {
                hidden.value = id ? String(id) : '';
            }

            if (preview) {
                preview.innerHTML = '';

                if (image && image.url) {
                    const img = document.createElement('img');
                    img.src = image.url;
                    img.alt = image.name || '';
                    preview.appendChild(img);
                }
            }

            if (meta) {
                meta.textContent = image && image.id ? imageMeta(image) : '';
            }

            if (remove) {
                remove.disabled = !id;
            }

            root.classList.toggle('has-image', Boolean(id));

            if (emptyLabel) {
                emptyLabel.textContent = id ? 'Cambiar imagen' : 'Subir imagen';
            }

            if (trigger) {
                trigger.setAttribute('aria-label', id ? 'Cambiar imagen' : 'Subir imagen');
            }
        }

        function setDisabled(disabled) {
            if (trigger) {
                trigger.disabled = disabled;
            }

            if (input) {
                input.disabled = disabled;
            }
        }

        function setStatus(message, isError) {
            if (!status) {
                return;
            }

            status.textContent = message;
            status.classList.toggle('is-error', Boolean(isError));
        }
    }

    function setupDestinationFields() {
        if (!destinationType) {
            return;
        }

        const sync = function () {
            const activeType = destinationType.value;

            destinationFields.forEach(function (field) {
                const type = field.getAttribute('data-sultana-promotion-destination-field');
                const isActive = type === activeType;
                field.hidden = !isActive;
                Array.from(field.querySelectorAll('input, select, textarea')).forEach(function (control) {
                    control.disabled = !isActive;
                });
            });
        };

        destinationType.addEventListener('change', sync);
        sync();
    }

    function parseInitialImage(root) {
        try {
            return normalizeImage(JSON.parse(root.getAttribute('data-initial-image') || '{}'));
        } catch (error) {
            return {};
        }
    }

    function normalizeImage(image) {
        return {
            id: parseInt(image.id || image.attachment_id, 10) || 0,
            url: image.url || '',
            name: image.name || '',
            width: parseInt(image.width, 10) || 0,
            height: parseInt(image.height, 10) || 0,
            filesize: parseInt(image.filesize, 10) || 0,
            mime: image.mime || '',
            temporary: Boolean(image.temporary)
        };
    }

    function imageMeta(image) {
        const dimensions = image.width && image.height ? image.width + ' x ' + image.height : '';
        const size = image.filesize ? formatBytes(image.filesize) : '';
        const mime = image.mime || '';

        return [dimensions, size, mime].filter(Boolean).join(' - ');
    }

    function formatBytes(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        if (bytes >= 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }

        return String(bytes) + ' B';
    }
}());
