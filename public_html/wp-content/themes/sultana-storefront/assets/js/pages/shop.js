(function () {
  const setupImageSkeletons = function () {
    document.querySelectorAll(".shop-page .js-image-skeleton").forEach(function (wrapper) {
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

  const setupSuggestedProductsCarousel = function () {
    document.querySelectorAll("[data-shop-products-carousel]").forEach(function (track) {
      const section = track.closest(".single-product-related");
      const setupHorizontalScroll = window.sultanaStorefrontSetupHorizontalScroll;

      if (setupHorizontalScroll && section) {
        setupHorizontalScroll(
          track,
          section.querySelector(".single-product-related__arrow--prev"),
          section.querySelector(".single-product-related__arrow--next")
        );
      }

    });
  };

  const setupShopPage = function () {
    setupImageSkeletons();
    setupSuggestedProductsCarousel();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupShopPage, { once: true });
    return;
  }

  setupShopPage();
})();
