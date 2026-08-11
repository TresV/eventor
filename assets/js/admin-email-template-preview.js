(function ($) {
  function replaceTokens(input, tokens) {
    if (!input) return "";
    return input.replace(/\{([a-z0-9_]+)\}/gi, function (_, key) {
      return Object.prototype.hasOwnProperty.call(tokens, key) ? String(tokens[key]) : "{" + key + "}";
    });
  }

  function buildSampleTokens() {
    return {
      event_name: "Sample Event",
      event_start: "2026-06-24 10:00",
      event_end: "2026-06-24 18:00",
      event_location: "Sample Venue, Sample City",
      event_map_url: "https://maps.google.com/?q=Sample+Venue",
      ticket_code: "EVT-ABCDEFG123",
      attendee_name: "Viktor",
      attendee_email: "viktor@example.com",
      verify_url: "https://example.com/ticket-checkin/?code=EVT-ABCDEFG123",
      ics_url: "https://example.com/ticket-ics/EVT-ABCDEFG123/000/xyz.ics",
      google_cal_url: "https://www.google.com/calendar/render?action=TEMPLATE",
      pdf_url: "https://example.com/?evt_ticket_pdf=EVT-ABCDEFG123&sig=xyz",
      cancel_url: "https://example.com/ticket-cancel/?ticket_code=EVT-ABCDEFG123&email=viktor%40example.com&ts=1700000000&sig=xyz",
      tickets_html:
        '<div style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:6px;"><strong>Sample Event</strong> — EVT-ABCDEFG123</div>',
    };
  }

  function refreshPreview() {
    var $enabled = $('input[name="evt_tickets_settings[email_use_custom_template]"]');
    var enabled = $enabled.length ? $enabled.is(":checked") : false;
    var $iframe = $("#evt-email-template-preview iframe");
    if (!$iframe.length) return;

    var doc = $iframe[0].contentWindow.document;
    if (!enabled) {
      doc.open();
      doc.write(
        '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;padding:12px;">Enable <strong>Use Custom Email Template</strong> to preview your HTML/CSS.</div>'
      );
      doc.close();
      return;
    }

    var css = $('textarea[name="evt_tickets_settings[email_custom_template_css]"]').val() || "";
    var html = $('textarea[name="evt_tickets_settings[email_custom_template_html]"]').val() || "";

    var tokens = buildSampleTokens();
    css = replaceTokens(css, tokens);
    html = replaceTokens(html, tokens);

    doc.open();
    doc.write("<!doctype html><html><head><meta charset='utf-8'/>");
    if (css.trim()) {
      doc.write("<style>" + css + "</style>");
    }
    doc.write("</head><body>" + html + "</body></html>");
    doc.close();
  }

  $(document).on(
    "input change",
    'input[name="evt_tickets_settings[email_use_custom_template]"], textarea[name="evt_tickets_settings[email_custom_template_css]"], textarea[name="evt_tickets_settings[email_custom_template_html]"]',
    function () {
      refreshPreview();
    }
  );

  $(function () {
    refreshPreview();
  });
})(jQuery);
