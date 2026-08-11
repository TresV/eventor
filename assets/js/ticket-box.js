(function ($) {
    'use strict';

    const selectors = {
        form: '.evt-ticket-box__form',
    };

    function initTicketBox($scope) {
        const $form = $scope.find(selectors.form);
        if (!$form.length) {
            return;
        }

        const $eventSelect = $form.find('[name="evt_event_id"]').first();
        const $timeslotWrap = $form.find('.evt-ticket-box__timeslot');
        const $timeslotSelect = $form.find('[name="evt_timeslot_id"]');
        const $quantityWrap = $form.find('.evt-ticket-box__quantity');
        const $quantityInput = $form.find('[name="evt_ticket_quantity"]');

        const $wrap = $form.closest('.evt-ticket-box');
        const inlineI18nRaw = ($wrap && $wrap.attr('data-i18n')) ? $wrap.attr('data-i18n') : '{}';
        let inlineI18n = {};
        try { inlineI18n = JSON.parse(inlineI18nRaw || '{}') || {}; } catch (e) { inlineI18n = {}; }
        const i18n = Object.assign({}, (EvtTicketsBox && EvtTicketsBox.i18n ? EvtTicketsBox.i18n : {}), inlineI18n);

        function resetTimeslots() {
            if (!$timeslotSelect.length || !$timeslotWrap.length) {
                return;
            }

            const placeholder = $timeslotSelect.data('placeholder') || 'Select a timeslot';
            $timeslotSelect.empty().append($('<option/>').attr('value', '').text(placeholder));
            $timeslotSelect.prop('required', false);
            $timeslotWrap.hide();
        }

        function applyLimit(limitOne, maxPerEmail) {
            if (!$quantityWrap.length || !$quantityInput.length) {
                return;
            }
            if (limitOne || (maxPerEmail && Number(maxPerEmail) === 1)) {
                $quantityInput.val('1');
                $quantityWrap.hide();
            } else {
                $quantityWrap.show();
            }
        }

        function populateTimeslots(slots, required, limitOne, maxPerEmail) {
            if (!$timeslotSelect.length || !$timeslotWrap.length) {
                return;
            }

            resetTimeslots();

            if (!Array.isArray(slots) || slots.length === 0) {
                return;
            }

            slots.forEach(function (slot) {
                if (!slot || !slot.id) {
                    return;
                }
                $timeslotSelect.append(
                    $('<option/>').attr('value', slot.id).text(slot.label || slot.id)
                );
            });

            $timeslotSelect.prop('required', !!required);
            $timeslotWrap.show();
            applyLimit(!!limitOne, maxPerEmail);
        }

        function getEventId() {
            const hidden = $form.find('input[type="hidden"][name="evt_event_id"]');
            if (hidden.length && hidden.val()) {
                return hidden.val();
            }
            return $eventSelect.val();
        }

        function fetchTimeslots(eventId) {
            resetTimeslots();

            if (!eventId) {
                applyLimit(false, 0);
                return;
            }

            $.post(EvtTicketsBox.ajaxUrl, {
                action: 'evt_ticket_box_timeslots',
                nonce: EvtTicketsBox.nonce,
                event_id: eventId,
            }).done(function (response) {
                const slots = response && response.data && response.data.slots ? response.data.slots : [];
                const required = !!(response && response.data && response.data.required);
                const limitOne = !!(response && response.data && response.data.limit_one);
                const maxPerEmail = response && response.data && response.data.max_per_email ? response.data.max_per_email : 0;
                populateTimeslots(slots, required, limitOne, maxPerEmail);
            }).fail(function () {
                resetTimeslots();
                applyLimit(false, 0);
            });
        }

        const prefillEventId = ($wrap && $wrap.data('prefillEvent')) ? String($wrap.data('prefillEvent')) : '';
        const prefillLock = ($wrap && $wrap.data('prefillLock')) ? true : false;

        if ($eventSelect.length) {
            $eventSelect.on('change', function () {
                fetchTimeslots($(this).val());
            });

            if (prefillEventId) {
                $eventSelect.val(prefillEventId);
                fetchTimeslots(prefillEventId);
                if (prefillLock) {
                    $eventSelect.prop('disabled', true);
                    $form.find('.evt-ticket-box__event').addClass('is-locked');
                }
            }
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            const $btn = $form.find('button[type="submit"]');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text(i18n.submitting || 'Submitting…');

            const data = {
                action: 'evt_ticket_box_submit',
                nonce: EvtTicketsBox.nonce,
                event_id: getEventId(),
                timeslot_id: $form.find('[name="evt_timeslot_id"]').val(),
                quantity: $form.find('[name="evt_ticket_quantity"]').val(),
                attendee_name: $form.find('[name="evt_attendee_name"]').val(),
                attendee_phone: $form.find('[name="evt_attendee_phone"]').val(),
                attendee_email: $form.find('[name="evt_attendee_email"]').val(),
                evt_website: $form.find('[name="evt_website"]').val(),
                current_url: window.location.href || '',
            };

            $.post(EvtTicketsBox.ajaxUrl, data)
                .done(function (response) {
                    if (response && response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                        return;
                    }

                    let message = (response && response.data && response.data.message) || '';
                    if (!message) {
                        message = i18n.success || 'Success';
                    }
                    $form.find('.evt-ticket-box__notice').removeClass('error').addClass('success').text(message).show();
                    $form.trigger('reset');
                    resetTimeslots();
                })
                .fail(function (xhr) {
                    const message = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || i18n.error || 'Error';
                    $form.find('.evt-ticket-box__notice').removeClass('success').addClass('error').text(message).show();
                })
                .always(function () {
                    $btn.prop('disabled', false).text(originalText);
                });
        });
    }

    $(window).on('elementor/frontend/init', function () {
        if (!window.elementorFrontend) {
            return;
        }

        elementorFrontend.hooks.addAction('frontend/element_ready/evt_ticket_box.default', initTicketBox);
    });
})(jQuery);
