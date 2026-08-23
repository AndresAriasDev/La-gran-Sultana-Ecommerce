(function () {
    var form = document.querySelector('[data-sultana-admin-login-form]');

    if (!form) {
        return;
    }

    var submitButton = form.querySelector('[data-login-submit]');

    if (!submitButton) {
        return;
    }

    form.addEventListener('submit', function () {
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.classList.add('is-loading');
    });
}());
