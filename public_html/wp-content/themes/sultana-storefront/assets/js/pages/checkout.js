(function () {
  const checkoutForm = document.querySelector("form.checkout");

  if (!checkoutForm) {
    return;
  }

  const fieldAliases = {
    nombres: "billing_first_name",
    nombre: "billing_first_name",
    apellidos: "billing_last_name",
    apellido: "billing_last_name",
    direccion: "billing_address_1",
    departamento: "billing_state",
    municipio: "billing_city",
    telefono: "billing_phone",
    correo: "billing_email",
    email: "billing_email",
  };

  const setLoading = function (isLoading) {
    const button = checkoutForm.querySelector("#place_order");

    if (!button) {
      return;
    }

    button.classList.toggle("is-loading", isLoading);
  };

  const emailStatus = {
    value: "",
    requiresLogin: false,
    requestId: 0,
  };

  const emailField = checkoutForm.querySelector("#billing_email");

  const normalizeText = function (value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  };

  const isValidEmail = function (value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
  };

  const getEmailRow = function () {
    return emailField ? emailField.closest(".form-row") : null;
  };

  const clearCheckoutEmailAccountNotice = function () {
    const row = getEmailRow();

    if (!row) {
      return;
    }

    row.classList.remove("ve-field-has-error", "ve-checkout-email-requires-login");
    row.querySelectorAll(".ve-checkout-email-account-notice").forEach(function (notice) {
      notice.remove();
    });

    if (emailField) {
      emailField.removeAttribute("aria-invalid");
    }
  };

  const openCheckoutLoginModal = function () {
    const modal = document.querySelector('[data-account-modal="account"]');

    if (!modal) {
      return;
    }

    modal.querySelectorAll("[data-account-view-panel]").forEach(function (panel) {
      const isLogin = panel.dataset.accountViewPanel === "login";

      panel.hidden = !isLogin;
      panel.classList.toggle("is-active", isLogin);
    });

    modal.querySelectorAll(".account-form__message").forEach(function (message) {
      message.textContent = "";
      message.classList.remove("is-error", "is-success");
    });

    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("has-modal-open");

    const loginInput = modal.querySelector('[data-account-view-panel="login"] input');

    if (loginInput) {
      window.setTimeout(function () {
        loginInput.focus();
      }, 80);
    }
  };

  const showCheckoutEmailAccountNotice = function () {
    const row = getEmailRow();

    if (!row || row.querySelector(".ve-checkout-email-account-notice")) {
      return;
    }

    row.classList.add("ve-field-has-error", "ve-checkout-email-requires-login");

    if (emailField) {
      emailField.setAttribute("aria-invalid", "true");
    }

    const notice = document.createElement("div");
    notice.className = "ve-checkout-email-account-notice";
    notice.setAttribute("role", "status");

    const text = document.createElement("span");
    text.textContent = "Este correo ya tiene una cuenta. Inicia sesión para continuar.";

    const button = document.createElement("button");
    button.type = "button";
    button.textContent = "Iniciar sesión";
    button.setAttribute("data-modal-open", "account");
    button.setAttribute("data-account-view", "login");
    button.addEventListener("click", openCheckoutLoginModal);

    notice.appendChild(text);
    notice.appendChild(button);
    row.appendChild(notice);
  };

  const checkCheckoutEmailStatus = function () {
    if (!emailField || !window.sultanaStorefront || document.body.classList.contains("logged-in")) {
      return;
    }

    const email = String(emailField.value || "").trim().toLowerCase();

    clearCheckoutEmailAccountNotice();
    emailStatus.value = email;
    emailStatus.requiresLogin = false;

    if (!isValidEmail(email)) {
      return;
    }

    const requestId = emailStatus.requestId + 1;
    const formData = new FormData();

    emailStatus.requestId = requestId;
    formData.append("action", "scc_checkout_email_status");
    formData.append("nonce", window.sultanaStorefront.checkoutEmailStatusNonce || "");
    formData.append("email", email);

    fetch(window.sultanaStorefront.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: formData,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (result) {
        if (requestId !== emailStatus.requestId || email !== String(emailField.value || "").trim().toLowerCase()) {
          return;
        }

        emailStatus.requiresLogin = !!(result && result.success && result.data && result.data.requires_login);

        if (emailStatus.requiresLogin) {
          showCheckoutEmailAccountNotice();
        }
      })
      .catch(function () {
        emailStatus.requiresLogin = false;
      });
  };

  const getErrorMessages = function (notice) {
    const cleanMessage = function (message) {
      const trimmed = message.trim();

      if (normalizeText(trimmed).startsWith("facturacion ")) {
        return trimmed.replace(/^\S+\s+/, "").trim();
      }

      return trimmed;
    };

    const items = Array.from(notice.querySelectorAll("li"));

    if (items.length) {
      return items
        .map(function (item) {
          return cleanMessage(item.textContent.trim());
        })
        .filter(Boolean);
    }

    const text = cleanMessage(notice.textContent.trim());

    return text ? [text] : [];
  };

  const markFieldError = function (fieldId) {
    const field = document.getElementById(fieldId);
    const row = field ? field.closest(".form-row") : null;

    if (!field || !row) {
      return;
    }

    row.classList.add("ve-field-has-error");
    field.setAttribute("aria-invalid", "true");

    field.addEventListener(
      "input",
      function () {
        row.classList.remove("ve-field-has-error");
        field.removeAttribute("aria-invalid");
      },
      { once: true }
    );

    field.addEventListener(
      "change",
      function () {
        row.classList.remove("ve-field-has-error");
        field.removeAttribute("aria-invalid");
      },
      { once: true }
    );
  };

  const clearInlineFieldErrors = function () {
    checkoutForm.querySelectorAll(".form-row").forEach(function (row) {
      row.querySelectorAll(
        ".woocommerce-error, .woocommerce-error li, .error, .woocommerce-input-wrapper ~ small, .woocommerce-input-wrapper ~ span:not(.select2):not(.select2-container)"
      ).forEach(function (error) {
        error.remove();
      });

      Array.from(row.childNodes).forEach(function (node) {
        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
          node.textContent = "";
        }
      });
    });
  };

  const markCheckoutErrorFields = function (messages) {
    const combined = normalizeText(messages.join(" "));

    Object.keys(fieldAliases).forEach(function (label) {
      if (combined.includes(label)) {
        markFieldError(fieldAliases[label]);
      }
    });

    checkoutForm.querySelectorAll("[required]").forEach(function (field) {
      if (!String(field.value || "").trim()) {
        markFieldError(field.id);
      }
    });
  };

  const showCheckoutErrorQueue = function (messages) {
    const checkoutContainer = checkoutForm.closest(".woocommerce") || checkoutForm.parentElement;
    const existingNotice = checkoutContainer ? checkoutContainer.querySelector(".ve-checkout-error-queue") : null;
    let currentIndex = 0;

    if (!checkoutContainer || !messages.length) {
      return null;
    }

    if (existingNotice) {
      existingNotice.remove();
    }

    const notice = document.createElement("div");
    notice.className = "woocommerce-error ve-checkout-error-queue";
    notice.setAttribute("role", "alert");

    const icon = document.createElement("span");
    icon.className = "ve-checkout-error-queue__icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = "!";

    const message = document.createElement("span");
    message.className = "ve-checkout-error-queue__message";

    const closeButton = document.createElement("button");
    closeButton.className = "ve-checkout-error-queue__close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Cerrar aviso");
    closeButton.innerHTML = '<span aria-hidden="true">&times;</span>';

    const showCurrentMessage = function () {
      message.textContent = messages[currentIndex] || "";
    };

    closeButton.addEventListener("click", function () {
      currentIndex += 1;

      if (currentIndex < messages.length) {
        showCurrentMessage();
        return;
      }

      notice.classList.add("is-dismissing");

      window.setTimeout(function () {
        notice.remove();
      }, 260);
    });

    notice.appendChild(icon);
    notice.appendChild(message);
    notice.appendChild(closeButton);
    showCurrentMessage();

    const giftNotice = checkoutContainer.querySelector("[data-checkout-gift-notice]");
    checkoutContainer.insertBefore(notice, giftNotice || checkoutForm);

    return notice;
  };

  const normalizeCheckoutErrors = function () {
    const noticeGroups = Array.from(document.querySelectorAll(".woocommerce-NoticeGroup-checkout, .woocommerce-notices-wrapper"));
    const messages = [];
    const existingQueue = document.querySelector(".ve-checkout-error-queue");

    noticeGroups.forEach(function (group) {
      group.querySelectorAll(".woocommerce-error").forEach(function (notice) {
        messages.push.apply(messages, getErrorMessages(notice));
        notice.remove();
      });

      if (!group.children.length) {
        group.remove();
      }
    });

    clearInlineFieldErrors();
    markCheckoutErrorFields(messages);

    if (!messages.length) {
      return existingQueue;
    }

    return showCheckoutErrorQueue(messages);
  };

  const scrollToCheckoutErrors = function () {
    const notice = normalizeCheckoutErrors();

    if (!notice) {
      return;
    }

    const headerOffset = 92;
    const target = Math.max(0, notice.getBoundingClientRect().top + window.pageYOffset - headerOffset);

    window.scrollTo({
      top: target,
      behavior: "smooth",
    });
  };

  if (emailField && !document.body.classList.contains("logged-in")) {
    emailField.addEventListener("input", function () {
      emailStatus.requiresLogin = false;
      emailStatus.requestId += 1;
      clearCheckoutEmailAccountNotice();
    });

    emailField.addEventListener("blur", checkCheckoutEmailStatus);
    emailField.addEventListener("change", checkCheckoutEmailStatus);
  }

  checkoutForm.addEventListener("submit", function (event) {
    if (emailStatus.requiresLogin) {
      event.preventDefault();
      event.stopPropagation();
      setLoading(false);
      showCheckoutEmailAccountNotice();
      return;
    }

    setLoading(true);
  });

  if (window.jQuery) {
    jQuery(document.body).on("checkout_error", function () {
      setLoading(false);
      window.setTimeout(scrollToCheckoutErrors, 0);
      window.setTimeout(clearInlineFieldErrors, 80);
      window.setTimeout(scrollToCheckoutErrors, 240);
      window.setTimeout(clearInlineFieldErrors, 360);
    });

    jQuery(document.body).on("updated_checkout", function () {
      setLoading(false);
    });
  }

  document.querySelectorAll("[data-checkout-gift-notice-close]").forEach(function (button) {
    button.addEventListener("click", function () {
      const notice = button.closest("[data-checkout-gift-notice]");

      if (!notice) {
        return;
      }

      notice.classList.add("is-dismissing");

      window.setTimeout(function () {
        notice.remove();
      }, 240);
    });
  });
})();
