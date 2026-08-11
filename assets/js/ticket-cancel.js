(function ($) {
  function setMessage($wrap, type, text) {
    $wrap.removeClass("is-success is-error").addClass(type === "success" ? "is-success" : "is-error");
    $wrap.text(text || "");
  }

  $(document).on("submit", ".evt-ticket-cancel__form", function (e) {
    e.preventDefault();

    if (typeof EvtTicketsCancel === "undefined") return;

    var $form = $(this);
    var $wrap = $form.closest(".evt-ticket-cancel");
    var $msg = $form.find(".evt-ticket-cancel__message");
    var $btn = $form.find("button[type=submit]");
    var originalBtnText = $btn.text();

    setMessage($msg, "success", "");

    var inlineI18n = {};
    try {
      inlineI18n = JSON.parse(($wrap.attr("data-i18n") || "{}")) || {};
    } catch (e2) {
      inlineI18n = {};
    }
    var i18n = Object.assign({}, (EvtTicketsCancel.i18n || {}), inlineI18n);
    $btn.prop("disabled", true).text((i18n && i18n.submitting) || "Submitting…");

    var ticketCode = ($form.find('input[name="ticket_code"]').val() || "").trim();
    var email = ($form.find('input[name="email"]').val() || "").trim();
    var reason = ($form.find('input[name="reason"]').val() || "").trim();
    var refund = $form.find('input[name="refund_request"]').is(":checked");
    var sig = ($form.find('input[name="sig"]').val() || "").trim();
    var ts = ($form.find('input[name="ts"]').val() || "").trim();

    $.ajax({
      url: EvtTicketsCancel.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action: "evt_ticket_cancel",
        nonce: EvtTicketsCancel.nonce,
        ticket_code: ticketCode,
        email: email,
        sig: sig,
        ts: ts,
        reason: reason,
        mode: refund ? "refund_request" : "cancel_only",
      },
    })
      .done(function (resp) {
        if (resp && resp.success) {
          // Email-match path: no signature was provided, so the server sent a
          // signed confirmation link to the attendee's inbox instead of
          // cancelling directly.
          if (resp.data && resp.data.confirmation_sent) {
            var confirmMsg =
              (resp.data && resp.data.message) ||
              (i18n && i18n.confirmation_sent) ||
              "A confirmation link has been sent to your email.";
            setMessage($msg, "success", confirmMsg);
            $btn.prop("disabled", true);
            return;
          }
          var extra = "";
          if (resp.data && resp.data.status) {
            extra += " (" + String(resp.data.status) + ")";
          }
          if (resp.data && resp.data.cancelled_at) {
            extra += " — " + String(resp.data.cancelled_at);
          }
          setMessage($msg, "success", ((i18n && i18n.success) || "Ticket cancelled.") + extra);
          $btn.prop("disabled", true);
          return;
        }
        var msg = (resp && resp.data && resp.data.message) || ((i18n && i18n.error) || "Unable to cancel ticket.");
        setMessage($msg, "error", msg);
        $btn.prop("disabled", false).text(originalBtnText);
      })
      .fail(function (xhr) {
        var msg =
          (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
          ((i18n && i18n.error) || "Unable to cancel ticket.");
        setMessage($msg, "error", msg);
        $btn.prop("disabled", false).text(originalBtnText);
      });
  });
})(jQuery);
