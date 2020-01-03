var Dashboard = function() {
    return {
        initDashboardDaterange: function() {
            if (!jQuery().daterangepicker) {
                return;
            }
            $('#dashboard-report-range span').html(moment().subtract('days', 29).format('MMMM D, YYYY') + ' - ' + moment().format('MMMM D, YYYY'));
            $('#dashboard-report-range').show();
        },

        init: function() {
            this.initDashboardDaterange();
        }
    };
}();

jQuery(document).ready(function() {
   Dashboard.init();
});