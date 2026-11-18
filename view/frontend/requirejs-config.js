/**
 * Chart.js is loaded through RequireJS as the "chartjs" module.
 *
 * For an air-gapped or fully self-hosted deployment, drop the UMD build into
 * view/frontend/web/js/lib/ and point this path at it instead of the CDN.
 */
var config = {
    paths: {
        chartjs: 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min'
    },
    shim: {
        chartjs: {
            exports: 'Chart'
        }
    }
};
