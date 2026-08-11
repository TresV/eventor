(function ($) {
    'use strict';

    function setStatus($el, message, isError) {
        if (!$el.length) {
            return;
        }
        $el.removeClass('is-error is-success');
        if (isError) {
            $el.addClass('is-error');
        } else {
            $el.addClass('is-success');
        }
        $el.text(message);
    }

    $(function () {
        if ($.fn.wpColorPicker) {
            $('.evt-color-field').wpColorPicker();
        }

        $('#evt-pdf-template').on('change', function () {
            var preset = $(this).val();
            $('.evt-wizard-pdf-preview').removeClass('is-active');
            $('.evt-wizard-pdf-preview[data-preset="' + preset + '"]').addClass('is-active');
        });

        $('.evt-wizard-pdf-preview').on('click', function () {
            var preset = $(this).data('preset');
            if (!preset) {
                return;
            }
            $('#evt-pdf-template').val(preset).trigger('change');
        });

        var $button = $('.evt-create-starter-pages');
        var $status = $('.evt-setup-wizard__status');

        if (!$button.length || typeof EvtSetupWizard === 'undefined') {
            return;
        }

        $button.on('click', function () {
            if ($button.prop('disabled')) {
                return;
            }

            $button.prop('disabled', true);
            setStatus($status, (EvtSetupWizard.i18n && EvtSetupWizard.i18n.creating) ? EvtSetupWizard.i18n.creating : 'Creating pages...', false);

            $.post(EvtSetupWizard.ajaxUrl, {
                action: 'evt_setup_wizard_create_pages',
                nonce: EvtSetupWizard.nonce
            }).done(function (response) {
                if (!response || !response.success) {
                    setStatus($status, (EvtSetupWizard.i18n && EvtSetupWizard.i18n.error) ? EvtSetupWizard.i18n.error : 'Could not create pages. Please try again.', true);
                    return;
                }

                setStatus($status, (EvtSetupWizard.i18n && EvtSetupWizard.i18n.success) ? EvtSetupWizard.i18n.success : 'Starter pages are ready.', false);
                window.location.reload();
            }).fail(function () {
                setStatus($status, (EvtSetupWizard.i18n && EvtSetupWizard.i18n.error) ? EvtSetupWizard.i18n.error : 'Could not create pages. Please try again.', true);
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
