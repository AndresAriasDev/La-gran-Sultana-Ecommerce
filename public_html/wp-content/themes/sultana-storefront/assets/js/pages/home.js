(function () {
  const setupHorizontalScroll = window.sultanaStorefrontSetupHorizontalScroll;

  if (setupHorizontalScroll) {
    setupHorizontalScroll(
      document.querySelector(".home-categories-carousel__track"),
      document.querySelector(".home-categories-carousel__arrow--prev"),
      document.querySelector(".home-categories-carousel__arrow--next")
    );

    setupHorizontalScroll(
      document.querySelector(".home-brands__grid"),
      document.querySelector(".home-brands__arrow--prev"),
      document.querySelector(".home-brands__arrow--next")
    );
  }

  const setupPromotionCarousel = function () {
    const carousel = document.querySelector(".home-promotion-carousel");
    const track = carousel ? carousel.querySelector("[data-home-promotion-track]") : null;

    if (!carousel || !track || track.dataset.promotionCarouselReady === "true") {
      return;
    }

    const slides = Array.from(track.querySelectorAll("[data-home-promotion-slide]"));
    const prevButton = carousel.querySelector(".home-promotion-carousel__arrow--prev");
    const nextButton = carousel.querySelector(".home-promotion-carousel__arrow--next");
    const dots = Array.from(carousel.querySelectorAll("[data-home-promotion-dot]"));

    track.dataset.promotionCarouselReady = "true";

    let activeIndex = 0;
    let ticking = false;

    const slideOffset = function (index) {
      const slide = slides[index];

      return slide ? slide.offsetLeft - track.offsetLeft : 0;
    };

    const nearestSlideIndex = function () {
      return slides.reduce(function (nearestIndex, slide, index) {
        const nearestDistance = Math.abs(slideOffset(nearestIndex) - track.scrollLeft);
        const currentDistance = Math.abs(slideOffset(index) - track.scrollLeft);

        return currentDistance < nearestDistance ? index : nearestIndex;
      }, 0);
    };

    const updateState = function (nextIndex) {
      activeIndex = Math.max(0, Math.min(slides.length - 1, nextIndex));

      if (prevButton) {
        prevButton.disabled = activeIndex <= 0;
      }

      if (nextButton) {
        nextButton.disabled = activeIndex >= slides.length - 1;
      }

      dots.forEach(function (dot, index) {
        const isActive = index === activeIndex;

        dot.classList.toggle("is-active", isActive);
        dot.setAttribute("aria-current", String(isActive));
      });
    };

    const goToSlide = function (index) {
      const nextIndex = Math.max(0, Math.min(slides.length - 1, index));

      track.scrollTo({
        left: slideOffset(nextIndex),
        behavior: "smooth",
      });

      updateState(nextIndex);
    };

    if (slides.length <= 1) {
      return;
    }

    if (prevButton) {
      prevButton.addEventListener("click", function () {
        goToSlide(activeIndex - 1);
      });
    }

    if (nextButton) {
      nextButton.addEventListener("click", function () {
        goToSlide(activeIndex + 1);
      });
    }

    dots.forEach(function (dot, index) {
      dot.addEventListener("click", function () {
        goToSlide(index);
      });
    });

    track.addEventListener(
      "scroll",
      function () {
        if (ticking) {
          return;
        }

        window.requestAnimationFrame(function () {
          updateState(nearestSlideIndex());
          ticking = false;
        });

        ticking = true;
      },
      { passive: true }
    );

    window.addEventListener("resize", function () {
      track.scrollTo({
        left: slideOffset(activeIndex),
        behavior: "auto",
      });
    });

    updateState(0);
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

  const createForYouSkeletons = function (count) {
    const fragment = document.createDocumentFragment();

    for (let index = 0; index < count; index += 1) {
      const card = document.createElement("article");

      card.className = "for-you-product-card for-you-product-card--skeleton";
      card.setAttribute("aria-hidden", "true");
      card.innerHTML =
        '<span class="for-you-product-card__media skeleton-block"></span>' +
        '<span class="skeleton-line skeleton-line--title"></span>' +
        '<span class="for-you-product-card__footer">' +
          '<span class="for-you-product-card__prices">' +
            '<span class="skeleton-line skeleton-line--price"></span>' +
            '<span class="skeleton-line skeleton-line--price-short"></span>' +
          '</span>' +
          '<span class="for-you-product-card__cart skeleton-button"></span>' +
        '</span>';

      fragment.appendChild(card);
    }

    return fragment;
  };

  const removeForYouSkeletons = function (grid) {
    grid.querySelectorAll(".for-you-product-card--skeleton").forEach(function (card) {
      card.remove();
    });
  };

  const setupForYouProducts = function () {
    const forYouButton = document.querySelector("[data-for-you-load-more]");
    const forYouGrid = document.querySelector("[data-for-you-grid]");

    if (!forYouButton || !forYouGrid || !window.sultanaStorefront) {
      return;
    }

    forYouButton.addEventListener("click", function () {
      const offset = Number.parseInt(forYouButton.dataset.offset || "0", 10);
      const formData = new FormData();
      const skeletonCount = window.matchMedia("(max-width: 640px)").matches ? 4 : 5;

      formData.append("action", "variedadesexpress_load_for_you");
      formData.append("nonce", window.sultanaStorefront.forYouNonce);
      formData.append("offset", String(offset));

      forYouButton.disabled = true;
      forYouButton.classList.add("is-loading");
      forYouGrid.appendChild(createForYouSkeletons(skeletonCount));

      fetch(window.sultanaStorefront.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          removeForYouSkeletons(forYouGrid);

          if (!result.success || !result.data.html) {
            forYouButton.remove();
            return;
          }

          forYouGrid.insertAdjacentHTML("beforeend", result.data.html);
          setupImageSkeletons(forYouGrid);
          forYouButton.dataset.offset = String(offset + result.data.count);

          if (!result.data.has_more) {
            forYouButton.remove();
          }
        })
        .catch(function () {
          removeForYouSkeletons(forYouGrid);
          forYouButton.disabled = false;
        })
        .finally(function () {
          if (document.body.contains(forYouButton)) {
            forYouButton.disabled = false;
            forYouButton.classList.remove("is-loading");
          }
        });
    });
  };

  setupPromotionCarousel();
  setupImageSkeletons(document);
  setupForYouProducts();
})();
