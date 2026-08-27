(function () {
  const setupCartNotices = function () {
    document
      .querySelectorAll(".woocommerce-notices-wrapper .woocommerce-message, .woocommerce-notices-wrapper .woocommerce-error, .woocommerce-notices-wrapper .woocommerce-info")
      .forEach(function (notice) {
        if (notice.hasAttribute("data-ve-cart-coupon-notice")) {
          return;
        }

        window.setTimeout(function () {
          const wrapper = notice.closest(".woocommerce-notices-wrapper");

          notice.classList.add("is-dismissing");

          window.setTimeout(function () {
            notice.remove();

            if (wrapper && !wrapper.querySelector(".woocommerce-message, .woocommerce-error, .woocommerce-info")) {
              wrapper.remove();
            }
          }, 260);
        }, 4200);
      });
  };

  const submitCartForm = function (form) {
    const updateButton = form.querySelector('[name="update_cart"]');

    if (!updateButton) {
      return;
    }

    updateButton.disabled = false;
    updateButton.classList.add("is-loading");

    if (form.requestSubmit) {
      form.requestSubmit(updateButton);
      return;
    }

    const hiddenSubmit = document.createElement("input");

    hiddenSubmit.type = "hidden";
    hiddenSubmit.name = updateButton.name;
    hiddenSubmit.value = updateButton.value;
    form.appendChild(hiddenSubmit);
    form.submit();
  };

  const getCartNonce = function () {
    const nonce = document.querySelector('[name="woocommerce-cart-nonce"]');

    return nonce ? nonce.value : "";
  };

  const getCartItemKey = function (input) {
    const item = input ? input.closest("[data-ve-cart-item-key]") : null;

    return item ? item.dataset.veCartItemKey || "" : "";
  };

  const escapeSelectorValue = function (value) {
    if (window.CSS && window.CSS.escape) {
      return window.CSS.escape(value);
    }

    return String(value).replace(/"/g, '\\"');
  };

  const showCartNotice = function (message, type) {
    const page = document.querySelector(".ve-cart-page");
    let wrapper = document.querySelector(".woocommerce-notices-wrapper:not(.ve-cart-coupon-feedback)");

    if (!page || !message) {
      return;
    }

    if (!wrapper) {
      wrapper = document.createElement("div");
      wrapper.className = "woocommerce-notices-wrapper";
      page.insertAdjacentElement("beforebegin", wrapper);
    }

    wrapper.innerHTML = "";

    const notice = document.createElement("div");
    notice.className = type === "success" ? "woocommerce-message" : "woocommerce-error";
    notice.setAttribute("role", type === "success" ? "status" : "alert");
    notice.textContent = message;
    wrapper.appendChild(notice);
    setupCartNotices();
  };

  const showCartCouponNotice = function (message, type) {
    const wrapper = document.querySelector("[data-ve-cart-coupon-feedback]");

    if (!wrapper || !message) {
      showCartNotice(message, type);
      return;
    }

    wrapper.innerHTML = "";

    const notice = document.createElement("div");
    const messageWrap = document.createElement("span");
    const closeButton = document.createElement("button");

    notice.className = (type === "success" ? "woocommerce-message" : "woocommerce-error") + " ve-cart-coupon-notice";
    notice.setAttribute("role", type === "success" ? "status" : "alert");
    notice.setAttribute("data-ve-cart-coupon-notice", "");

    messageWrap.className = "ve-cart-coupon-notice__message";
    messageWrap.textContent = message;

    closeButton.className = "ve-cart-coupon-notice__close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Cerrar aviso");
    closeButton.innerHTML = '<span aria-hidden="true">&times;</span>';

    closeButton.addEventListener("click", function () {
      notice.classList.add("is-dismissing");

      window.setTimeout(function () {
        notice.remove();

        if (!wrapper.querySelector(".woocommerce-message, .woocommerce-error, .woocommerce-info")) {
          wrapper.innerHTML = "";
        }
      }, 260);
    });

    notice.appendChild(messageWrap);
    notice.appendChild(closeButton);
    wrapper.appendChild(notice);
  };

  const clearCartCouponNotice = function () {
    const wrapper = document.querySelector("[data-ve-cart-coupon-feedback]");

    if (wrapper) {
      wrapper.innerHTML = "";
    }
  };

  const scrollToCouponNotice = function (notice) {
    if (!notice || !window.matchMedia("(max-width: 768px)").matches) {
      return;
    }

    const header = document.querySelector(".site-header");
    const headerHeight = header ? header.getBoundingClientRect().height : 0;
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const noticeTop = notice.getBoundingClientRect().top + window.scrollY;
    const targetTop = Math.max(0, noticeTop - headerHeight - 16);

    window.scrollTo({
      top: targetTop,
      behavior: prefersReducedMotion ? "auto" : "smooth",
    });
  };

  const scrollToCurrentCouponNotice = function () {
    const wrapper = document.querySelector("[data-ve-cart-coupon-feedback]");
    const notice = wrapper
      ? wrapper.querySelector(".woocommerce-message, .woocommerce-error, .woocommerce-info")
      : null;

    scrollToCouponNotice(notice);
  };

  const replaceCartPage = function (html) {
    const currentPage = document.querySelector(".ve-cart-page");
    const template = document.createElement("template");

    if (!currentPage || !html) {
      return false;
    }

    template.innerHTML = html.trim();

    const nextPage = template.content.querySelector(".ve-cart-page");

    if (!nextPage) {
      return false;
    }

    currentPage.replaceWith(nextPage);
    setupCartNotices();
    setupCartImageSkeletons(nextPage);
    setupCartRecommendationsCarousel();
    setupCartLoaders();

    return true;
  };

  const updateHeaderCartCount = function (count) {
    if (window.sultanaStorefrontUpdateCartCount) {
      window.sultanaStorefrontUpdateCartCount(count);
    }
  };

  const applyServerState = function (data) {
    if (!data) {
      return;
    }

    if (data.fragments && data.fragments.cart_page) {
      replaceCartPage(data.fragments.cart_page);
    }

    if (typeof data.cart_count !== "undefined") {
      updateHeaderCartCount(data.cart_count);
    }
  };

  const markCartItemLoading = function (key, isLoading) {
    const item = document.querySelector('[data-ve-cart-item-key="' + escapeSelectorValue(key) + '"]');

    if (!item) {
      return;
    }

    item.classList.toggle("is-updating", isLoading);
    item.setAttribute("aria-busy", isLoading ? "true" : "false");
  };

  const setupQuantityControls = function () {
    const lineStates = new Map();

    const markLineLoading = function (key, isLoading) {
      markCartItemLoading(key, isLoading);
    };

    const sendQuantityUpdate = function (key, quantity) {
      const state = lineStates.get(key) || {
        inFlight: false,
        pendingQuantity: null,
        sentQuantity: null,
      };

      if (state.inFlight) {
        state.pendingQuantity = quantity;
        lineStates.set(key, state);
        return;
      }

      state.inFlight = true;
      state.pendingQuantity = null;
      state.sentQuantity = quantity;
      lineStates.set(key, state);
      markLineLoading(key, true);

      const formData = new FormData();
      formData.append("action", "variedadesexpress_cart_update_quantity");
      formData.append("nonce", getCartNonce());
      formData.append("cart_item_key", key);
      formData.append("quantity", String(quantity));

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          const data = result.data || {};

          applyServerState(data);

          if (!result.success) {
            throw new Error(data.message || "No pudimos actualizar la cantidad.");
          }

          const latestState = lineStates.get(key) || state;
          const pendingQuantity = latestState.pendingQuantity;

          latestState.inFlight = false;
          latestState.sentQuantity = null;
          latestState.pendingQuantity = null;
          lineStates.set(key, latestState);

          if (pendingQuantity !== null && pendingQuantity !== data.quantity && document.querySelector('[data-ve-cart-item-key="' + escapeSelectorValue(key) + '"]')) {
            sendQuantityUpdate(key, pendingQuantity);
            return;
          }

          lineStates.delete(key);
        })
        .catch(function (error) {
          const latestState = lineStates.get(key) || state;

          latestState.inFlight = false;
          latestState.sentQuantity = null;
          latestState.pendingQuantity = null;
          lineStates.delete(key);
          markLineLoading(key, false);
          showCartNotice(error.message, "error");
        });
    };

    document.addEventListener("click", function (event) {
      const button = event.target.closest("[data-ve-quantity-step]");

      if (!button) {
        return;
      }

      const control = button.closest("[data-ve-quantity]");
      const input = control ? control.querySelector("input.qty") : null;

      if (!input) {
        return;
      }

      const step = Number(button.dataset.veQuantityStep || 0);
      const min = Number(input.getAttribute("min") || 0);
      const maxAttr = input.getAttribute("max");
      const max = maxAttr ? Number(maxAttr) : Infinity;
      const current = Number(input.value || 0);
      const next = Math.max(min, Math.min(max, current + step));

      event.preventDefault();

      if (next === current) {
        return;
      }

      input.value = String(next);
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });

    document.addEventListener("change", function (event) {
      if (!event.target.matches("[data-ve-quantity] input.qty")) {
        return;
      }

      const input = event.target;
      const min = Number(input.getAttribute("min") || 0);
      const maxAttr = input.getAttribute("max");
      const max = maxAttr ? Number(maxAttr) : Infinity;
      const value = Number(input.value || min);
      const next = Math.max(min, Math.min(max, value));

      input.value = String(next);
      const key = getCartItemKey(input);

      if (!key || !window.sultanaStorefront || !window.sultanaStorefront.ajaxUrl) {
        submitCartForm(event.target.closest("[data-ve-cart-form]"));
        return;
      }

      sendQuantityUpdate(key, next);
    });

  };

  const setupCartRemovals = function () {
    const removingItems = new Set();

    document.addEventListener("click", function (event) {
      const removeLink = event.target.closest(".ve-cart-item__remove");

      if (!removeLink) {
        return;
      }

      const item = removeLink.closest("[data-ve-cart-item-key]");
      const key = item ? item.dataset.veCartItemKey || "" : "";

      if (!key || !window.sultanaStorefront || !window.sultanaStorefront.ajaxUrl) {
        return;
      }

      event.preventDefault();

      if (removingItems.has(key)) {
        return;
      }

      removingItems.add(key);
      markCartItemLoading(key, true);
      removeLink.classList.add("is-loading");
      removeLink.setAttribute("aria-disabled", "true");

      const formData = new FormData();
      formData.append("action", "variedadesexpress_cart_remove_item");
      formData.append("nonce", getCartNonce());
      formData.append("cart_item_key", key);

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          const data = result.data || {};

          applyServerState(data);

          if (!result.success) {
            throw new Error(data.message || "No pudimos eliminar este producto.");
          }

          removingItems.delete(key);
        })
        .catch(function (error) {
          removingItems.delete(key);
          markCartItemLoading(key, false);
          removeLink.classList.remove("is-loading");
          removeLink.removeAttribute("aria-disabled");
          showCartNotice(error.message, "error");
        });
    });
  };

  const setupCartCoupons = function () {
    let isApplyingCoupon = false;
    const removingCoupons = new Set();

    const clearCouponButtonLoadingState = function (button) {
      if (!button) {
        return;
      }

      button.disabled = false;
      button.classList.remove("is-loading");
      button.removeAttribute("aria-disabled");
    };

    const setCouponButtonMode = function (button, mode) {
      if (!button) {
        return;
      }

      const isClear = mode === "clear";

      clearCouponButtonLoadingState(button);
      button.dataset.veCouponButtonMode = mode;
      button.classList.toggle("is-clear", isClear);
      button.textContent = isClear ? "Limpiar" : "Aplicar";
      button.setAttribute("aria-label", isClear ? "Limpiar codigo de cupon" : "Aplicar cupon");
    };

    const resetCouponFailureState = function (form) {
      if (!form) {
        return;
      }

      form.removeAttribute("data-ve-failed-coupon-code");
      setCouponButtonMode(form.querySelector('[name="apply_coupon"]'), "apply");
    };

    const markCouponFailureState = function (form, couponCode) {
      if (!form) {
        return;
      }

      form.dataset.veFailedCouponCode = couponCode;
      setCouponButtonMode(form.querySelector('[name="apply_coupon"]'), "clear");
    };

    const markCouponFormLoading = function (form, isLoading) {
      if (!form) {
        return;
      }

      const couponInput = form.querySelector('[name="coupon_code"]');
      const couponButton = form.querySelector('[name="apply_coupon"]');

      if (couponInput) {
        couponInput.disabled = isLoading;
      }

      if (couponButton) {
        couponButton.disabled = isLoading;
        couponButton.classList.toggle("is-loading", isLoading);

        if (isLoading) {
          couponButton.setAttribute("aria-disabled", "true");
        } else {
          couponButton.removeAttribute("aria-disabled");
        }
      }
    };

    document.addEventListener("submit", function (event) {
      const form = event.target.closest("[data-ve-cart-form]");
      const submitter = event.submitter;
      const couponInput = form ? form.querySelector('[name="coupon_code"]') : null;
      const couponButton = form ? form.querySelector('[name="apply_coupon"]') : null;
      const isCouponSubmit =
        (submitter && submitter.name === "apply_coupon") ||
        (couponInput && document.activeElement === couponInput);

      if (!form || !isCouponSubmit) {
        return;
      }

      if (!window.sultanaStorefront || !window.sultanaStorefront.ajaxUrl) {
        return;
      }

      event.preventDefault();

      if (couponButton && couponButton.dataset.veCouponButtonMode === "clear") {
        if (couponInput) {
          couponInput.value = "";
          couponInput.focus();
        }

        clearCartCouponNotice();
        resetCouponFailureState(form);
        return;
      }

      if (isApplyingCoupon) {
        return;
      }

      const couponCode = couponInput ? couponInput.value.trim() : "";

      if (!couponCode) {
        markCouponFormLoading(form, false);
        resetCouponFailureState(form);
        showCartCouponNotice("Ingresa un código de cupón.", "error");
        scrollToCurrentCouponNotice();
        return;
      }

      isApplyingCoupon = true;
      markCouponFormLoading(form, true);

      const formData = new FormData();
      formData.append("action", "variedadesexpress_cart_apply_coupon");
      formData.append("nonce", getCartNonce());
      formData.append("coupon_code", couponCode);

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          const data = result.data || {};

          if (!result.success) {
            const error = new Error(data.message || "No pudimos aplicar este cupón.");
            error.isCouponApplyFailure = true;
            throw error;
          }

          resetCouponFailureState(form);
          applyServerState(data);
          showCartCouponNotice(data.message || "Cupón aplicado correctamente.", "success");
          scrollToCurrentCouponNotice();
        })
        .catch(function (error) {
          showCartCouponNotice(error.message, "error");

          if (error.isCouponApplyFailure && couponInput && couponInput.value.trim() === couponCode) {
            markCouponFailureState(form, couponCode);
          }

          scrollToCurrentCouponNotice();
        })
        .finally(function () {
          isApplyingCoupon = false;
          markCouponFormLoading(form, false);
        });
    });

    document.addEventListener("input", function (event) {
      const couponInput = event.target.closest('[name="coupon_code"]');
      const form = couponInput ? couponInput.closest("[data-ve-cart-form]") : null;
      const failedCode = form ? form.dataset.veFailedCouponCode || "" : "";

      if (!form || !failedCode) {
        return;
      }

      if (couponInput.value.trim() !== failedCode) {
        resetCouponFailureState(form);
      }
    });

    document.addEventListener("click", function (event) {
      const button = event.target.closest("[data-ve-remove-coupon]");

      if (!button) {
        return;
      }

      if (!window.sultanaStorefront || !window.sultanaStorefront.ajaxUrl) {
        return;
      }

      event.preventDefault();

      const couponCode = button.dataset.veRemoveCoupon || "";

      if (!couponCode || removingCoupons.has(couponCode)) {
        return;
      }

      removingCoupons.add(couponCode);
      button.disabled = true;
      button.classList.add("is-loading");
      button.setAttribute("aria-disabled", "true");

      const formData = new FormData();
      formData.append("action", "variedadesexpress_cart_remove_coupon");
      formData.append("nonce", getCartNonce());
      formData.append("coupon_code", couponCode);

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          const data = result.data || {};

          if (!result.success) {
            throw new Error(data.message || "No pudimos quitar este cupón.");
          }

          applyServerState(data);
          removingCoupons.delete(couponCode);
          showCartCouponNotice(data.message || "Cupón eliminado correctamente.", "success");
        })
        .catch(function (error) {
          removingCoupons.delete(couponCode);
          button.disabled = false;
          button.classList.remove("is-loading");
          button.removeAttribute("aria-disabled");
          showCartCouponNotice(error.message, "error");
        });
    });
  };

  const setupCartRecommendationsCarousel = function () {
    document.querySelectorAll("[data-cart-recommendations-track]").forEach(function (track) {
      if (track.dataset.cartRecommendationsReady === "true") {
        return;
      }

      const section = track.closest(".single-product-related");
      const setupHorizontalScroll = window.sultanaStorefrontSetupHorizontalScroll;

      if (!section || !setupHorizontalScroll) {
        return;
      }

      track.dataset.cartRecommendationsReady = "true";
      setupHorizontalScroll(
        track,
        section.querySelector(".single-product-related__arrow--prev"),
        section.querySelector(".single-product-related__arrow--next")
      );
    });
  };

  const setupCartImageSkeletons = function (scope) {
    const root = scope || document;

    root.querySelectorAll(".js-image-skeleton").forEach(function (wrapper) {
      const image = wrapper.querySelector("img");

      if (!image) {
        wrapper.classList.add("has-no-image");
        return;
      }

      if (image.complete && image.naturalWidth > 0) {
        wrapper.classList.add("is-loaded");
        return;
      }

      image.addEventListener(
        "load",
        function () {
          wrapper.classList.add("is-loaded");
        },
        { once: true }
      );

      image.addEventListener(
        "error",
        function () {
          wrapper.classList.add("is-loaded");
        },
        { once: true }
      );
    });
  };

  const setupCartLoaders = function () {
    document.querySelectorAll("[data-ve-cart-form]").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        const submitter = event.submitter;

        if (!submitter) {
          return;
        }

        submitter.classList.add("is-loading");
        submitter.setAttribute("aria-disabled", "true");
      });
    });

    document.querySelectorAll(".ve-cart-summary__checkout").forEach(function (link) {
      link.addEventListener("click", function () {
        if (link.dataset.modalOpen) {
          return;
        }

        link.classList.add("is-loading");
        link.setAttribute("aria-disabled", "true");
      });
    });
  };

  setupCartNotices();
  setupQuantityControls();
  setupCartRemovals();
  setupCartCoupons();
  setupCartImageSkeletons(document);
  setupCartRecommendationsCarousel();
  setupCartLoaders();
})();
