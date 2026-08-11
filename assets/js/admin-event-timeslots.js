(function ($) {
    'use strict';

    function renderTemplate($wrap) {
        const $tpl = $wrap.find('template[data-timeslot-template]');
        if (! $tpl.length) {
            return '';
        }

        const html = $tpl.html() || '';
        if (! html) {
            return '';
        }

        const idx = $wrap.data('evtTimeslotIdx') || 0;
        $wrap.data('evtTimeslotIdx', idx + 1);

        return html.replace(/__INDEX__/g, String(idx));
    }

    function init() {
        const $wrap = $('[data-timeslots]');
        if (! $wrap.length) {
            return;
        }

        // Seed index based on existing rows count.
        const seed = $wrap.find('[data-row]').length || 0;
        $wrap.data('evtTimeslotIdx', seed);

        $wrap.on('click', '[data-add-timeslot]', function () {
            const html = renderTemplate($wrap);
            if (! html) {
                return;
            }
            $wrap.find('[data-rows]').append(html);
            if (window.evtTicketsInitDatetimePickers) {
                window.evtTicketsInitDatetimePickers($wrap);
            }
        });

        $wrap.on('click', '[data-remove-timeslot]', function () {
            const $row = $(this).closest('[data-row]');
            if (! $row.length) {
                return;
            }

            const $rows = $wrap.find('[data-row]');
            if ($rows.length <= 1) {
                // Keep at least one row so the UI stays visible.
                $row.find('input').val('');
                return;
            }

            $row.remove();
        });
    }

    $(init);
})(jQuery);
