(function () {
  const fieldAliases = {
    departamento: "billing_state",
    municipio: "billing_city",
    telefono: "billing_phone",
    "teléfono": "billing_phone",
    nombres: "billing_first_name",
    nombre: "billing_first_name",
    apellidos: "billing_last_name",
    apellido: "billing_last_name",
    direccion: "billing_address_1",
    "dirección": "billing_address_1",
  };

  const setupAccountNotices = function () {
    document
      .querySelectorAll(".woocommerce-message, .woocommerce-error, .woocommerce-info")
      .forEach(function (notice) {
        if (notice.classList.contains("woocommerce-error") && document.querySelector(".ve-address-form")) {
          setupAddressErrorNotice(notice);
          return;
        }

        window.setTimeout(function () {
          notice.classList.add("is-dismissing");

          window.setTimeout(function () {
            notice.remove();
          }, 260);
        }, 5200);
      });
  };

  const normalizeText = function (value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  };

  const getErrorMessages = function (notice) {
    const items = Array.from(notice.querySelectorAll("li"));

    if (items.length) {
      return items
        .map(function (item) {
          return item.textContent.trim();
        })
        .filter(Boolean);
    }

    const text = notice.textContent.trim();

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

  const markAddressErrorFields = function (messages) {
    const combined = normalizeText(messages.join(" "));

    Object.keys(fieldAliases).forEach(function (label) {
      if (combined.includes(label)) {
        markFieldError(fieldAliases[label]);
      }
    });

    document.querySelectorAll(".ve-address-form [required]").forEach(function (field) {
      if (!String(field.value || "").trim()) {
        markFieldError(field.id);
      }
    });
  };

  const setupAddressErrorNotice = function (notice) {
    const messages = getErrorMessages(notice);
    let currentIndex = 0;

    if (!messages.length) {
      return;
    }

    markAddressErrorFields(messages);
    notice.classList.add("ve-account-error-queue");
    notice.innerHTML = "";

    const message = document.createElement("span");
    message.className = "ve-account-error-queue__message";

    const closeButton = document.createElement("button");
    closeButton.className = "ve-account-error-queue__close";
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

    notice.appendChild(message);
    notice.appendChild(closeButton);
    showCurrentMessage();
  };

  const setupAccountFormLoaders = function () {
    document
      .querySelectorAll(".woocommerce-EditAccountForm, .ve-address-form")
      .forEach(function (form) {
        form.addEventListener("submit", function () {
          const submitButton = form.querySelector(".ve-account-form__submit");

          if (!submitButton) {
            return;
          }

          submitButton.classList.add("is-loading");
          submitButton.setAttribute("aria-disabled", "true");
        });
      });
  };

  const showWishlistFeedback = function (message, type, options) {
    const wrapper = document.querySelector("[data-wishlist-feedback]");

    if (!wrapper || !message) {
      return;
    }

    wrapper.innerHTML = "";

    const notice = document.createElement("div");
    const messageWrap = document.createElement("span");
    const closeButton = document.createElement("button");

    notice.className = (type === "success" ? "woocommerce-message" : "woocommerce-error") + " ve-account-error-queue";
    notice.setAttribute("role", type === "success" ? "status" : "alert");

    messageWrap.className = "ve-account-error-queue__message";
    messageWrap.textContent = message;

    if (options && options.cartUrl) {
      notice.classList.add("is-clickable");
      notice.dataset.cartUrl = options.cartUrl;

      notice.addEventListener("click", function (event) {
        if (event.target.closest(".ve-account-error-queue__close")) {
          return;
        }

        window.location.href = options.cartUrl;
      });
    }

    closeButton.className = "ve-account-error-queue__close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Cerrar aviso");
    closeButton.innerHTML = '<span aria-hidden="true">&times;</span>';

    closeButton.addEventListener("click", function () {
      notice.classList.add("is-dismissing");

      window.setTimeout(function () {
        notice.remove();
      }, 260);
    });

    notice.appendChild(messageWrap);
    notice.appendChild(closeButton);
    wrapper.appendChild(notice);

    window.requestAnimationFrame(function () {
      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    });
  };

  const getWishlistCurrentPage = function () {
    const currentUrl = new URL(window.location.href);

    return currentUrl.searchParams.get("wishlist_page") || "1";
  };

  const updateWishlistContent = function (data) {
    const content = document.querySelector("[data-wishlist-content]");

    if (!content || !data || typeof data.content_html !== "string") {
      return false;
    }

    content.innerHTML = data.content_html;

    if (data.page_url && window.history && window.history.replaceState) {
      const nextUrl = new URL(data.page_url, window.location.href);

      if (nextUrl.href !== window.location.href) {
        window.history.replaceState(null, "", nextUrl.href);
      }
    }

    return true;
  };

  const setupWishlistAddToCart = function () {
    document.addEventListener("click", function (event) {
      const button = event.target.closest("[data-wishlist-add-to-cart]");

        if (!button || !window.sultanaStorefront || button.disabled) {
          return;
        }

        const key = button.dataset.wishlistAddToCart || "";
        const card = button.closest("[data-wishlist-item]");
        const title = card ? card.querySelector(".ve-wishlist-card__title") : null;
        const productName = title ? title.textContent.trim() : "";
        const formData = new FormData();

        if (!key) {
          return;
        }

        button.disabled = true;
        button.classList.add("is-loading");

        formData.append("action", "scc_wishlist_add_to_cart");
        formData.append("nonce", window.sultanaStorefront.wishlistNonce || "");
        formData.append("key", key);
        formData.append("wishlist_page", getWishlistCurrentPage());

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
              throw new Error(result.data && result.data.message ? result.data.message : "No pudimos agregar este producto.");
            }

          updateWishlistContent(result.data);

            if (window.sultanaStorefrontUpdateWishlistCount && result.data && typeof result.data.wishlist_count !== "undefined") {
              window.sultanaStorefrontUpdateWishlistCount(result.data.wishlist_count);
            }

            if (window.sultanaStorefrontUpdateCartCount && result.data && typeof result.data.cart_count !== "undefined") {
              window.sultanaStorefrontUpdateCartCount(result.data.cart_count);
            }

            showWishlistFeedback(
              productName ? '"' + productName + '" se ha añadido a tu carrito.' : result.data.message || "Producto agregado al carrito.",
              "success",
              {
                cartUrl: window.sultanaStorefront.cartUrl || "",
              }
            );
          })
          .catch(function (error) {
            if (button.isConnected) {
              button.disabled = false;
              button.classList.remove("is-loading");
            }

            showWishlistFeedback(error.message, "error");
          });
    });
  };

  const setupWishlistRemovals = function () {
    document.addEventListener("click", function (event) {
      const button = event.target.closest("[data-wishlist-remove]");

        if (!button || !window.sultanaStorefront || button.disabled) {
          return;
        }

        const key = button.dataset.wishlistRemove || "";
      const formData = new FormData();

        if (!key) {
          return;
        }

        button.disabled = true;
        button.classList.add("is-loading");

        formData.append("action", "scc_remove_wishlist_item");
        formData.append("nonce", window.sultanaStorefront.wishlistNonce || "");
        formData.append("key", key);
        formData.append("wishlist_page", getWishlistCurrentPage());

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
              throw new Error(result.data && result.data.message ? result.data.message : "No pudimos eliminar este producto.");
            }

          updateWishlistContent(result.data);

            if (window.sultanaStorefrontUpdateWishlistCount && result.data && typeof result.data.count !== "undefined") {
              window.sultanaStorefrontUpdateWishlistCount(result.data.count);
            }

            if (window.sultanaStorefrontShowToast) {
              window.sultanaStorefrontShowToast(result.data.message, "success");
            }
          })
          .catch(function (error) {
            if (button.isConnected) {
              button.disabled = false;
              button.classList.remove("is-loading");
            }

            if (window.sultanaStorefrontShowToast) {
              window.sultanaStorefrontShowToast(error.message, "error");
            }
          });
    });
  };

  const setupProfileAvatarUpload = function () {
    document.querySelectorAll("[data-profile-avatar]").forEach(function (wrapper) {
      const button = wrapper.querySelector("[data-profile-avatar-button]");
      const input = wrapper.querySelector("[data-profile-avatar-input]");
      const avatar = wrapper.querySelector(".ve-account-nav__avatar");
      let isUploading = false;

      if (!button || !input || !avatar || !window.sultanaStorefront || !window.sultanaStorefront.ajaxUrl) {
        return;
      }

      const showToast = function (message, type) {
        if (window.sultanaStorefrontShowToast && message) {
          window.sultanaStorefrontShowToast(message, type || "success");
        }
      };

      const getValidAvatarUrl = function (value) {
        if (typeof value !== "string" || !value.trim()) {
          return "";
        }

        try {
          return new URL(value, window.location.origin).toString();
        } catch (error) {
          return "";
        }
      };

      const updateAvatarImage = function (avatarUrl) {
        const imageUrl = getValidAvatarUrl(avatarUrl);
        let image = avatar.querySelector(".ve-account-nav__avatar-image");

        if (!imageUrl) {
          return false;
        }

        if (!image) {
          image = document.createElement("img");
          image.className = "ve-account-nav__avatar-image";
          image.alt = "";
          avatar.replaceChildren(image);
        }

        image.removeAttribute("srcset");
        image.removeAttribute("sizes");
        image.src = imageUrl;

        return true;
      };

      const preloadAndUpdateAvatarImage = function (avatarUrl) {
        const imageUrl = getValidAvatarUrl(avatarUrl);

        if (!imageUrl) {
          return Promise.resolve(false);
        }

        return new Promise(function (resolve) {
          const preload = new Image();

          preload.onload = function () {
            resolve(updateAvatarImage(imageUrl));
          };

          preload.onerror = function () {
            resolve(false);
          };

          preload.src = imageUrl;
        });
      };

      const setLoading = function (loading) {
        isUploading = loading;
        wrapper.classList.toggle("is-loading", loading);
        wrapper.setAttribute("aria-busy", loading ? "true" : "false");
        button.disabled = loading;
      };

      const clearInput = function () {
        input.value = "";
      };

      const validateFile = function (file) {
        const allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/avif"];
        const allowedExtensions = [".jpg", ".jpeg", ".jfif", ".png", ".webp", ".avif"];
        const fileName = file && typeof file.name === "string" ? file.name.toLowerCase() : "";
        const hasAllowedExtension = allowedExtensions.some(function (extension) {
          return fileName.endsWith(extension);
        });

        if (!file) {
          return "Selecciona una imagen para subir.";
        }

        if ((file.type && !allowedTypes.includes(file.type)) || (!file.type && !hasAllowedExtension)) {
          return "Selecciona una imagen JPG, PNG, WebP o AVIF valida.";
        }

        if (file.size <= 0 || file.size > 10 * 1024 * 1024) {
          return "La imagen no puede superar los 10 MB.";
        }

        return "";
      };

      button.addEventListener("click", function () {
        if (isUploading) {
          return;
        }

        input.click();
      });

      input.addEventListener("change", function () {
        const file = input.files && input.files[0] ? input.files[0] : null;
        const validationMessage = validateFile(file);
        const formData = new FormData();

        if (validationMessage) {
          showToast(validationMessage, "error");
          clearInput();
          return;
        }

        setLoading(true);
        formData.append("action", "scc_profile_avatar_upload");
        formData.append("nonce", input.dataset.profileAvatarNonce || "");
        formData.append("avatar", file);

        fetch(window.sultanaStorefront.ajaxUrl, {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (result) {
            const data = result && result.data ? result.data : {};

            if (!result || !result.success) {
              throw new Error(data.message || "No pudimos actualizar tu foto de perfil.");
            }

            return preloadAndUpdateAvatarImage(data.avatar_url).then(function () {
              showToast(data.message || "Foto de perfil actualizada.", "success");
            });
          })
          .catch(function (error) {
            showToast(error.message || "No pudimos actualizar tu foto de perfil.", "error");
          })
          .finally(function () {
            clearInput();
            setLoading(false);
          });
      });
    });
  };

  const setupAccountNavigationLoaders = function () {
    const navList = document.querySelector("[data-account-mobile-nav-list]");
    let loadingLink = null;
    let keyboardLoadingLink = null;

    if (!navList) {
      return;
    }

    const getNavigationUrl = function (link) {
      if (!link || link.hasAttribute("download")) {
        return null;
      }

      const target = (link.getAttribute("target") || "").toLowerCase();

      if (target && target !== "_self") {
        return null;
      }

      try {
        return new URL(link.href, window.location.href);
      } catch (error) {
        return null;
      }
    };

    const isCurrentTabNavigation = function (link) {
      const nextUrl = getNavigationUrl(link);
      const currentUrl = new URL(window.location.href);

      if (!nextUrl || nextUrl.origin !== currentUrl.origin) {
        return false;
      }

      if (nextUrl.pathname === currentUrl.pathname && nextUrl.search === currentUrl.search) {
        return false;
      }

      return nextUrl.href !== currentUrl.href;
    };

    const setLoadingLink = function (link) {
      if (loadingLink && loadingLink !== link) {
        loadingLink.classList.remove("is-loading");
        loadingLink.removeAttribute("aria-busy");
      }

      loadingLink = link;
      loadingLink.classList.add("is-loading");
      loadingLink.setAttribute("aria-busy", "true");
    };

    navList.addEventListener("click", function (event) {
      const link = event.target.closest(".ve-account-nav__list a");

      if (!link || !navList.contains(link)) {
        return;
      }

      if (link.classList.contains("is-loading")) {
        if (keyboardLoadingLink === link && event.detail === 0) {
          keyboardLoadingLink = null;
          return;
        }

        event.preventDefault();
        return;
      }

      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      if (!isCurrentTabNavigation(link)) {
        return;
      }

      setLoadingLink(link);
    });

    navList.addEventListener("keydown", function (event) {
      const link = event.target.closest(".ve-account-nav__list a");

      if (event.key !== "Enter" || !link || !navList.contains(link)) {
        return;
      }

      if (link.classList.contains("is-loading")) {
        event.preventDefault();
        return;
      }

      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !isCurrentTabNavigation(link)) {
        return;
      }

      keyboardLoadingLink = link;
      setLoadingLink(link);
    });
  };

  const setupScopedInfoPopovers = function (settings) {
    const scope = document.querySelector(settings.scopeSelector);
    let activeButton = null;
    let activePopover = null;
    let closeTimer = null;

    if (!scope) {
      return;
    }

    const clearCloseTimer = function () {
      if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
    };

    const closeActivePopover = function () {
      clearCloseTimer();

      if (activeButton) {
        activeButton.setAttribute("aria-expanded", "false");
      }

      if (activePopover) {
        activePopover.style.transform = "";
        activePopover.style.removeProperty(settings.arrowLeftProperty);
        activePopover.style.removeProperty(settings.arrowRightProperty);
        activePopover.hidden = true;
      }

      activeButton = null;
      activePopover = null;
    };

    const scheduleClose = function () {
      clearCloseTimer();

      closeTimer = window.setTimeout(function () {
        closeActivePopover();
      }, 5000);
    };

    const keepPopoverInViewport = function (button, popover) {
      const margin = 12;
      const arrowSize = 13;
      const arrowMargin = 12;
      let offset = 0;

      popover.style.transform = "";
      popover.style.removeProperty(settings.arrowLeftProperty);
      popover.style.removeProperty(settings.arrowRightProperty);

      const rect = popover.getBoundingClientRect();

      if (rect.left < margin) {
        offset = margin - rect.left;
      } else if (rect.right > window.innerWidth - margin) {
        offset = window.innerWidth - margin - rect.right;
      }

      if (offset) {
        popover.style.transform = "translateX(" + offset + "px)";
      }

      const adjustedRect = popover.getBoundingClientRect();
      const buttonRect = button.getBoundingClientRect();
      const buttonCenter = buttonRect.left + buttonRect.width / 2;
      const minLeft = arrowMargin;
      const maxLeft = Math.max(minLeft, adjustedRect.width - arrowSize - arrowMargin);
      const arrowLeft = Math.min(
        maxLeft,
        Math.max(minLeft, buttonCenter - adjustedRect.left - arrowSize / 2)
      );

      popover.style.setProperty(settings.arrowLeftProperty, arrowLeft + "px");
      popover.style.setProperty(settings.arrowRightProperty, "auto");
    };

    const openPopover = function (button, popover) {
      if (activePopover && activePopover !== popover) {
        closeActivePopover();
      }

      activeButton = button;
      activePopover = popover;
      activeButton.setAttribute("aria-expanded", "true");
      activePopover.hidden = false;
      keepPopoverInViewport(activeButton, activePopover);
      scheduleClose();
    };

    document.addEventListener("click", function (event) {
      const button = event.target.closest(settings.toggleSelector);

      if (button && scope.contains(button)) {
        const popoverId = button.getAttribute("aria-controls") || "";
        const popover = popoverId ? document.getElementById(popoverId) : null;
        const isOpen = button.getAttribute("aria-expanded") === "true";

        if (!popover) {
          return;
        }

        event.preventDefault();

        if (isOpen) {
          closeActivePopover();
          return;
        }

        openPopover(button, popover);
        return;
      }

      if (
        activePopover &&
        !activePopover.contains(event.target) &&
        (!activeButton || !activeButton.contains(event.target))
      ) {
        closeActivePopover();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && activePopover) {
        closeActivePopover();
      }
    });

    window.addEventListener("resize", function () {
      if (activeButton && activePopover) {
        keepPopoverInViewport(activeButton, activePopover);
      }
    });
  };

  const setupCouponInfoPopovers = function () {
    setupScopedInfoPopovers({
      scopeSelector: "[data-account-coupons]",
      toggleSelector: "[data-coupon-info-toggle]",
      arrowLeftProperty: "--ve-coupon-popover-arrow-left",
      arrowRightProperty: "--ve-coupon-popover-arrow-right",
    });
  };

  const setupOrderInfoPopovers = function () {
    setupScopedInfoPopovers({
      scopeSelector: "[data-view-order-info]",
      toggleSelector: "[data-order-info-toggle]",
      arrowLeftProperty: "--ve-view-order-popover-arrow-left",
      arrowRightProperty: "--ve-view-order-popover-arrow-right",
    });
  };

  const setupAccountFieldInfoPopovers = function () {
    setupScopedInfoPopovers({
      scopeSelector: ".ve-account-form",
      toggleSelector: "[data-account-field-info-toggle]",
      arrowLeftProperty: "--ve-account-field-popover-arrow-left",
      arrowRightProperty: "--ve-account-field-popover-arrow-right",
    });
  };

  const setupCopyButtons = function () {
    document.querySelectorAll("[data-copy-text]").forEach(function (button) {
      button.addEventListener("click", function () {
        const text = button.dataset.copyText || "";
        const message = button.dataset.copyMessage || "Enlace copiado";
        const promptLabel = button.dataset.copyPrompt || "Copiá este enlace";

        if (!text) {
          return;
        }

        if (window.sultanaStorefrontCopyText) {
          window.sultanaStorefrontCopyText(text, {
            message: message,
            promptLabel: promptLabel,
          }).then(function (copied) {
            if (copied) {
              button.classList.add("is-copied");

              window.setTimeout(function () {
                button.classList.remove("is-copied");
              }, 1800);
            }
          }).catch(function () {});
        }
      });
    });
  };

  const setupOrderStatusFilters = function () {
    document.querySelectorAll("[data-order-status-filter]").forEach(function (filter) {
      const scope = filter.closest(".ve-account-orders");
      const toggle = filter.querySelector("[data-order-status-filter-toggle]");
      const menu = filter.querySelector("[data-order-status-filter-menu]");
      const options = filter.querySelectorAll("[data-order-status-filter-option]");
      const nativeFilter = filter.querySelector("[data-order-status-filter-native]");
      const cards = scope ? Array.from(scope.querySelectorAll("[data-order-status]")) : [];
      const empty = scope ? scope.querySelector("[data-order-status-empty]") : null;

      if (!cards.length || !toggle || !menu || !options.length) {
        return;
      }

      const closeMenu = function () {
        toggle.setAttribute("aria-expanded", "false");
        menu.hidden = true;
      };

      const applyFilter = function (status) {
        let visibleCount = 0;

        cards.forEach(function (card) {
          const shouldShow = status === "all" || card.dataset.orderStatus === status;

          card.hidden = !shouldShow;
          card.style.display = shouldShow ? "" : "none";

          if (shouldShow) {
            visibleCount += 1;
          }
        });

        if (empty) {
          empty.hidden = visibleCount > 0;
        }
      };

      toggle.addEventListener("click", function () {
        const isOpen = toggle.getAttribute("aria-expanded") === "true";

        toggle.setAttribute("aria-expanded", String(!isOpen));
        menu.hidden = isOpen;
      });

      options.forEach(function (option) {
        option.addEventListener("click", function () {
          const status = option.dataset.orderStatusFilterOption || "all";
          const url = option.dataset.orderStatusFilterUrl || "";

          if (url) {
            window.location.href = url;
            return;
          }

          if (nativeFilter) {
            nativeFilter.value = status;
          }

          applyFilter(status);
          closeMenu();
        });
      });

      if (nativeFilter) {
        nativeFilter.addEventListener("change", function () {
          const selectedOption = nativeFilter.options[nativeFilter.selectedIndex];
          const url = selectedOption ? selectedOption.dataset.orderStatusFilterUrl || "" : "";

          if (url) {
            window.location.href = url;
            return;
          }

          applyFilter(nativeFilter.value || "all");
          closeMenu();
        });
      }

      document.addEventListener("click", function (event) {
        if (!filter.contains(event.target)) {
          closeMenu();
        }
      });
    });
  };

  setupAccountNotices();
  setupAccountFormLoaders();
  setupWishlistAddToCart();
  setupWishlistRemovals();
  setupProfileAvatarUpload();
  setupAccountNavigationLoaders();
  setupCouponInfoPopovers();
  setupOrderInfoPopovers();
  setupAccountFieldInfoPopovers();
  setupCopyButtons();
  setupOrderStatusFilters();
})();
