(function ($) {
    'use strict';

    $(function () {
        if (!$.fn.wpColorPicker) {
            return;
        }

        $('.evt-color-field').each(function () {
            var $field = $(this);
            if ($field.data('wpColorPicker')) {
                return;
            }
            $field.wpColorPicker();
        });
    });
})(jQuery);

