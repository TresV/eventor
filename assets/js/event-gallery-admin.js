(function ($) {
    'use strict';

    const i18n = Object.assign(
        {
            title: 'Event Gallery',
            buttonUse: 'Use selected images',
            empty: 'No gallery images selected.'
        },
        window.evtEventGalleryMetaI18n || {}
    );

    function renderPreview($wrap, ids) {
        const $preview = $wrap.find('.evt-event-gallery-meta__preview');
        const thumbs = [];

        ids.forEach(function (id) {
            const attachment = wp.media.attachment(id);
            if (!attachment) {
                return;
            }

            const attrs = attachment.attributes || {};
            const sizes = attrs.sizes || {};
            const thumb = (sizes.thumbnail && sizes.thumbnail.url) || attrs.url || '';
            if (!thumb) {
                return;
            }

            thumbs.push(
                '<div class="evt-event-gallery-meta__thumb" data-id="' + String(id) + '">' +
                    '<img src="' + String(thumb) + '" alt="" style="width:80px;height:80px;object-fit:cover;border:1px solid #ccd0d4;border-radius:4px;" />' +
                '</div>'
            );
        });

        if (thumbs.length) {
            $preview.html(thumbs.join(''));
            return;
        }

        $preview.html('<em class="evt-event-gallery-meta__empty">' + String(i18n.empty) + '</em>');
    }

    function parseIds($field) {
        const raw = ($field.val() || '').trim();
        if (!raw) {
            return [];
        }

        return raw
            .split(',')
            .map(function (part) { return parseInt(part, 10); })
            .filter(function (id) { return Number.isFinite(id) && id > 0; });
    }

    function bindGalleryField($wrap) {
        const selector = $wrap.data('field');
        if (!selector) {
            return;
        }

        const $field = $(selector);
        if (!$field.length) {
            return;
        }

        let frame = null;

        $wrap.on('click', '.evt-event-gallery-meta__add', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: i18n.title,
                library: { type: 'image' },
                button: { text: i18n.buttonUse },
                multiple: true
            });

            frame.on('open', function () {
                const selection = frame.state().get('selection');
                const ids = parseIds($field);
                ids.forEach(function (id) {
                    const attachment = wp.media.attachment(id);
                    attachment.fetch();
                    selection.add(attachment ? [attachment] : []);
                });
            });

            frame.on('select', function () {
                const selection = frame.state().get('selection');
                const ids = [];
                selection.each(function (model) {
                    const id = parseInt(model.get('id'), 10);
                    if (Number.isFinite(id) && id > 0) {
                        ids.push(id);
                    }
                });

                $field.val(ids.join(','));
                renderPreview($wrap, ids);
            });

            frame.open();
        });

        $wrap.on('click', '.evt-event-gallery-meta__clear', function (e) {
            e.preventDefault();
            $field.val('');
            renderPreview($wrap, []);
        });
    }

    $(function () {
        $('.evt-event-gallery-meta').each(function () {
            bindGalleryField($(this));
        });
    });
})(jQuery);
