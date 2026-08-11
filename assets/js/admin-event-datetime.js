(function ($) {
    'use strict';

    function buildConfig() {
        var cfg = {
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d H:i',
            allowInput: true
        };

        if (window.evtTicketsFlatpickr && window.evtTicketsFlatpickr.time_24hr === false) {
            cfg.time_24hr = false;
        }

        var locale = window.evtTicketsFlatpickr && window.evtTicketsFlatpickr.locale
            ? window.evtTicketsFlatpickr.locale
            : '';

        if (locale && window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns[locale]) {
            cfg.locale = window.flatpickr.l10ns[locale];
        }

        return cfg;
    }

    function initDateTimePickers($root) {
        if (! window.flatpickr) {
            return;
        }

        var cfg = buildConfig();
        var $fields = ($root && $root.find) ? $root.find('.evt-datetime-field') : $('.evt-datetime-field');

        $fields.each(function () {
            if (this._evtFp) {
                return;
            }
            this._evtFp = flatpickr(this, cfg);
        });
    }

    window.evtTicketsInitDatetimePickers = initDateTimePickers;

    $(function () {
        initDateTimePickers($(document));
    });
})(jQuery);
