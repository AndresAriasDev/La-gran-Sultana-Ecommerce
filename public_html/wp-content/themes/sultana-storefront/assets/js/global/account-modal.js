(function () {
  let pendingAccountLoginRedirect = "";
  let recoveryCooldownTimer = null;

  const clearRecoveryCooldown = function () {
    if (!recoveryCooldownTimer) {
      return;
    }

    window.clearInterval(recoveryCooldownTimer);
    recoveryCooldownTimer = null;
  };

  const setRecoveryInitialState = function (form, shouldClearValue) {
    if (!form) {
      return;
    }

    const modal = form.closest("[data-account-modal]");
    const title = modal ? modal.querySelector("[data-account-recovery-title]") : null;
    const intro = form.querySelector("[data-account-recovery-intro]");
    const fields = form.querySelector("[data-account-recovery-fields]");
    const success = form.querySelector("[data-account-recovery-success]");
    const message = form.querySelector("[data-account-recovery-message]");
    const submitButton = form.querySelector('button[type="submit"]');

    if (shouldClearValue) {
      form.reset();
    }

    if (title) {
      title.textContent = "Recuperar contraseña";
    }

    if (intro) {
      intro.hidden = false;
    }

    if (fields) {
      fields.hidden = false;
    }

    if (success) {
      success.hidden = true;
    }

    if (message) {
      message.textContent = "";
      message.classList.remove("is-error", "is-success");
      message.hidden = false;
    }

    if (submitButton) {
      submitButton.disabled = false;
      submitButton.removeAttribute("aria-disabled");
      submitButton.removeAttribute("aria-busy");
      submitButton.classList.remove("is-loading");
      restoreButtonContent(submitButton, "Enviar enlace");
    }
  };

  const setRecoverySuccessState = function (form) {
    if (!form) {
      return;
    }

    const modal = form.closest("[data-account-modal]");
    const title = modal ? modal.querySelector("[data-account-recovery-title]") : null;
    const intro = form.querySelector("[data-account-recovery-intro]");
    const fields = form.querySelector("[data-account-recovery-fields]");
    const success = form.querySelector("[data-account-recovery-success]");
    const message = form.querySelector("[data-account-recovery-message]");

    if (title) {
      title.textContent = "Revisá tu correo";
    }

    if (intro) {
      intro.hidden = true;
    }

    if (fields) {
      fields.hidden = true;
    }

    if (success) {
      success.hidden = false;
    }

    if (message) {
      message.textContent = "";
      message.classList.remove("is-error", "is-success");
      message.hidden = true;
    }
  };

  const storeRedirectToast = function (message) {
    try {
      window.sessionStorage.setItem(
        window.sultanaStorefrontToastStorageKey || "variedadesExpressReviewToast",
        JSON.stringify({
          type: "success",
          message: message,
        })
      );
    } catch (error) {
      // Ignore storage failures; the redirect should still happen.
    }
  };

  const setupAccountModals = function () {
    const modals = document.querySelectorAll("[data-review-modal], [data-account-modal]");

    if (!modals.length) {
      return;
    }

    const openModal = function (name) {
      const modal =
        document.querySelector('[data-account-modal="' + name + '"]') ||
        document.querySelector('[data-review-modal="' + name + '"]');

      if (!modal) {
        return;
      }

      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("has-modal-open");

      const firstInput = modal.querySelector("input, textarea, button");

      if (firstInput) {
        window.setTimeout(function () {
          firstInput.focus();
        }, 80);
      }
    };

    const prepareReviewForm = function (button) {
      const form = document.querySelector("[data-product-review-form]");

      if (!form || button.dataset.modalOpen !== "review") {
        return;
      }

      const reviewIdField = form.querySelector("[data-review-id-field]");
      const textarea = form.querySelector('textarea[name="comment"]');
      const rating = button.dataset.reviewRating || "";

      form.reset();

      if (reviewIdField) {
        reviewIdField.value = button.dataset.reviewEdit ? button.dataset.reviewId || "" : "";
      }

      if (textarea) {
        textarea.value = button.dataset.reviewEdit ? button.dataset.reviewContent || "" : "";
      }

      if (rating) {
        const ratingInput = form.querySelector('input[name="rating"][value="' + rating + '"]');

        if (ratingInput) {
          ratingInput.checked = true;
        }
      }
    };

    const closeModals = function () {
      modals.forEach(function (modal) {
        const recoveryForm = modal.querySelector("[data-account-recovery-form]");

        if (recoveryForm) {
          clearRecoveryCooldown();
          setRecoveryInitialState(recoveryForm, true);
        }

        modal.setAttribute("aria-hidden", "true");
      });
      pendingAccountLoginRedirect = "";
      document.body.classList.remove("has-modal-open");
    };

    const switchAccountView = function (modal, view) {
      if (!modal || !view) {
        return;
      }

      modal.querySelectorAll("[data-account-view-panel]").forEach(function (panel) {
        const isActive = panel.dataset.accountViewPanel === view;

        panel.hidden = !isActive;
        panel.classList.toggle("is-active", isActive);
      });
    };

    const clearAccountMessages = function (modal) {
      modal.querySelectorAll(".account-form__message").forEach(function (message) {
        message.textContent = "";
        message.classList.remove("is-error", "is-success");
      });
    };

    const resetRecoveryView = function (modal) {
      const form = modal.querySelector("[data-account-recovery-form]");

      clearRecoveryCooldown();
      setRecoveryInitialState(form, true);
    };

    const focusAccountView = function (modal, view) {
      const firstInput = modal.querySelector('[data-account-view-panel="' + view + '"] input');

      if (firstInput) {
        window.setTimeout(function () {
          firstInput.focus();
        }, 60);
      }
    };

    document.querySelectorAll("[data-modal-open]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        const modalName = button.dataset.modalOpen;
        const modal = document.querySelector('[data-account-modal="' + modalName + '"]');

        event.preventDefault();
        prepareReviewForm(button);
        pendingAccountLoginRedirect = button.hasAttribute("data-account-login-redirect")
          ? (window.sultanaStorefront && window.sultanaStorefront.myAccountUrl ? window.sultanaStorefront.myAccountUrl : "")
          : "";
        openModal(modalName);
        switchAccountView(modal, button.dataset.accountView);

        if (modal && button.dataset.accountView === "recovery") {
          resetRecoveryView(modal);
          focusAccountView(modal, "recovery");
        }
      });
    });

    document.querySelectorAll("[data-review-close], [data-modal-close]").forEach(function (button) {
      button.addEventListener("click", closeModals);
    });

    document.querySelectorAll("[data-account-password-recovery]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        const modal = link.closest("[data-account-modal]");

        event.preventDefault();

        if (!modal) {
          return;
        }

        clearAccountMessages(modal);
        resetRecoveryView(modal);
        switchAccountView(modal, "recovery");
        focusAccountView(modal, "recovery");
      });
    });

    document.querySelectorAll("[data-account-view]").forEach(function (button) {
      button.addEventListener("click", function () {
        const modal = button.closest("[data-account-modal]");
        const view = button.dataset.accountView;

        if (!modal || !view) {
          return;
        }

        if (button.closest("[data-account-recovery-form]") && view === "login") {
          clearRecoveryCooldown();
          setRecoveryInitialState(button.closest("[data-account-recovery-form]"), true);
        }

        switchAccountView(modal, view);
        clearAccountMessages(modal);
        focusAccountView(modal, view);
      });
    });

    document.querySelectorAll("[data-password-toggle]").forEach(function (button) {
      button.addEventListener("click", function () {
        const field = button.closest(".account-form__field");
        const input = field ? field.querySelector('input[type="password"], input[type="text"]') : null;

        if (!input) {
          return;
        }

        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
        button.textContent = isPassword ? "Ocultar" : "Ver";
        button.setAttribute("aria-label", isPassword ? "Ocultar contraseña" : "Mostrar contraseña");
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeModals();
      }
    });
  };

  const setupAccountAjaxForm = function (form, config) {
    if (!form || !window.sultanaStorefront) {
      return;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      if (config.cooldownSeconds && recoveryCooldownTimer) {
        return;
      }

      const message = form.querySelector(config.messageSelector);
      const submitButton = form.querySelector('button[type="submit"]');
      const buttonLabel = submitButton ? submitButton.querySelector("[data-button-label]") : null;
      const buttonIcon = submitButton ? submitButton.querySelector(".account-form__button-icon") : null;
      const shouldReloadOnSuccess = config.reloadOnSuccess !== false;
      const formData = new FormData(form);
      let shouldReload = false;

      formData.append("action", config.action);
      formData.append("nonce", config.nonce);

      if (message) {
        message.textContent = "";
        message.classList.remove("is-error", "is-success");
      }

      if (submitButton) {
        submitButton.dataset.defaultText = buttonLabel ? buttonLabel.textContent : submitButton.textContent;
        submitButton.disabled = true;
        submitButton.setAttribute("aria-disabled", "true");
        submitButton.setAttribute("aria-busy", "true");
        submitButton.classList.add("is-loading");

        if (buttonIcon) {
          buttonIcon.dataset.defaultHtml = buttonIcon.innerHTML;
          buttonIcon.innerHTML = '<span class="button-loader" aria-hidden="true"></span>';
        } else {
          submitButton.innerHTML =
            '<span class="button-loader" aria-hidden="true"></span><span class="screen-reader-text">' + config.loadingText + "</span>";
        }

        if (buttonLabel) {
          buttonLabel.textContent = config.loadingText;
        }
      }

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          if (!result.success) {
            throw new Error(result.data && result.data.message ? result.data.message : config.errorMessage);
          }

          if (!shouldReloadOnSuccess) {
            const successMessage =
              result.data && result.data.message ? result.data.message : config.successMessage;

            if (config.recoverySuccessState) {
              setRecoverySuccessState(form);
            } else if (message) {
              message.textContent = successMessage;
              message.classList.add("is-success");
            }

            if (config.cooldownSeconds && submitButton) {
              startRecoveryCooldown(submitButton, config.cooldownSeconds, config.cooldownText || "Volver a enviar enlace");
            }

            return;
          }

          if (config.redirectAfterSuccess && pendingAccountLoginRedirect) {
            shouldReload = true;
            storeRedirectToast("Sesión iniciada correctamente.");
            window.location.href = pendingAccountLoginRedirect;
            return;
          }

          shouldReload = true;
          window.sessionStorage.setItem(
            window.sultanaStorefrontToastStorageKey || "variedadesExpressReviewToast",
            JSON.stringify({
              type: "success",
              message: config.successMessage,
              scrollTop: true,
            })
          );

          window.setTimeout(function () {
            window.location.href = window.location.pathname + window.location.search;
            window.location.reload();
          }, 500);
        })
        .catch(function (error) {
          if (message) {
            message.hidden = false;
            message.textContent = error.message;
            message.classList.add("is-error");
          }
        })
        .finally(function () {
          if (submitButton && !shouldReload) {
            if (config.cooldownSeconds && recoveryCooldownTimer) {
              return;
            }

            submitButton.disabled = false;
            submitButton.removeAttribute("aria-disabled");
            submitButton.removeAttribute("aria-busy");
            submitButton.classList.remove("is-loading");
            restoreButtonContent(submitButton, submitButton.dataset.defaultText || config.defaultText);
          }
        });
    });
  };

  const restoreButtonContent = function (button, text) {
    const label = button.querySelector("[data-button-label]");
    const icon = button.querySelector(".account-form__button-icon");

    if (label) {
      label.textContent = text;
    } else {
      button.textContent = text;
    }

    if (icon && icon.dataset.defaultHtml) {
      icon.innerHTML = icon.dataset.defaultHtml || icon.innerHTML;
    }
  };

  const startRecoveryCooldown = function (button, seconds, text) {
    const label = button.querySelector("[data-button-label]");
    let remaining = seconds;

    clearRecoveryCooldown();

    button.classList.remove("is-loading");
    button.disabled = true;
    button.setAttribute("aria-disabled", "true");
    button.removeAttribute("aria-busy");
    restoreButtonContent(button, text + " (" + remaining + "s)");

    recoveryCooldownTimer = window.setInterval(function () {
      remaining -= 1;

      if (remaining > 0) {
        if (label) {
          label.textContent = text + " (" + remaining + "s)";
        } else {
          button.textContent = text + " (" + remaining + "s)";
        }

        return;
      }

      window.clearInterval(recoveryCooldownTimer);
      recoveryCooldownTimer = null;
      button.disabled = false;
      button.removeAttribute("aria-disabled");
      restoreButtonContent(button, text);
    }, 1000);
  };

  const setupReviewFormLoading = function () {
    const form = document.querySelector("[data-product-review-form]");

    if (!form) {
      return;
    }

    form.addEventListener("submit", function () {
      const submitButton = form.querySelector('button[type="submit"]');

      if (!submitButton) {
        return;
      }

      submitButton.dataset.defaultText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.classList.add("is-loading");
      submitButton.innerHTML =
        '<span class="button-loader" aria-hidden="true"></span><span class="screen-reader-text">Enviando reseña...</span>';
    });
  };

  const setupPasswordResetForm = function () {
    const form = document.querySelector("[data-password-reset-form]");

    if (!form || !window.sultanaStorefront) {
      return;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      const message = form.querySelector("[data-password-reset-message]");
      const submitButton = form.querySelector('button[type="submit"]');
      const buttonLabel = submitButton ? submitButton.querySelector("[data-button-label]") : null;
      const buttonIcon = submitButton ? submitButton.querySelector(".account-form__button-icon") : null;
      const formData = new FormData(form);
      const activePanel = document.querySelector("[data-password-reset-active]");
      const successPanel = document.querySelector("[data-password-reset-success]");

      formData.append("action", "scc_reset_password");
      formData.append("nonce", window.sultanaStorefront.passwordResetCompleteNonce || "");

      if (message) {
        message.textContent = "";
        message.classList.remove("is-error", "is-success");
      }

      if (submitButton) {
        submitButton.dataset.defaultText = buttonLabel ? buttonLabel.textContent : submitButton.textContent;
        submitButton.disabled = true;
        submitButton.setAttribute("aria-disabled", "true");
        submitButton.setAttribute("aria-busy", "true");
        submitButton.classList.add("is-loading");
        if (buttonIcon) {
          buttonIcon.dataset.defaultHtml = buttonIcon.innerHTML;
        }
        if (buttonLabel && buttonIcon) {
          buttonIcon.innerHTML = '<span class="button-loader" aria-hidden="true"></span>';
          buttonLabel.textContent = "Guardando contraseña...";
        } else {
          submitButton.innerHTML =
            '<span class="button-loader" aria-hidden="true"></span><span class="screen-reader-text">Guardando contraseña...</span>';
        }
      }

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          if (!result.success) {
            throw new Error(result.data && result.data.message ? result.data.message : "No pudimos actualizar tu contraseña.");
          }

          if (activePanel) {
            activePanel.remove();
          }

          if (successPanel) {
            successPanel.hidden = false;
            const loginButton = successPanel.querySelector("[data-modal-open]");

            if (loginButton) {
              loginButton.focus();
            }
          }

          if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
          }
        })
        .catch(function (error) {
          if (message) {
            message.textContent = error.message;
            message.classList.add("is-error");
          }

          if (submitButton) {
            submitButton.disabled = false;
            submitButton.removeAttribute("aria-disabled");
            submitButton.removeAttribute("aria-busy");
            submitButton.classList.remove("is-loading");
            restoreButtonContent(submitButton, submitButton.dataset.defaultText || "Guardar nueva contraseña");
          }
        });
    });
  };

  setupAccountModals();
  setupReviewFormLoading();
  setupPasswordResetForm();

  setupAccountAjaxForm(document.querySelector("[data-account-register-form]"), {
    action: "scc_register_account",
    nonce: window.sultanaStorefront ? window.sultanaStorefront.accountNonce : "",
    messageSelector: "[data-account-register-message]",
    loadingText: "Registrando...",
    successMessage: "Registro exitoso",
    errorMessage: "No se pudo crear la cuenta.",
    defaultText: "Crear cuenta",
  });

  setupAccountAjaxForm(document.querySelector("[data-account-login-form]"), {
    action: "scc_login_account",
    nonce: window.sultanaStorefront ? window.sultanaStorefront.loginNonce : "",
    messageSelector: "[data-account-login-message]",
    loadingText: "Iniciando sesión...",
    successMessage: "Sesión iniciada",
    errorMessage: "No se pudo iniciar sesión.",
    defaultText: "Iniciar sesión",
    redirectAfterSuccess: true,
  });
  setupAccountAjaxForm(document.querySelector("[data-account-recovery-form]"), {
    action: "scc_request_password_reset",
    nonce: window.sultanaStorefront ? window.sultanaStorefront.passwordResetNonce : "",
    messageSelector: "[data-account-recovery-message]",
    loadingText: "Enviando enlace...",
    successMessage: "Si existe una cuenta asociada a ese correo, te enviamos un enlace para restablecer tu contraseña.",
    errorMessage: "No pudimos procesar la solicitud.",
    defaultText: "Enviar enlace",
    reloadOnSuccess: false,
    recoverySuccessState: true,
    cooldownSeconds: 30,
    cooldownText: "Volver a enviar enlace",
  });
})();
