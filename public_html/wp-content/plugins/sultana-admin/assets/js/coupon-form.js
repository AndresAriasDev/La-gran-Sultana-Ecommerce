(function () {
    'use strict';

    document.querySelectorAll('[data-sultana-coupon-filter]').forEach(function (form) {
        var data = readJson(form.getAttribute('data-sultana-coupon-filter'), {});
        var allCategories = normalizeIds(data.categories || []);
        var allBrands = normalizeIds(data.brands || []);
        var pairs = normalizePairs(data.pairs || []);

        if (!allCategories.length || !allBrands.length || !pairs.length) {
            return;
        }

        form.addEventListener('change', function (event) {
            if (!event.target.matches('input[name="product_categories[]"], input[name="product_brands[]"]')) {
                return;
            }

            applyFilters();
        });

        applyFilters();

        function applyFilters() {
            var settled = settleSelections();
            updateOptions('categories', settled.categories);
            updateOptions('brands', settled.brands);
        }

        function settleSelections() {
            var categorySelection = getSelected('categories');
            var brandSelection = getSelected('brands');

            for (var step = 0; step < 4; step += 1) {
                var allowedCategories = allowedCategoriesFor(brandSelection);
                var allowedBrands = allowedBrandsFor(categorySelection);
                var nextCategories = categorySelection.filter(function (id) {
                    return allowedCategories.indexOf(id) !== -1;
                });
                var nextBrands = brandSelection.filter(function (id) {
                    return allowedBrands.indexOf(id) !== -1;
                });

                if (sameIds(categorySelection, nextCategories) && sameIds(brandSelection, nextBrands)) {
                    return {
                        categories: allowedCategories,
                        brands: allowedBrands
                    };
                }

                setSelected('categories', nextCategories);
                setSelected('brands', nextBrands);
                categorySelection = nextCategories;
                brandSelection = nextBrands;
            }

            return {
                categories: allowedCategoriesFor(getSelected('brands')),
                brands: allowedBrandsFor(getSelected('categories'))
            };
        }

        function allowedCategoriesFor(brandSelection) {
            if (!brandSelection.length) {
                return allCategories.slice();
            }

            return uniqueIds(pairs.filter(function (pair) {
                return brandSelection.indexOf(pair.brandId) !== -1;
            }).map(function (pair) {
                return pair.categoryId;
            }));
        }

        function allowedBrandsFor(categorySelection) {
            if (!categorySelection.length) {
                return allBrands.slice();
            }

            return uniqueIds(pairs.filter(function (pair) {
                return categorySelection.indexOf(pair.categoryId) !== -1;
            }).map(function (pair) {
                return pair.brandId;
            }));
        }

        function updateOptions(type, allowedIds) {
            getInputs(type).forEach(function (input) {
                var id = parseInt(input.value, 10) || 0;
                var allowed = allowedIds.indexOf(id) !== -1;
                var option = input.closest('[data-coupon-filter-option]');

                input.disabled = !allowed;

                if (!allowed) {
                    input.checked = false;
                }

                if (option) {
                    option.hidden = !allowed;
                }
            });
        }

        function getSelected(type) {
            return getInputs(type).filter(function (input) {
                return input.checked && !input.disabled;
            }).map(function (input) {
                return parseInt(input.value, 10) || 0;
            }).filter(Boolean);
        }

        function setSelected(type, ids) {
            getInputs(type).forEach(function (input) {
                input.checked = ids.indexOf(parseInt(input.value, 10) || 0) !== -1;
            });
        }

        function getInputs(type) {
            var name = type === 'brands' ? 'product_brands[]' : 'product_categories[]';

            return Array.prototype.slice.call(form.querySelectorAll('input[name="' + name + '"]'));
        }
    });

    function readJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function normalizeIds(ids) {
        return uniqueIds(ids.map(function (id) {
            return parseInt(id, 10) || 0;
        }).filter(Boolean));
    }

    function normalizePairs(pairs) {
        return pairs.map(function (pair) {
            return {
                categoryId: parseInt(pair.category_id, 10) || 0,
                brandId: parseInt(pair.brand_id, 10) || 0
            };
        }).filter(function (pair) {
            return pair.categoryId > 0 && pair.brandId > 0;
        });
    }

    function uniqueIds(ids) {
        return ids.filter(function (id, index) {
            return ids.indexOf(id) === index;
        });
    }

    function sameIds(first, second) {
        if (first.length !== second.length) {
            return false;
        }

        return first.every(function (id) {
            return second.indexOf(id) !== -1;
        });
    }
}());
