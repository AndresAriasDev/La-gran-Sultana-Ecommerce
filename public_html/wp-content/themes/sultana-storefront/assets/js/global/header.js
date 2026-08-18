(function () {
  const menuToggle = document.querySelector(".site-header__menu-toggle");
  const navigation = document.querySelector(".site-navigation");

  if (menuToggle && navigation) {
    const updateMobileMenuOffset = function () {
      const navigationTop = Math.max(0, Math.round(navigation.getBoundingClientRect().top));
      document.documentElement.style.setProperty(
        "--ve-mobile-menu-top",
        `${navigationTop}px`
      );
    };

    menuToggle.addEventListener("click", function () {
      const isOpen = menuToggle.getAttribute("aria-expanded") === "true";

      menuToggle.setAttribute("aria-expanded", String(!isOpen));
      navigation.classList.toggle("is-open", !isOpen);

      if (!isOpen) {
        window.requestAnimationFrame(updateMobileMenuOffset);
      }
    });

    window.addEventListener("resize", function () {
      if (menuToggle.getAttribute("aria-expanded") === "true") {
        updateMobileMenuOffset();
      }
    });
  }

  const setupSearchRedirect = function () {
    const searchForm = document.querySelector(".site-search");

    if (!searchForm) {
      return;
    }

    const searchInput = searchForm.querySelector('input[name="s"]');
    const shopUrl = searchForm.dataset.shopUrl;

    if (!searchInput || !shopUrl) {
      return;
    }

    searchForm.addEventListener("submit", function (event) {
      if (searchInput.value.trim() !== "") {
        return;
      }

      event.preventDefault();
      window.location.assign(shopUrl);
    });
  };

  const setupAutoHideHeader = function () {
    const header = document.querySelector(".site-header");

    if (!header) {
      return;
    }

    let lastScrollY = window.scrollY;
    let ticking = false;

    const updateHeader = function () {
      const currentScrollY = Math.max(window.scrollY, 0);
      const scrollDelta = currentScrollY - lastScrollY;
      const isMenuOpen =
        menuToggle && menuToggle.getAttribute("aria-expanded") === "true";

      if (currentScrollY < 80 || isMenuOpen) {
        header.classList.remove("is-hidden");
      } else if (scrollDelta > 8) {
        header.classList.add("is-hidden");
      } else if (scrollDelta < -8) {
        header.classList.remove("is-hidden");
      }

      lastScrollY = currentScrollY;
      ticking = false;
    };

    window.addEventListener(
      "scroll",
      function () {
        if (!ticking) {
          window.requestAnimationFrame(updateHeader);
          ticking = true;
        }
      },
      { passive: true }
    );
  };

  const setupHorizontalScroll = function (track, prevButton, nextButton) {
    if (!track || !prevButton || !nextButton) {
      return;
    }

    const hintScrollThreshold = 24;
    const hintKeyMap = [
      {
        attribute: "data-related-products-track",
        key: "variedadesExpressRelatedScrolled",
      },
      {
        attribute: "data-shop-products-carousel",
        key: "variedadesExpressSearchSuggestionsScrolled",
      },
      {
        attribute: "data-cart-recommendations-track",
        key: "variedadesExpressCartRecommendationsScrolled",
      },
    ];

    const getHintStorageKey = function () {
      if (track.dataset.scrollHintKey) {
        return track.dataset.scrollHintKey;
      }

      const matchedKey = hintKeyMap.find(function (item) {
        return track.hasAttribute(item.attribute);
      });

      if (matchedKey) {
        return matchedKey.key;
      }

      if (track.classList.contains("home-brands__grid")) {
        return "variedadesExpressBrandsScrolled";
      }

      if (track.classList.contains("home-categories-carousel__track")) {
        return "variedadesExpressCategoriesScrolled";
      }

      return "";
    };

    const hintStorageKey = getHintStorageKey();
    const initialScrollLeft = track.scrollLeft;

    if (track.dataset.horizontalScrollReady === "true") {
      return;
    }

    track.dataset.horizontalScrollReady = "true";

    if (hintStorageKey && window.sessionStorage.getItem(hintStorageKey) === "true") {
      track.classList.add("has-seen-scroll");
    }

    const markScrollHintSeen = function () {
      if (track.classList.contains("has-seen-scroll")) {
        return;
      }

      track.classList.add("has-seen-scroll");

      if (hintStorageKey) {
        window.sessionStorage.setItem(hintStorageKey, "true");
      }
    };

    const updateArrowState = function () {
      const maxScroll = track.scrollWidth - track.clientWidth;
      const currentScroll = Math.ceil(track.scrollLeft);

      prevButton.disabled = currentScroll <= 0;
      nextButton.disabled = currentScroll >= maxScroll - 1;
    };

    const scrollTrack = function (direction) {
      const amount = Math.max(220, Math.round(track.clientWidth * 0.45));

      track.scrollBy({
        left: direction * amount,
        behavior: "smooth",
      });
    };

    prevButton.addEventListener("click", function () {
      markScrollHintSeen();
      scrollTrack(-1);
    });

    nextButton.addEventListener("click", function () {
      markScrollHintSeen();
      scrollTrack(1);
    });

    track.addEventListener(
      "scroll",
      function () {
        updateArrowState();

        if (Math.abs(track.scrollLeft - initialScrollLeft) >= hintScrollThreshold) {
          markScrollHintSeen();
        }
      },
      { passive: true }
    );

    window.addEventListener("resize", updateArrowState);
    updateArrowState();
  };

  setupSearchRedirect();
  setupAutoHideHeader();

  setupHorizontalScroll(
    document.querySelector(".site-navigation__menu"),
    document.querySelector(".site-navigation__arrow--prev"),
    document.querySelector(".site-navigation__arrow--next")
  );

  window.sultanaStorefrontSetupHorizontalScroll = setupHorizontalScroll;
  window.variedadesExpressSetupHorizontalScroll = window.sultanaStorefrontSetupHorizontalScroll;

  window.sultanaStorefrontUpdateWishlistCount = function (count) {
    const badge = document.querySelector("[data-wishlist-count]");
    const nextCount = Math.max(0, Number.parseInt(count, 10) || 0);

    if (!badge) {
      return;
    }

    badge.textContent = String(nextCount);
    badge.hidden = nextCount <= 0;
  };
  window.variedadesExpressUpdateWishlistCount = window.sultanaStorefrontUpdateWishlistCount;

  window.sultanaStorefrontUpdateCartCount = function (count) {
    const badge = document.querySelector(".site-header__cart-count");
    const nextCount = Math.max(0, Number.parseInt(count, 10) || 0);

    if (!badge) {
      return;
    }

    badge.textContent = String(nextCount);
  };
  window.variedadesExpressUpdateCartCount = window.sultanaStorefrontUpdateCartCount;
})();
