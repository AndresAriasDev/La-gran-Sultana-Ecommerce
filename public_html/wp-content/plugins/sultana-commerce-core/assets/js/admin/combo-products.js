(function ($) {
  let nextComponentIndex = Date.now();

  const setupEnhancedSelect = function ($context) {
    if ($.fn.wc_product_search) {
      $context.find(".wc-product-search").filter(":not(.enhanced)").wc_product_search();
      return;
    }

    $(document.body).trigger("wc-enhanced-select-init");
  };

  const updateComboFieldVisibility = function () {
    const productType = $("#product-type").val();
    const isCombo = productType === "combo";
    const $comboVisibleFields = $(
      ".product_data_tabs .general_tab, .product_data_tabs li a[href='#general_product_data'], #general_product_data, #general_product_data .options_group.pricing, #general_product_data ._regular_price_field, #general_product_data ._sale_price_field, ._sku_field"
    );
    const $comboHiddenFields = $("._manage_stock_field, ._stock_field, ._backorders_field, ._low_stock_amount_field, ._weight_field");

    $comboHiddenFields.addClass("hide_if_combo");

    if (isCombo) {
      $comboVisibleFields.addClass("show_if_combo");
      $(".product_data_tabs .general_tab, .product_data_tabs li a[href='#general_product_data']").closest("li").show();
      $("#general_product_data .options_group.pricing, #general_product_data ._regular_price_field, #general_product_data ._sale_price_field, ._sku_field").show();
      $("#_regular_price").prop("readonly", true).attr("aria-readonly", "true");
      $comboHiddenFields.hide();
    } else {
      $comboVisibleFields.removeClass("show_if_combo");
      $("#_regular_price").prop("readonly", false).removeAttr("aria-readonly");
      $comboHiddenFields.css("display", "");
    }
  };

  const reindexComponentRows = function () {
    $("[data-scc-combo-components-list]")
      .find("[data-scc-combo-component-row]")
      .each(function (index) {
        $(this)
          .find("[name]")
          .each(function () {
            this.name = this.name.replace(/scc_combo_components\[[^\]]+\]/, "scc_combo_components[" + index + "]");
          });
      });
  };

  $(function () {
    const $table = $("[data-scc-combo-components]");

    updateComboFieldVisibility();
    $(document.body).trigger("woocommerce-product-type-change");
    $(document.body).on("woocommerce-product-type-change", updateComboFieldVisibility);
    $("#product-type").on("change", updateComboFieldVisibility);

    $("#post").on("submit", function () {
      reindexComponentRows();
    });

    if (!$table.length) {
      return;
    }

    setupEnhancedSelect($(document.body));

    $table.on("click", "[data-scc-combo-add-component]", function (event) {
      event.preventDefault();

      const $template = $("[data-scc-combo-component-template]");
      const $list = $("[data-scc-combo-components-list]");
      const index = String(nextComponentIndex++);
      const html = $template.html().replace(/__index__/g, index);
      const $row = $(html);

      $list.append($row);
      setupEnhancedSelect($row);
    });

    $table.on("click", "[data-scc-combo-remove-component]", function (event) {
      event.preventDefault();
      $(this).closest("[data-scc-combo-component-row]").remove();
    });
  });
})(jQuery);
