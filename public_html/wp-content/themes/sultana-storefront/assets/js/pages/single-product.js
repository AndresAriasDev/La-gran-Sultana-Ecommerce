(function () {
  const setupWooCommerceNotices = function () {
    document
      .querySelectorAll(".woocommerce-notices-wrapper .woocommerce-message, .woocommerce-notices-wrapper .woocommerce-error, .woocommerce-notices-wrapper .woocommerce-info")
      .forEach(function (notice) {
        window.setTimeout(function () {
          const wrapper = notice.closest(".woocommerce-notices-wrapper");

          notice.classList.add("is-dismissing");

          window.setTimeout(function () {
            notice.remove();

            if (wrapper && !wrapper.querySelector(".woocommerce-message, .woocommerce-error, .woocommerce-info")) {
              wrapper.remove();
            }
          }, 260);
        }, 5200);
      });
  };

  const setupSingleProductNotice = function (preferredTarget) {
    if (window.sultanaStorefrontSingleProductNotice) {
      if (preferredTarget && typeof window.sultanaStorefrontSingleProductNotice.mount === "function") {
        window.sultanaStorefrontSingleProductNotice.mount(preferredTarget);
      }

      return window.sultanaStorefrontSingleProductNotice;
    }

    const notice = document.createElement("div");
    let hideTimer = 0;
    let messages = [];
    let currentIndex = 0;
    let actionUrl = "";

    notice.className = "single-product-summary__variation-notice";
    notice.setAttribute("role", "status");
    notice.innerHTML =
      '<span class="single-product-summary__variation-notice-icon" aria-hidden="true">!</span>' +
      '<div class="single-product-summary__variation-notice-content"></div>' +
      '<button class="single-product-summary__variation-notice-close" type="button" aria-label="Cerrar aviso"><span aria-hidden="true">&times;</span></button>';

    const content = notice.querySelector(".single-product-summary__variation-notice-content");
    const closeButton = notice.querySelector(".single-product-summary__variation-notice-close");
    const icon = notice.querySelector(".single-product-summary__variation-notice-icon");

    const mount = function (target) {
      if (!target || notice.parentNode) {
        return;
      }

      target.insertAdjacentElement("beforebegin", notice);
    };

    const hide = function () {
      window.clearTimeout(hideTimer);
      notice.classList.add("is-dismissing");

      hideTimer = window.setTimeout(function () {
        notice.classList.remove("is-visible", "is-dismissing", "is-error", "is-success");
        notice.classList.remove("is-clickable");
        actionUrl = "";

        if (content) {
          content.innerHTML = "";
        }
      }, 220);
    };

    const render = function () {
      if (!content) {
        return;
      }

      content.innerHTML = "";

      const message = document.createElement(actionUrl ? "a" : "span");

      message.className = "single-product-summary__variation-notice-message";
      message.textContent = messages[currentIndex] || "";

      if (actionUrl) {
        message.href = actionUrl;
      }

      content.appendChild(message);
    };

    const show = function (newMessages, type, options) {
      const fallbackTarget =
        preferredTarget ||
        document.querySelector("form.variations_form table.variations") ||
        document.querySelector(".single-product-summary__actions form.cart");

      mount(fallbackTarget);

      if (!notice.parentNode) {
        return;
      }

      window.clearTimeout(hideTimer);
      messages = Array.isArray(newMessages) ? newMessages.filter(Boolean) : [newMessages].filter(Boolean);
      currentIndex = 0;
      actionUrl = options && options.actionUrl ? options.actionUrl : "";

      if (!messages.length || !content) {
        hide();
        return;
      }

      notice.classList.remove("is-visible", "is-dismissing", "is-error", "is-success");
      notice.classList.add(type === "success" ? "is-success" : "is-error");
      notice.classList.toggle("is-clickable", Boolean(actionUrl));

      if (icon) {
        icon.textContent = type === "success" ? "✓" : "!";
      }

      render();

      window.requestAnimationFrame(function () {
        notice.classList.add("is-visible");
      });
    };

    if (closeButton) {
      closeButton.addEventListener("click", function () {
        currentIndex += 1;

        if (currentIndex < messages.length) {
          render();
          return;
        }

        hide();
      });
    }

    mount(preferredTarget);

    window.sultanaStorefrontSingleProductNotice = {
      hide: hide,
      mount: mount,
      show: show,
    };
    window.variedadesExpressSingleProductNotice = window.sultanaStorefrontSingleProductNotice;

    return window.sultanaStorefrontSingleProductNotice;
  };

  const setupSingleProductAddToCartNotice = function () {
    const isMobileProductViewport = function () {
      return window.matchMedia && window.matchMedia("(max-width: 900px)").matches;
    };

    const moveStaticCartNotice = function (notice) {
      const mobileAnchor = document.querySelector("[data-product-mobile-cart-notice-anchor]");
      const summaryCard = document.querySelector(".single-product-summary__card");
      const title = document.querySelector(".single-product-summary__title");

      if (!notice || !mobileAnchor || !summaryCard || !title) {
        return;
      }

      if (isMobileProductViewport()) {
        mobileAnchor.appendChild(notice);
        return;
      }

      summaryCard.insertBefore(notice, title);
    };

    document.querySelectorAll("[data-product-cart-notice]").forEach(function (notice) {
      moveStaticCartNotice(notice);

      notice.addEventListener("click", function (event) {
        const cartUrl = notice.dataset.productCartUrl || "";

        if (!cartUrl || event.target.closest("[data-product-cart-notice-close]")) {
          return;
        }

        if (event.target.closest("a")) {
          return;
        }

        window.location.href = cartUrl;
      });
    });

    window.addEventListener("resize", function () {
      document.querySelectorAll("[data-product-cart-notice]").forEach(moveStaticCartNotice);
    });

    document.querySelectorAll("[data-product-cart-notice-close]").forEach(function (button) {
      button.addEventListener("click", function () {
        const notice = button.closest("[data-product-cart-notice]");

        if (!notice) {
          return;
        }

        notice.classList.add("is-dismissing");

        window.setTimeout(function () {
          notice.remove();
        }, 220);
      });
    });

    const title = document.querySelector(".single-product-summary__title");
    const message = document.querySelector(".woocommerce-notices-wrapper .woocommerce-message");

    if (!title || !message) {
      return;
    }

    const wrapper = message.closest(".woocommerce-notices-wrapper");
    const text = message.textContent.trim();

    if (!text) {
      return;
    }

    const cartLink = document.querySelector(".site-header__cart");
    const cartUrl = (window.sultanaStorefront && window.sultanaStorefront.cartUrl) || (cartLink ? cartLink.href : "");
    const productNotice = setupSingleProductNotice(title);

    productNotice.show(text, "success", {
      actionUrl: cartUrl,
    });

    message.remove();

    if (wrapper && !wrapper.querySelector(".woocommerce-message, .woocommerce-error, .woocommerce-info")) {
      wrapper.remove();
    }
  };

  const setupSingleProductGallery = function () {
    const gallery = document.querySelector(".single-product-gallery");

    if (!gallery) {
      return;
    }

    let mainImage = gallery.querySelector(".single-product-gallery__main-image");
    const mainLink = gallery.querySelector(".single-product-gallery__main-link");
    const thumbs = Array.from(gallery.querySelectorAll(".single-product-gallery__thumb"));

    if (!mainImage || !mainLink) {
      return;
    }

    const rawImages = thumbs.length
      ? thumbs.map(function (thumb) {
          return {
            alt: thumb.dataset.productImageAlt || "",
            src: thumb.dataset.productImage || "",
          };
        }).filter(function (image) {
          return image.src;
        })
      : [
          {
            alt: mainImage.alt || "",
            src: mainImage.currentSrc || mainImage.src || "",
          },
        ];
    let isAnimating = false;
    let didSwipe = false;
    let pointerStartX = 0;
    let pointerStartY = 0;

    const normalizeImageSrc = function (src) {
      return String(src || "")
        .split("?")[0]
        .replace(/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/, "");
    };

    const images = rawImages.reduce(function (uniqueImages, image) {
      if (!image.src) {
        return uniqueImages;
      }

      const exists = uniqueImages.some(function (uniqueImage) {
        return normalizeImageSrc(uniqueImage.src) === normalizeImageSrc(image.src);
      });

      if (!exists) {
        uniqueImages.push(image);
      }

      return uniqueImages;
    }, []);
    let currentIndex = 0;
    let imageChangeToken = 0;
    let intentPreloadTimer = null;
    const preloadCache = new Map();

    if (!images.length) {
      return;
    }

    const preloadImage = function (imageData) {
      if (!imageData || !imageData.src) {
        return Promise.resolve(null);
      }

      if (preloadCache.has(imageData.src)) {
        return preloadCache.get(imageData.src).promise;
      }

      const image = new Image();
      const promise = new Promise(function (resolve, reject) {
        image.addEventListener("load", function () {
          resolve(image);
        }, { once: true });

        image.addEventListener("error", function () {
          preloadCache.delete(imageData.src);
          reject(new Error("No se pudo cargar la imagen del producto."));
        }, { once: true });
      });

      image.decoding = "async";
      image.src = imageData.src;
      imageData.preloaded = image;
      preloadCache.set(imageData.src, {
        image: image,
        promise: promise,
      });

      return promise;
    };

    const scheduleIntentPreload = function (imageData) {
      window.clearTimeout(intentPreloadTimer);

      if (!imageData || !imageData.src) {
        return;
      }

      intentPreloadTimer = window.setTimeout(function () {
        preloadImage(imageData).catch(function () {});
      }, 140);
    };

    const cancelIntentPreload = function () {
      window.clearTimeout(intentPreloadTimer);
      intentPreloadTimer = null;
    };

    const findImageIndexBySrc = function (src) {
      const normalizedSrc = normalizeImageSrc(src);

      return images.findIndex(function (image) {
        return normalizeImageSrc(image.src) === normalizedSrc;
      });
    };

    const setZoom = function (isZoomed, event) {
      if (event) {
        const rect = mainLink.getBoundingClientRect();
        const point = event.changedTouches ? event.changedTouches[0] : event;
        const x = ((point.clientX - rect.left) / rect.width) * 100;
        const y = ((point.clientY - rect.top) / rect.height) * 100;

        mainLink.style.setProperty("--gallery-zoom-x", x + "%");
        mainLink.style.setProperty("--gallery-zoom-y", y + "%");
      }

      mainLink.classList.toggle("is-zoomed", isZoomed);
      mainLink.setAttribute(
        "aria-label",
        isZoomed ? "Reducir imagen del producto" : "Ampliar imagen del producto"
      );
    };

    const refreshThumbs = function () {
      const currentImage = images[currentIndex] || {};
      const currentSrc = normalizeImageSrc(currentImage.src);

      thumbs.forEach(function (thumb, index) {
        thumb.classList.toggle("is-active", normalizeImageSrc(thumb.dataset.productImage) === currentSrc);
      });
    };

    const finishImageChange = function (nextImage) {
      mainImage.remove();
      nextImage.classList.remove("single-product-gallery__main-image--incoming");
      mainImage = nextImage;
      gallery.classList.remove("is-sliding", "is-sliding-next", "is-sliding-prev");
      isAnimating = false;
    };

    const showImage = function (nextIndex, direction) {
      if (nextIndex === currentIndex || !images[nextIndex]) {
        return;
      }

      const imageData = images[nextIndex];
      const changeToken = imageChangeToken + 1;

      imageChangeToken = changeToken;
      setZoom(false);

      preloadImage(imageData).then(function (nextImage) {
        if (changeToken !== imageChangeToken || !nextImage) {
          return;
        }

        isAnimating = true;
        nextImage.className = "single-product-gallery__main-image single-product-gallery__main-image--incoming";
        nextImage.alt = imageData.alt;
        nextImage.decoding = "async";

        gallery.classList.add(direction === "prev" ? "is-sliding-prev" : "is-sliding-next");
        mainLink.appendChild(nextImage);

        window.requestAnimationFrame(function () {
          if (changeToken !== imageChangeToken) {
            return;
          }

          gallery.classList.add("is-sliding");
        });

        window.setTimeout(function () {
          if (changeToken !== imageChangeToken) {
            if (nextImage.parentNode === mainLink) {
              nextImage.remove();
            }

            gallery.classList.remove("is-sliding", "is-sliding-next", "is-sliding-prev");
            isAnimating = false;
            return;
          }

          currentIndex = nextIndex;
          refreshThumbs();
          finishImageChange(nextImage);
        }, 320);
      }).catch(function () {});
    };

    const showAdjacentImage = function (step) {
      const nextIndex = currentIndex + step;

      if (nextIndex < 0 || nextIndex >= images.length) {
        return;
      }

      showImage(nextIndex, step < 0 ? "prev" : "next");
    };

    const showImageBySrc = function (src, alt) {
      const imageSrc = String(src || "");

      if (!imageSrc) {
        return;
      }

      const foundIndex = findImageIndexBySrc(imageSrc);

      if (foundIndex >= 0) {
        showImage(foundIndex, foundIndex < currentIndex ? "prev" : "next");
        return;
      }

      images.push({
        alt: alt || mainImage.alt || "",
        src: imageSrc,
      });

      showImage(images.length - 1, "next");
    };

    window.sultanaStorefrontProductGallery = {
      reset: function () {
        showImage(0, "prev");
      },
      showImageBySrc: showImageBySrc,
    };
    window.variedadesExpressProductGallery = window.sultanaStorefrontProductGallery;

    thumbs.forEach(function (thumb) {
      const imageDataForThumb = function () {
        const imageIndex = findImageIndexBySrc(thumb.dataset.productImage);

        return imageIndex >= 0 ? images[imageIndex] : null;
      };

      thumb.addEventListener("pointerenter", function () {
        scheduleIntentPreload(imageDataForThumb());
      });

      thumb.addEventListener("pointerleave", cancelIntentPreload);
      thumb.addEventListener("focus", function () {
        scheduleIntentPreload(imageDataForThumb());
      });
      thumb.addEventListener("blur", cancelIntentPreload);

      thumb.addEventListener("click", function () {
        cancelIntentPreload();

        const nextIndex = findImageIndexBySrc(thumb.dataset.productImage);

        if (nextIndex >= 0) {
          showImage(nextIndex, nextIndex < currentIndex ? "prev" : "next");
        }
      });
    });

    mainLink.addEventListener("click", function (event) {
      event.preventDefault();

      if (didSwipe || isAnimating) {
        didSwipe = false;
        return;
      }

      setZoom(!mainLink.classList.contains("is-zoomed"), event);
    });

    mainLink.addEventListener("pointerdown", function (event) {
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
      didSwipe = false;
    });

    mainLink.addEventListener("pointerup", function (event) {
      const deltaX = event.clientX - pointerStartX;
      const deltaY = event.clientY - pointerStartY;

      if (Math.abs(deltaX) < 48 || Math.abs(deltaX) <= Math.abs(deltaY)) {
        return;
      }

      didSwipe = true;
      showAdjacentImage(deltaX < 0 ? 1 : -1);
    });
  };

  const setupImageSkeletons = function (scope) {
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

  const setupCopyButtons = function () {
    const copyButtons = document.querySelectorAll("[data-copy-text]");

    copyButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        const text = button.dataset.copyText || "";

        if (!text) {
          return;
        }

        const markCopied = function () {
          button.classList.add("is-copied");
          window.setTimeout(function () {
            button.classList.remove("is-copied");
          }, 1200);
        };

        if (window.sultanaStorefrontCopyText) {
          window.sultanaStorefrontCopyText(text, {
            message: "SKU copiado",
            promptLabel: "Copia este SKU",
          }).then(function (copied) {
            if (copied) {
              markCopied();
            }
          }).catch(function () {});
        }
      });
    });
  };

  const setupVariationSku = function () {
    const skuWrapper = document.querySelector("[data-product-sku-wrapper]");
    const skuText = document.querySelector("[data-product-sku-text]");
    const copyButton = skuWrapper
      ? skuWrapper.querySelector("[data-copy-text]")
      : null;
    const variationForm = document.querySelector("form.variations_form");

    if (!skuWrapper || !skuText || !copyButton || !variationForm || !window.jQuery) {
      return;
    }

    const initialSku = skuWrapper.dataset.initialSku || "";
    let variationSkus = {};

    try {
      variationSkus = JSON.parse(skuWrapper.dataset.variationSkus || "{}");
    } catch (error) {
      variationSkus = {};
    }

    const updateSku = function (sku) {
      const value = sku || "";

      skuWrapper.classList.toggle("is-empty", !value);
      skuText.textContent = value ? "SKU: " + value : "";
      copyButton.dataset.copyText = value;
      copyButton.disabled = !value;
    };

    window.jQuery(variationForm).on("found_variation", function (event, variation) {
      const variationId = variation && variation.variation_id
        ? String(variation.variation_id)
        : "";
      const variationSku = variationId && variationSkus[variationId]
        ? variationSkus[variationId]
        : "";

      updateSku(variationSku && variationSku !== initialSku ? variationSku : initialSku);
    });

    window.jQuery(variationForm).on("reset_data", function () {
      updateSku(initialSku);
    });
  };

  const setupVariationControls = function () {
    const variationForm = document.querySelector("form.variations_form");
    const priceTarget = document.querySelector("[data-product-price]");

    if (!variationForm || !window.jQuery) {
      return;
    }

    const initialPriceHtml = priceTarget ? priceTarget.innerHTML : "";
    const gallery = document.querySelector(".single-product-gallery");
    const variationsTable = variationForm.querySelector("table.variations");
    const themeUrl = window.sultanaStorefront && window.sultanaStorefront.themeUrl
      ? window.sultanaStorefront.themeUrl
      : "";
    const productNotice = setupSingleProductNotice(variationsTable);
    const colorMap = {
      beige: "#d8c3a5",
      amarillo: "#f4d03f",
      azul: "#2563eb",
      blanco: "#ffffff",
      borgona: "#7f1734",
      bronce: "#b47a3c",
      cafe: "#7b4a2f",
      caramel: "#b97845",
      caramelo: "#b97845",
      celeste: "#7dd3fc",
      champagne: "#f7e7ce",
      champan: "#f7e7ce",
      chocolate: "#4e2f1f",
      cobre: "#b87333",
      coral: "#ff7f50",
      crema: "#fff4dc",
      dorado: "#d4af37",
      espresso: "#4b2f24",
      fucsia: "#e91e8f",
      gris: "#9ca3af",
      honey: "#c99045",
      ivory: "#fff8dc",
      lila: "#c8a2c8",
      light: "#f4d7c8",
      marfil: "#fff8e7",
      marron: "#6b4226",
      marrón: "#6b4226",
      natural: "#d8b894",
      negro: "#111111",
      nude: "#d8a48f",
      naranja: "#f97316",
      plateado: "#c0c0c0",
      porcelain: "#f1d6c8",
      rojo: "#d5232f",
      rosa: "#ff69b4",
      rosado: "#ff69b4",
      sand: "#c2a477",
      tan: "#b88a5a",
      transparente: "linear-gradient(135deg, #ffffff 0 46%, #d7d7d7 47% 53%, #ffffff 54% 100%)",
      turquesa: "#20c5b5",
      verde: "#16a34a",
      vino: "#8b1e3f",
    };

    const normalize = function (value) {
      return value
        .toString()
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
    };

    const escapeSelectorValue = function (value) {
      if (window.CSS && window.CSS.escape) {
        return window.CSS.escape(value);
      }

      return value.replace(/"/g, '\\"');
    };

    const isColorAttribute = function (select) {
      const name = normalize(select.name || select.id || "");
      const label = normalize(
        select.closest("tr")?.querySelector("label")?.textContent || ""
      );

      return name.includes("color") || name.includes("tono") || label.includes("color") || label.includes("tono");
    };

    const getColorValue = function (option) {
      const label = normalize(option.textContent || option.value);

      return colorMap[label] || "";
    };

    const getAvailableVariations = function () {
      const variations = window.jQuery(variationForm).data("product_variations");

      return Array.isArray(variations) ? variations : [];
    };

    const getSelectedAttributes = function () {
      return Array.from(variationForm.querySelectorAll('select[name^="attribute_"]')).reduce(function (selected, select) {
        if (select.value) {
          selected[select.name] = select.value;
        }

        return selected;
      }, {});
    };

    const getMissingVariationLabels = function () {
      return Array.from(variationForm.querySelectorAll('select[name^="attribute_"]'))
        .filter(function (select) {
          return !select.value;
        })
        .map(function (select) {
          const row = select.closest("tr");
          const label = row ? row.querySelector("label") : null;

          return label ? label.textContent.trim().replace(/\s*\*$/, "") : "";
        })
        .filter(Boolean);
    };

    const showVariationNotice = function (messages) {
      productNotice.show(messages, "error");
    };

    const clearVariationNotice = function () {
      productNotice.hide();
    };

    const variationMatchesSelectedAttributes = function (variation, selectedAttributes) {
      const attributes = variation && variation.attributes ? variation.attributes : {};

      return Object.keys(selectedAttributes).every(function (attributeName) {
        const variationValue = attributes[attributeName] || "";

        return variationValue === "" || variationValue === selectedAttributes[attributeName];
      });
    };

    const isSelectableVariation = function (variation) {
      if (!variation || !variation.variation_id) {
        return false;
      }

      if (variation.is_in_stock === false || variation.is_purchasable === false) {
        return false;
      }

      if (variation.variation_is_active === false || variation.variation_is_visible === false) {
        return false;
      }

      return true;
    };

    const getSingleSelectableVariation = function () {
      const selectableVariations = getAvailableVariations().filter(isSelectableVariation);

      return selectableVariations.length === 1 ? selectableVariations[0] : null;
    };

    const getVariationAttributeValue = function (variation, select) {
      const attributes = variation && variation.attributes ? variation.attributes : {};
      const variationValue = attributes[select.name] || "";

      if (variationValue) {
        return variationValue;
      }

      const availableOptions = Array.from(select.options).filter(function (option) {
        return option.value && !option.disabled;
      });

      return availableOptions.length === 1 ? availableOptions[0].value : "";
    };

    const autoSelectSingleVariation = function () {
      const variation = getSingleSelectableVariation();
      const selects = Array.from(variationForm.querySelectorAll('select[name^="attribute_"]'));

      if (!variation || !selects.length || selects.some(function (select) {
        return Boolean(select.value);
      })) {
        return;
      }

      const values = selects.map(function (select) {
        return {
          select: select,
          value: getVariationAttributeValue(variation, select),
        };
      });

      if (values.some(function (item) {
        return !item.value;
      })) {
        return;
      }

      values.forEach(function (item) {
        item.select.value = item.value;
      });

      values.forEach(function (item) {
        window.jQuery(item.select).trigger("change");
      });

      window.jQuery(variationForm).trigger("check_variations");
    };

    const getAssignedVariationImage = function (variation) {
      if (!variation || !variation.image) {
        return null;
      }

      const imageId = String(variation.image_id || variation.image.id || "");

      if (!imageId) {
        return null;
      }

      const imageSrc = variation.image.full_src || variation.image.src || "";

      if (!imageSrc) {
        return null;
      }

      return {
        alt: variation.image.alt || "",
        src: imageSrc,
      };
    };

    const updateGalleryFromSelectedAttributes = function () {
      const selectedAttributes = getSelectedAttributes();

      if (!Object.keys(selectedAttributes).length) {
        return false;
      }

      const matchedVariation = getAvailableVariations().find(function (variation) {
        return variationMatchesSelectedAttributes(variation, selectedAttributes) && getAssignedVariationImage(variation);
      });
      const variationImage = getAssignedVariationImage(matchedVariation);

      if (
        !variationImage ||
        !window.sultanaStorefrontProductGallery ||
        typeof window.sultanaStorefrontProductGallery.showImageBySrc !== "function"
      ) {
        return false;
      }

      window.sultanaStorefrontProductGallery.showImageBySrc(variationImage.src, variationImage.alt);

      return true;
    };

    const refreshGroup = function (select, group) {
      const selectedValue = select.value;

      group.classList.toggle("has-selection", Boolean(selectedValue));

      group.querySelectorAll(".variation-choice").forEach(function (button) {
        const option = select.querySelector('option[value="' + escapeSelectorValue(button.dataset.value) + '"]');
        const isDisabled = !option || option.disabled;

        button.classList.toggle("is-selected", button.dataset.value === selectedValue);
        button.disabled = isDisabled;
        button.setAttribute("aria-pressed", String(button.dataset.value === selectedValue));
      });
    };

    variationForm.querySelectorAll("select").forEach(function (select) {
      const options = Array.from(select.options).filter(function (option) {
        return option.value;
      });

      if (!options.length || select.dataset.enhanced === "true") {
        return;
      }

      select.dataset.enhanced = "true";

      const useSwatches = isColorAttribute(select);
      const group = document.createElement("div");

      group.className = useSwatches
        ? "variation-choice-group variation-choice-group--swatches"
        : "variation-choice-group variation-choice-group--pills";
      group.setAttribute("role", "list");

      options.forEach(function (option) {
        const button = document.createElement("button");
        const colorValue = getColorValue(option);

        button.type = "button";
        button.className = useSwatches ? "variation-choice variation-choice--swatch" : "variation-choice variation-choice--pill";
        button.dataset.value = option.value;
        button.setAttribute("aria-pressed", "false");
        button.setAttribute("aria-label", option.textContent.trim());

        if (useSwatches) {
          const swatch = document.createElement("span");

          swatch.className = "variation-choice__swatch";
          swatch.style.background = colorValue || "var(--color-cream)";
          button.appendChild(swatch);
          button.title = option.textContent.trim();
        } else {
          button.textContent = option.textContent.trim();
        }

        button.addEventListener("click", function () {
          if (button.disabled) {
            return;
          }

          select.value = button.dataset.value;
          window.jQuery(select).trigger("change");
        });

        group.appendChild(button);
      });

      select.insertAdjacentElement("afterend", group);
      select.classList.add("is-visually-hidden");
      refreshGroup(select, group);

      window.jQuery(select).on("change", function () {
        refreshGroup(select, group);
        clearVariationNotice();

        window.setTimeout(updateGalleryFromSelectedAttributes, 0);
      });
    });

    variationForm.classList.add("has-custom-variation-controls");

    const resetButton = variationForm.querySelector(".reset_variations");

    if (resetButton) {
      resetButton.classList.add("single-product-summary__reset-variations");
      resetButton.innerHTML =
        '<img src="' +
        themeUrl +
        '/assets/icons/brush-cleaning.svg" alt="" width="18" height="18" aria-hidden="true"><span class="screen-reader-text">Limpiar variaciones</span>';
    }

    const stopMissingVariationSubmit = function (event) {
      const missingLabels = getMissingVariationLabels();

      if (!missingLabels.length) {
        return false;
      }

      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();

      showVariationNotice(
        missingLabels.map(function (label) {
          return "Selecciona " + label.toLowerCase() + " antes de agregar este producto al carrito.";
        })
      );

      if (variationsTable) {
        variationsTable.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }

      return true;
    };

    variationForm.addEventListener("click", function (event) {
      if (!event.target.closest(".single_add_to_cart_button")) {
        return;
      }

      stopMissingVariationSubmit(event);
    }, true);

    variationForm.addEventListener("submit", function (event) {
      stopMissingVariationSubmit(event);
    }, true);

    window.jQuery(variationForm).on("woocommerce_update_variation_values", function () {
      variationForm.querySelectorAll("select").forEach(function (select) {
        const group = select.nextElementSibling;

        if (group && group.classList.contains("variation-choice-group")) {
          refreshGroup(select, group);
        }
      });
    });

    window.jQuery(variationForm).on("found_variation", function (event, variation) {
      if (priceTarget && variation && variation.price_html) {
        priceTarget.innerHTML = variation.price_html;
      }

      const variationImage = getAssignedVariationImage(variation);

      if (
        variationImage &&
        window.sultanaStorefrontProductGallery &&
        typeof window.sultanaStorefrontProductGallery.showImageBySrc === "function"
      ) {
        window.sultanaStorefrontProductGallery.showImageBySrc(variationImage.src, variationImage.alt);
      }
    });

    window.jQuery(variationForm).on("reset_data", function () {
      if (priceTarget) {
        priceTarget.innerHTML = initialPriceHtml;
      }

      window.setTimeout(function () {
        if (updateGalleryFromSelectedAttributes()) {
          return;
        }

        if (
          !Object.keys(getSelectedAttributes()).length &&
          window.sultanaStorefrontProductGallery &&
          typeof window.sultanaStorefrontProductGallery.reset === "function"
        ) {
          window.sultanaStorefrontProductGallery.reset();
        }
      }, 0);
    });

    autoSelectSingleVariation();
  };

  const setupSingleProductActions = function () {
    const actions = document.querySelector(".single-product-summary__actions");
    const cartButton = actions
      ? actions.querySelector(".single_add_to_cart_button")
      : null;

    if (!actions || !cartButton || cartButton.dataset.enhanced === "true") {
      return;
    }

    const themeUrl = window.sultanaStorefront && window.sultanaStorefront.themeUrl
      ? window.sultanaStorefront.themeUrl
      : "";

    cartButton.dataset.enhanced = "true";
    cartButton.insertAdjacentHTML(
      "afterbegin",
      '<img class="single-product-summary__cart-icon" src="' +
        themeUrl +
        '/assets/icons/shopping-cart.svg" alt="" width="18" height="18" aria-hidden="true">'
    );

    const wishlistButton = document.createElement("button");

    wishlistButton.className = "single-product-summary__wishlist";
    wishlistButton.type = "button";
    wishlistButton.setAttribute("aria-label", "Agregar a lista de deseos");
    wishlistButton.innerHTML =
      '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path></svg>';

    cartButton.insertAdjacentElement("afterend", wishlistButton);
  };

  const setupWishlistButton = function () {
    const actions = document.querySelector(".single-product-summary__actions");
    const wishlistButton = actions
      ? actions.querySelector(".single-product-summary__wishlist")
      : null;

    if (!actions || !wishlistButton || !window.sultanaStorefront) {
      return;
    }

    const form = actions.querySelector("form.cart");
    const productNotice = setupSingleProductNotice(form);
    let currentVariationId = "";
    let wishlistState = {};

    try {
      wishlistState = JSON.parse(actions.dataset.wishlistState || "{}");
    } catch (error) {
      wishlistState = {};
    }

    const showNotice = function (text, type) {
      if (!text) {
        productNotice.hide();
        return;
      }

      productNotice.show(text, type);
    };

    const getProductId = function () {
      const productInput = form
        ? form.querySelector('[name="add-to-cart"]')
        : null;

      return productInput ? productInput.value : "";
    };

    const getVariationId = function () {
      const variationInput = form
        ? form.querySelector('[name="variation_id"]')
        : null;

      return variationInput ? variationInput.value : "";
    };

    currentVariationId = getVariationId();

    const normalizeSelectionPart = function (value) {
      return String(value || "")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9_-]+/g, "-")
        .replace(/^-+|-+$/g, "");
    };

    const normalizeAttributes = function (attributes) {
      return Object.keys(attributes || {}).sort().reduce(function (normalized, attributeName) {
        let normalizedName = String(attributeName || "");
        const attributeValue = String(attributes[attributeName] || "");

        if (!normalizedName || !attributeValue) {
          return normalized;
        }

        if (normalizedName.indexOf("attribute_") !== 0) {
          normalizedName = "attribute_" + normalizedName;
        }

        normalized[normalizeSelectionPart(normalizedName)] = normalizeSelectionPart(attributeValue);

        return normalized;
      }, {});
    };

    const buildSelectionStateKey = function (productId, variationId, attributes) {
      return "selection:" + productId + ":" + variationId + ":" + JSON.stringify(normalizeAttributes(attributes));
    };

    const getAvailableVariations = function () {
      const variations = form && window.jQuery
        ? window.jQuery(form).data("product_variations")
        : [];

      return Array.isArray(variations) ? variations : [];
    };

    const getAttributes = function () {
      const attributes = {};

      if (!form) {
        return attributes;
      }

      form.querySelectorAll('select[name^="attribute_"]').forEach(function (select) {
        if (select.value) {
          attributes[select.name] = select.value;
        }
      });

      return attributes;
    };

    const getSelectedVariationId = function () {
      if (!form || !form.classList.contains("variations_form")) {
        return "";
      }

      const attributes = getAttributes();
      const selects = Array.from(form.querySelectorAll('select[name^="attribute_"]'));

      if (!selects.length || selects.some(function (select) {
        return !select.value;
      })) {
        return "";
      }

      const matchedVariation = getAvailableVariations().find(function (variation) {
        const variationAttributes = variation && variation.attributes ? variation.attributes : {};

        return Object.keys(attributes).every(function (attributeName) {
          const variationValue = variationAttributes[attributeName] || "";

          return variationValue === "" || variationValue === attributes[attributeName];
        });
      });

      if (matchedVariation && matchedVariation.variation_id) {
        return String(matchedVariation.variation_id);
      }

      return currentVariationId;
    };

    const getStateKey = function () {
      const variationId = getSelectedVariationId();
      const productId = getProductId();

      if (form && form.classList.contains("variations_form")) {
        return productId && variationId ? buildSelectionStateKey(productId, variationId, getAttributes()) : "";
      }

      return productId ? "product:" + productId : "";
    };

    const refreshWishlistState = function () {
      const key = getStateKey();
      const isActive = Boolean(key && wishlistState[key]);

      wishlistButton.classList.toggle("is-active", Boolean(isActive));
      wishlistButton.setAttribute(
        "aria-label",
        isActive ? "Quitar de lista de deseos" : "Agregar a lista de deseos"
      );
    };

    const getMissingVariationLabels = function () {
      if (!form) {
        return [];
      }

      return Array.from(form.querySelectorAll('select[name^="attribute_"]'))
        .filter(function (select) {
          return !select.value;
        })
        .map(function (select) {
          const row = select.closest("tr");
          const label = row ? row.querySelector("label") : null;

          return label ? label.textContent.trim().replace(/\s*\*$/, "") : "";
        })
        .filter(Boolean);
    };

    const hasSelectedRequiredVariation = function () {
      if (!form || !form.classList.contains("variations_form")) {
        return true;
      }

      return Boolean(getSelectedVariationId());
    };

    if (form && window.jQuery) {
      window.jQuery(form).on("found_variation", function (event, variation) {
        currentVariationId = variation && variation.variation_id ? String(variation.variation_id) : "";

        window.setTimeout(refreshWishlistState, 0);
      });

      window.jQuery(form).on("reset_data", function () {
        currentVariationId = "";

        window.setTimeout(refreshWishlistState, 0);
      });

      window.jQuery(form).on("change", 'select[name^="attribute_"]', function () {
        currentVariationId = "";

        window.setTimeout(refreshWishlistState, 0);
      });
    }

    refreshWishlistState();

    wishlistButton.addEventListener("click", function () {
      const formData = new FormData();

      showNotice("", "");

      if (!hasSelectedRequiredVariation()) {
        const missingLabels = getMissingVariationLabels();

        showNotice(
          missingLabels.length
            ? missingLabels.map(function (label) {
                return "Selecciona " + label.toLowerCase() + " antes de agregar este producto a tu lista de deseos.";
              })
            : "Selecciona las opciones del producto antes de agregarlo a tu lista de deseos.",
          "error"
        );
        return;
      }

      const stateKey = getStateKey();
      const isRemoving = Boolean(stateKey && wishlistState[stateKey]);

      wishlistButton.disabled = true;
      wishlistButton.classList.add("is-loading");

      formData.append("nonce", window.sultanaStorefront.wishlistNonce || "");

      if (isRemoving) {
        formData.append("action", "scc_remove_wishlist_item");
        formData.append("key", wishlistState[stateKey] || "");
      } else {
        formData.append("action", "scc_add_wishlist_item");
        formData.append("product_id", getProductId());
        formData.append("variation_id", getSelectedVariationId());
        formData.append("attributes", JSON.stringify(getAttributes()));
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
            throw new Error(result.data && result.data.message ? result.data.message : "No pudimos agregar este producto.");
          }

          if (isRemoving) {
            delete wishlistState[stateKey];
          } else {
            wishlistState[stateKey] = result.data.key || "";
          }

          refreshWishlistState();

          if (window.sultanaStorefrontUpdateWishlistCount && result.data && typeof result.data.count !== "undefined") {
            window.sultanaStorefrontUpdateWishlistCount(result.data.count);
          }

          showNotice(result.data.message || "Lista de deseos actualizada.", "success");
        })
        .catch(function (error) {
          showNotice(error.message, "error");

          if (error.message.toLowerCase().includes("inicia sesión")) {
            const accountButton = document.querySelector('[data-modal-open="account"]');

            if (accountButton) {
              accountButton.click();
            }
          }
        })
        .finally(function () {
          wishlistButton.disabled = false;
          wishlistButton.classList.remove("is-loading");
        });
    });
  };

  const setupRelatedProductsCarousel = function () {
    const track = document.querySelector("[data-related-products-track]");

    if (!track) {
      return;
    }

    const setupHorizontalScroll = window.sultanaStorefrontSetupHorizontalScroll;

    if (setupHorizontalScroll) {
      setupHorizontalScroll(
        track,
        document.querySelector(".single-product-related__arrow--prev"),
        document.querySelector(".single-product-related__arrow--next")
      );
    }

  };

  setupSingleProductGallery();
  setupSingleProductAddToCartNotice();
  setupWooCommerceNotices();
  setupCopyButtons();
  setupVariationSku();
  setupVariationControls();
  setupSingleProductActions();
  setupWishlistButton();
  setupImageSkeletons(document);
  setupRelatedProductsCarousel();
})();
