(function ($) {
  function replaceTokens(input, tokens) {
    if (!input) return "";
    return String(input).replace(/\{([a-z0-9_]+)\}/gi, function (_, key) {
      return Object.prototype.hasOwnProperty.call(tokens, key) ? String(tokens[key]) : "{" + key + "}";
    });
  }

  function readJsonData() {
    var el = document.getElementById("evt-email-presets-data");
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || "{}");
    } catch (e) {
      return null;
    }
  }

  function getBrandingTokens(data) {
    var primary = $('input[name="evt_tickets_email_preset_branding[primary_color]"]').val() || (data && data.defaults && data.defaults.primary_color) || "#111827";
    var accent = $('input[name="evt_tickets_email_preset_branding[accent_color]"]').val() || (data && data.defaults && data.defaults.accent_color) || "#7C3AED";
    var bg = $('input[name="evt_tickets_email_preset_branding[background_color]"]').val() || (data && data.defaults && data.defaults.background_color) || "#F3F4F6";
    var showLogo = $('input[name="evt_tickets_email_preset_branding[show_logo]"]').is(":checked");
    var logoUrl = (data && data.sample && data.sample.logo_url) || "";

    return {
      primary_color: String(primary),
      accent_color: String(accent),
      background_color: String(bg),
      logo_url: showLogo ? String(logoUrl) : "",
      logo_display: showLogo && logoUrl ? "block" : "none",
    };
  }

  function buildSrcdoc(css, html) {
    var out = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>';
    if (css && String(css).trim()) out += "<style>" + css + "</style>";
    out += "</head><body>" + (html || "") + "</body></html>";
    return out;
  }

  function refreshPreviews() {
    var data = readJsonData();
    if (!data || !data.templates) return;

    var baseTokens = $.extend({}, data.sample || {});
    var branding = getBrandingTokens(data);
    var tokens = $.extend(baseTokens, branding);

    $(".evt-email-preset-preview").each(function () {
      var $iframe = $(this);
      var preset = $iframe.data("preset");
      if (!preset || !data.templates[preset]) return;

      var css = replaceTokens(data.templates[preset].css || "", tokens);
      var html = replaceTokens(data.templates[preset].html || "", tokens);
      $iframe.attr("srcdoc", buildSrcdoc(css, html));
    });
  }

  function initColorPickers() {
    $(".evt-email-color-field").each(function () {
      var $field = $(this);
      if ($field.data("wpColorPicker")) return;
      $field.wpColorPicker({
        change: function () {
          refreshPreviews();
        },
        clear: function () {
          refreshPreviews();
        },
      });
    });
  }

  function initAccordionBehavior() {
    $(document).on("change", 'input[name="evt_tickets_email_preset"]', function () {
      var val = $(this).val();
      $(".evt-email-preset-item").each(function () {
        var $details = $(this);
        if ($details.data("preset") === val) {
          $details.prop("open", true);
        } else {
          $details.prop("open", false);
        }
      });
    });
  }

  $(function () {
    initColorPickers();
    initAccordionBehavior();
    refreshPreviews();

    $(document).on("change input", 'input[name="evt_tickets_email_preset_branding[show_logo]"]', function () {
      refreshPreviews();
    });
  });
})(jQuery);

