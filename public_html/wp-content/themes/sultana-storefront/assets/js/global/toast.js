(function () {
  const toastStorageKey = "variedadesExpressReviewToast";

  const showSiteToast = function (message, type) {
    if (!message) {
      return;
    }

    const existingToast = document.querySelector(".site-toast");

    if (existingToast) {
      existingToast.remove();
    }

    const toast = document.createElement("div");
    const iconName = type === "error" ? "x" : "check";
    const iconMarkup =
      window.sultanaStorefront &&
      window.sultanaStorefront.icons &&
      window.sultanaStorefront.icons[iconName]
        ? window.sultanaStorefront.icons[iconName]
        : "";

    toast.className = "site-toast site-toast--" + (type === "error" ? "error" : "success");
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    if (iconMarkup) {
      const iconWrap = document.createElement("span");
      iconWrap.className = "site-toast__icon-wrap";
      iconWrap.setAttribute("aria-hidden", "true");
      iconWrap.innerHTML = iconMarkup;
      toast.appendChild(iconWrap);
    }

    toast.appendChild(document.createTextNode(message));
    document.body.appendChild(toast);

    window.requestAnimationFrame(function () {
      toast.classList.add("is-visible");
    });

    window.setTimeout(function () {
      toast.classList.remove("is-visible");
      window.setTimeout(function () {
        toast.remove();
      }, 240);
    }, 4200);
  };

  window.sultanaStorefrontShowToast = showSiteToast;
  window.variedadesExpressShowToast = window.sultanaStorefrontShowToast;
  window.sultanaStorefrontToastStorageKey = toastStorageKey;
  window.variedadesExpressToastStorageKey = window.sultanaStorefrontToastStorageKey;
  window.sultanaStorefrontCopyText = function (text, options) {
    const value = String(text || "");
    const settings = options || {};
    const promptLabel = settings.promptLabel || "Copia este texto";
    const message = settings.message || "";
    const shouldShowToast = settings.showToast !== false;

    if (!value) {
      return Promise.reject(new Error("No hay texto para copiar."));
    }

    const markCopied = function () {
      if (message && shouldShowToast && window.sultanaStorefrontShowToast) {
        window.sultanaStorefrontShowToast(message, "success");
      }

      return true;
    };

    const promptFallback = function () {
      window.prompt(promptLabel, value);

      return false;
    };

    if (navigator.clipboard && window.isSecureContext && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(value).then(markCopied).catch(promptFallback);
    }

    return Promise.resolve(promptFallback());
  };
  window.variedadesExpressCopyText = window.sultanaStorefrontCopyText;

  try {
    const storedToast = window.sessionStorage.getItem(toastStorageKey);

    if (storedToast) {
      const toastData = JSON.parse(storedToast);

      window.sessionStorage.removeItem(toastStorageKey);
      if (toastData.scrollTop) {
        if ("scrollRestoration" in window.history) {
          window.history.scrollRestoration = "manual";
        }
        window.scrollTo(0, 0);
      }
      showSiteToast(toastData.message, toastData.type);
    }
  } catch (error) {
    window.sessionStorage.removeItem(toastStorageKey);
  }

  const setupUrlNotices = function () {
    const url = new URL(window.location.href);
    const notice = url.searchParams.get("scc_notice") || url.searchParams.get("ve_notice");

    if (notice !== "logged_out") {
      return;
    }

    const message =
      window.sultanaStorefront &&
      window.sultanaStorefront.notices &&
      window.sultanaStorefront.notices.loggedOut
        ? window.sultanaStorefront.notices.loggedOut
        : "Sesión cerrada correctamente.";

    showSiteToast(message, "success");
    url.searchParams.delete("scc_notice");
    url.searchParams.delete("ve_notice");
    window.history.replaceState({}, document.title, url.toString());
  };

  setupUrlNotices();
})();
