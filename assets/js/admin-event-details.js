(function ($) {
    'use strict';

    function bindToggle() {
        $('.evt-event-toggle input[type="checkbox"][data-toggle-target]').each(function () {
            var $cb = $(this);
            var selector = $cb.data('toggleTarget');
            if (!selector) {
                return;
            }

            var $target = $(selector);
            if (!$target.length) {
                return;
            }

            var sync = function () {
                $target.toggleClass('is-hidden', !$cb.is(':checked'));
            };

            $cb.on('change', sync);
            sync();
        });
    }

    function bindSectionHashOpen() {
        var hash = window.location.hash;
        if (!hash) {
            return;
        }

        var node = document.querySelector(hash);
        if (!node || node.tagName !== 'DETAILS') {
            return;
        }

        node.open = true;
    }

    function bindSectionNav() {
        $('.evt-event-editor__nav-item').on('click', function () {
            var href = $(this).attr('href');
            if (!href || href.charAt(0) !== '#') {
                return;
            }

            var node = document.querySelector(href);
            if (node && node.tagName === 'DETAILS') {
                node.open = true;
            }
        });
    }

    $(function () {
        bindToggle();
        bindSectionHashOpen();
        bindSectionNav();
    });
})(jQuery);
