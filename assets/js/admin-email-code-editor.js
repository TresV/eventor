(function ($) {
  function initEditor(selector, settings) {
    var $el = $(selector);
    if (!$el.length) return null;
    if (typeof wp === "undefined" || !wp.codeEditor || !settings) return null;

    try {
      var instance = wp.codeEditor.initialize($el, settings);
      if (instance && instance.codemirror) {
        instance.codemirror.on("change", function () {
          instance.codemirror.save();
          $el.trigger("input").trigger("change");
        });
      }
      return instance;
    } catch (e) {
      return null;
    }
  }

  $(function () {
    if (typeof EvtTicketsEmailCodeEditor === "undefined") return;

    initEditor('textarea[name="evt_tickets_settings[email_custom_template_html]"]', EvtTicketsEmailCodeEditor.htmlSettings);
    initEditor('textarea[name="evt_tickets_settings[email_custom_template_css]"]', EvtTicketsEmailCodeEditor.cssSettings);
  });
})(jQuery);

