(function () {
    'use strict';

    document.querySelectorAll('[data-statistics-period-filter]').forEach(function (root) {
        var toggle = root.querySelector('[data-statistics-period-toggle]');
        var panelId = toggle ? toggle.getAttribute('aria-controls') : '';
        var panel = panelId ? document.getElementById(panelId) : null;

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        panel.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setOpen(false);
            }
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });

    document.querySelectorAll('[data-statistics-chart]').forEach(function (chart) {
        var svg = chart.querySelector('svg');
        var activePoint = chart.querySelector('.sultana-admin-sales-chart__active-point');
        var tooltip = chart.querySelector('.sultana-admin-sales-chart__tooltip');
        var tooltipLabel = document.createElement('small');
        var tooltipAmount = document.createElement('span');
        var points = [];
        var moneySettings = window.SultanaAdminStatistics && window.SultanaAdminStatistics.money
            ? window.SultanaAdminStatistics.money
            : {};

        try {
            points = JSON.parse(chart.getAttribute('data-points') || '[]');
        } catch (error) {
            points = [];
        }

        if (!svg || !activePoint || !tooltip || !points.length) {
            return;
        }

        tooltip.textContent = '';
        tooltip.appendChild(tooltipLabel);
        tooltip.appendChild(tooltipAmount);

        svg.addEventListener('mousemove', function (event) {
            showNearest(event.clientX);
        });

        svg.addEventListener('touchstart', function (event) {
            if (event.touches.length) {
                showNearest(event.touches[0].clientX);
            }
        }, { passive: true });

        svg.addEventListener('touchmove', function (event) {
            if (event.touches.length) {
                showNearest(event.touches[0].clientX);
            }
        }, { passive: true });

        svg.addEventListener('mouseleave', hideTooltip);

        function showNearest(clientX) {
            var rect = svg.getBoundingClientRect();
            var viewBox = svg.viewBox.baseVal;
            var svgX = ((clientX - rect.left) / rect.width) * viewBox.width;
            var point = nearestPoint(svgX);

            if (!point) {
                hideTooltip();
                return;
            }

            activePoint.setAttribute('cx', String(point.x));
            activePoint.setAttribute('cy', String(point.y));
            tooltipLabel.textContent = point.label || '';
            tooltipAmount.textContent = formatMoney(point.amount);
            tooltip.hidden = false;
            chart.classList.add('is-active');
            positionTooltip(point, rect, viewBox);
        }

        function nearestPoint(svgX) {
            return points.reduce(function (nearest, point) {
                var distance = Math.abs(Number(point.x) - svgX);

                if (!nearest || distance < nearest.distance) {
                    return {
                        distance: distance,
                        x: Number(point.x),
                        y: Number(point.y),
                        label: point.label,
                        amount: Number(point.amount || 0)
                    };
                }

                return nearest;
            }, null);
        }

        function positionTooltip(point, rect, viewBox) {
            var tooltipWidth = tooltip.offsetWidth || 120;
            var pixelX = (Number(point.x) / viewBox.width) * rect.width;
            var pixelY = (Number(point.y) / viewBox.height) * rect.height;
            var halfWidth = tooltipWidth / 2;
            var safeX = Math.max(halfWidth + 8, Math.min(rect.width - halfWidth - 8, pixelX));
            var safeY = Math.max(38, pixelY);

            tooltip.style.left = safeX + 'px';
            tooltip.style.top = safeY + 'px';
        }

        function hideTooltip() {
            chart.classList.remove('is-active');
            tooltip.hidden = true;
        }

        function formatMoney(amount) {
            var decimals = Number(moneySettings.decimals);
            var decimalSeparator = typeof moneySettings.decimalSeparator === 'string' ? moneySettings.decimalSeparator : '.';
            var thousandSeparator = typeof moneySettings.thousandSeparator === 'string' ? moneySettings.thousandSeparator : ',';
            var currencySymbol = typeof moneySettings.currencySymbol === 'string' ? moneySettings.currencySymbol : '';
            var priceFormat = typeof moneySettings.priceFormat === 'string' ? moneySettings.priceFormat : '%1$s%2$s';
            var normalizedAmount = Number.isFinite(Number(amount)) ? Number(amount) : 0;
            var parts;
            var integerPart;
            var decimalPart;
            var formattedNumber;

            decimals = Number.isFinite(decimals) ? Math.max(0, decimals) : 2;
            parts = normalizedAmount.toFixed(decimals).split('.');
            integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
            decimalPart = parts[1] ? decimalSeparator + parts[1] : '';
            formattedNumber = integerPart + decimalPart;

            return priceFormat
                .replace('%1$s', currencySymbol)
                .replace('%2$s', formattedNumber);
        }
    });
}());
