(function () {
    'use strict';

    var modal = null;

    function createModal() {
        var root = document.createElement('div');
        var dialog = document.createElement('div');
        var title = document.createElement('h2');
        var message = document.createElement('p');
        var detail = document.createElement('p');
        var list = document.createElement('ul');
        var actions = document.createElement('div');
        var cancel = document.createElement('button');
        var confirm = document.createElement('button');
        var titleId = 'sultana-admin-modal-title-' + Date.now();
        var descriptionId = 'sultana-admin-modal-description-' + Date.now();
        var detailId = 'sultana-admin-modal-detail-' + Date.now();
        var previousFocus = null;
        var currentOptions = {};

        root.className = 'sultana-admin-modal';
        root.hidden = true;
        dialog.className = 'sultana-admin-modal__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', titleId);
        title.id = titleId;
        title.className = 'sultana-admin-modal__title';
        message.id = descriptionId;
        message.className = 'sultana-admin-modal__message';
        detail.id = detailId;
        detail.className = 'sultana-admin-modal__detail';
        list.className = 'sultana-admin-modal__list';
        actions.className = 'sultana-admin-modal__actions';
        cancel.type = 'button';
        cancel.className = 'sultana-admin-muted-action';
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
            confirm.disabled = true;
            close(true);
        });

        root.addEventListener('click', function (event) {
            if (event.target === root) {
                close(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (root.hidden) {
                return;
            }

            if ('Escape' === event.key) {
                event.preventDefault();
                close(false);
                return;
            }

            if ('Tab' === event.key) {
                trapFocus(event);
            }
        });

        function open(options) {
            var hasTitle = false;
            var messageLabelsDialog = false;
            var describedBy = [];

            currentOptions = options || {};
            previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            title.textContent = currentOptions.title || '';
            title.hidden = !currentOptions.title;
            message.textContent = currentOptions.message || '';
            message.hidden = !currentOptions.message;
            messageLabelsDialog = !currentOptions.title && Boolean(currentOptions.message);
            message.classList.toggle('sultana-admin-modal__message--heading', messageLabelsDialog && Boolean(currentOptions.messageAsTitle));
            detail.textContent = currentOptions.detail || '';
            detail.hidden = !currentOptions.detail;
            cancel.textContent = currentOptions.cancelText || 'Cancelar';
            confirm.textContent = currentOptions.confirmText || 'Confirmar';
            confirm.disabled = false;
            confirm.className = 'sultana-admin-modal__confirm sultana-admin-modal__confirm--' + (currentOptions.variant || 'warning');

            hasTitle = !title.hidden;

            if (hasTitle) {
                dialog.setAttribute('aria-labelledby', titleId);
            } else if (messageLabelsDialog) {
                dialog.setAttribute('aria-labelledby', descriptionId);
            } else {
                dialog.removeAttribute('aria-labelledby');
            }

            if (!message.hidden && !messageLabelsDialog) {
                describedBy.push(descriptionId);
            }

            if (!detail.hidden) {
                describedBy.push(detailId);
            }

            if (describedBy.length) {
                dialog.setAttribute('aria-describedby', describedBy.join(' '));
            } else {
                dialog.removeAttribute('aria-describedby');
            }

            renderModalList(currentOptions.items || []);
            root.hidden = false;
            confirm.focus();
        }

        function renderModalList(items) {
            list.innerHTML = '';
            list.hidden = !items.length;

            items.slice(0, 6).forEach(function (item) {
                var row = document.createElement('li');
                row.textContent = item;
                list.appendChild(row);
            });

            if (items.length > 6) {
                var more = document.createElement('li');
                more.textContent = currentOptions.moreText || '+ ' + (items.length - 6) + ' elementos mas';
                list.appendChild(more);
            }
        }

        function close(confirmed) {
            var callback = confirmed ? currentOptions.onConfirm : currentOptions.onCancel;
            root.hidden = true;
            currentOptions = {};

            if ('function' === typeof callback) {
                callback();
            }

            if (previousFocus && 'function' === typeof previousFocus.focus) {
                previousFocus.focus();
            }
        }

        function trapFocus(event) {
            var focusable = Array.prototype.slice.call(dialog.querySelectorAll('a[href], button:not(:disabled), textarea, input, select, [tabindex]:not([tabindex="-1"])'))
                .filter(function (item) {
                    return !item.hidden && item.offsetParent !== null;
                });

            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        return {
            open: open
        };
    }

    window.SultanaAdminModal = {
        open: function (options) {
            if (!modal) {
                modal = createModal();
            }

            modal.open(options || {});
        }
    };
}());
