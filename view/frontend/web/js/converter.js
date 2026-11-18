/**
 * Storefront currency converter.
 *
 * Loads the currency list, looks up the current rate and draws the historical
 * chart, talking only to the module's own JSON endpoints. Initialised from the
 * template via data-mage-init, so it receives (config, element).
 */
define(['jquery', 'chartjs', 'mage/translate'], function ($, Chart, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $from = $root.find('[data-role="from"]'),
            $to = $root.find('[data-role="to"]'),
            $swap = $root.find('[data-role="swap"]'),
            $rate = $root.find('[data-role="rate"]'),
            $asOf = $root.find('[data-role="asof"]'),
            $message = $root.find('[data-role="message"]'),
            canvas = $root.find('[data-role="chart"]').get(0),
            chart = null;

        function showError(text) {
            // Accumulate distinct messages so that a rate failure and a history
            // failure in the same refresh are both shown, not overwritten.
            var seen = $message.data('errors') || [];

            if (seen.indexOf(text) === -1) {
                seen.push(text);
                $message.data('errors', seen);
            }
            $message.empty();
            seen.forEach(function (line) {
                $message.append($('<div></div>').text(line));
            });
            $message.addClass('_show');
        }

        function clearError() {
            $message.removeClass('_show').removeData('errors').empty();
        }

        function clearChart() {
            if (chart) {
                chart.destroy();
                chart = null;
            }
        }

        // Our endpoints answer errors with HTTP 422/500 and a JSON body, so jQuery
        // routes them to the fail handler — surface the server's own message there.
        function errorFrom(jqXHR, fallback) {
            return (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) || fallback;
        }

        function buildOptions(currencies) {
            return Object.keys(currencies).map(function (code) {
                return $('<option></option>').val(code).text(code + ' — ' + currencies[code]);
            });
        }

        function loadCurrencies() {
            return $.getJSON(config.endpoints.currencies).then(function (response) {
                if (!response.success) {
                    showError(response.message);
                    return;
                }
                // buildOptions() returns fresh nodes each call, so the two
                // selects get independent option elements.
                $from.empty().append(buildOptions(response.currencies));
                $to.empty().append(buildOptions(response.currencies));
                $from.val(config.defaultFrom);
                $to.val(config.defaultTo);
            }, function (jqXHR) {
                showError(errorFrom(jqXHR, $t('Could not load the currency list.')));
            });
        }

        function loadRate(from, to) {
            $rate.text($t('Loading…'));
            $asOf.empty();
            return $.getJSON(config.endpoints.rate, {from: from, to: to}).then(function (response) {
                if (!response.success) {
                    $rate.empty();
                    showError(response.message);
                    return;
                }
                var rate = response.rate;
                $rate.text('1 ' + rate.base + ' = ' + rate.rate + ' ' + rate.target);
                $asOf.text(rate.date ? $t('As of') + ' ' + rate.date : '');
            }, function (jqXHR) {
                $rate.empty();
                showError(errorFrom(jqXHR, $t('Could not load the current rate.')));
            });
        }

        function loadHistory(from, to) {
            return $.getJSON(config.endpoints.history, {from: from, to: to}).then(function (response) {
                if (!response.success) {
                    clearChart();
                    showError(response.message);
                    return;
                }
                drawChart(response.labels, response.values, from + ' → ' + to);
            }, function (jqXHR) {
                clearChart();
                showError(errorFrom(jqXHR, $t('Could not load the historical rates.')));
            });
        }

        function drawChart(labels, values, label) {
            if (!canvas) {
                return;
            }
            if (chart) {
                chart.destroy();
            }
            chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        borderColor: '#1979c3',
                        backgroundColor: 'rgba(25, 121, 195, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {y: {beginAtZero: false}}
                }
            });
        }

        function refresh() {
            var from = $from.val(),
                to = $to.val();

            if (!from || !to) {
                return;
            }
            clearError();
            loadRate(from, to);
            loadHistory(from, to);
        }

        $from.on('change', refresh);
        $to.on('change', refresh);
        $swap.on('click', function (event) {
            event.preventDefault();
            var current = $from.val();
            $from.val($to.val());
            $to.val(current);
            refresh();
        });

        $.when(loadCurrencies()).always(refresh);
    };
});
