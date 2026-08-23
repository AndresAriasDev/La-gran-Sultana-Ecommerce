(function () {
    var setupPasswordToggles = function () {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            var field = button.closest('.sultana-admin-login__field');
            var input = field ? field.querySelector('input[type="password"], input[type="text"]') : null;
            var showLabel = 'Mostrar contrasena';
            var hideLabel = 'Ocultar contrasena';
            var updateVisibility = function () {
                if (!input || !field) {
                    return;
                }

                if (input.value) {
                    field.classList.add('has-value');
                    return;
                }

                field.classList.remove('has-value');
                input.type = 'password';
                button.setAttribute('aria-label', showLabel);
            };

            if (!input) {
                return;
            }

            button.addEventListener('click', function () {
                if (!input.value) {
                    return;
                }

                var isPassword = input.type === 'password';

                input.type = isPassword ? 'text' : 'password';
                button.setAttribute('aria-label', isPassword ? hideLabel : showLabel);
            });

            input.addEventListener('input', updateVisibility);
            input.addEventListener('change', updateVisibility);

            updateVisibility();
            window.setTimeout(updateVisibility, 120);
            window.setTimeout(updateVisibility, 600);
        });
    };

    var setupSubmitLoading = function () {
        document.querySelectorAll('[data-sultana-admin-login-form]').forEach(function (form) {
            var submitButton = form.querySelector('[data-login-submit]');

            if (!submitButton) {
                return;
            }

            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }

                var text = submitButton.querySelector('.sultana-admin-login__submit-text');
                var loadingText = submitButton.dataset.loadingText || '';

                if (text && loadingText) {
                    text.textContent = loadingText;
                    submitButton.classList.add('is-loading-with-text');
                }

                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
                submitButton.classList.add('is-loading');
            });
        });
    };

    var setupCooldowns = function () {
        document.querySelectorAll('[data-cooldown-seconds]').forEach(function (button) {
            var seconds = parseInt(button.dataset.cooldownSeconds || '0', 10);
            var label = button.dataset.cooldownLabel || button.textContent.trim();
            var text = button.querySelector('.sultana-admin-login__submit-text');

            if (!seconds || !text) {
                return;
            }

            var remaining = seconds;
            var update = function () {
                text.textContent = label + ' (' + remaining + 's)';
            };

            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            update();

            var timer = window.setInterval(function () {
                remaining -= 1;

                if (remaining > 0) {
                    update();
                    return;
                }

                window.clearInterval(timer);
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                text.textContent = label;
            }, 1000);
        });
    };

    setupPasswordToggles();
    setupSubmitLoading();
    setupCooldowns();
}());
