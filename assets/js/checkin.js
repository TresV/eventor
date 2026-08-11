(function ($) {
  'use strict';

  function evtText(fallback) {
    return fallback;
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttr(str) {
    return escapeHtml(str);
  }

  function renderStatus($container, status) {
    if (!$container.length) return;

    if (!status || !status.type) {
      $container.empty();
      return;
    }

    var type = status.type || 'info';
    var title = status.title || '';
    var body = status.body || '';

    var className = 'evt-checkin-status evt-checkin-status-' + type;
    var html = '<div class="' + className + '">';

    if (title) {
      html += '<strong class="evt-checkin-status-title">' + escapeHtml(title) + '</strong>';
    }
    if (body) {
      html += '<p class="evt-checkin-status-body">' + escapeHtml(body) + '</p>';
    }

    html += '</div>';

    $container.html(html);
  }

  function renderTicketDetails($container, ticket) {
    if (!$container.length) return;

    if (!ticket || !ticket.id) {
      $container.empty();
      return;
    }

    var statusLabel;

    if (ticket.status === 'checked_in') {
      statusLabel = EvtTicketsCheckin.i18n.checkedIn || evtText('Checked in');
    } else {
      statusLabel = evtText('Pending');
    }

    var checkedInRow = '';
    if (ticket.checked_in_at) {
      checkedInRow =
        '<li><strong>' + escapeHtml(evtText('Checked-in at:')) + '</strong> ' +
        escapeHtml(ticket.checked_in_at) + '</li>';
    }

    var showConfirm = ticket.status === 'pending';

    var html = '';
    html += '<div class="evt-checkin-ticket-details">';
    html += '  <h3>' + escapeHtml(evtText('Ticket details')) + '</h3>';
    html += '  <ul>';
    html += '    <li><strong>' + escapeHtml(evtText('Code:')) + '</strong> ' + escapeHtml(ticket.code || '') + '</li>';
    html += '    <li><strong>' + escapeHtml(evtText('Name:')) + '</strong> ' + escapeHtml(ticket.name || '') + '</li>';
    html += '    <li><strong>' + escapeHtml(evtText('Email:')) + '</strong> ' + escapeHtml(ticket.email || '') + '</li>';
    html += '    <li><strong>' + escapeHtml(evtText('Event:')) + '</strong> ' + escapeHtml(ticket.event_name || '') + '</li>';
    html += '    <li><strong>' + escapeHtml(evtText('Status:')) + '</strong> ' + escapeHtml(statusLabel) + '</li>';
    if (checkedInRow) {
      html += checkedInRow;
    }
    html += '  </ul>';
    html += '</div>';

    if (showConfirm) {
      html += '<form method="post" class="evt-checkin-form evt-checkin-form-confirm">';
      html += '  <input type="hidden" name="ticket_id" value="' + escapeAttr(ticket.id) + '"/>';
      html += '  <button type="submit" name="evt_confirm_checkin" class="evt-checkin-button evt-checkin-button-confirm">';
      html +=      escapeHtml(evtText('Confirm Check-In'));
      html += '  </button>';
      html += '</form>';
    }

    $container.html(html);
  }

  /**
   * Try to normalize whatever was scanned into a clean ticket code.
   *
   * - If it's a full URL with ?code=XYZ -> return XYZ
   * - Otherwise, return the trimmed string as-is
   */
  function normalizeScannedCode(raw) {
    if (!raw) return '';

    var text = String(raw).trim();

    // First, try treating it as a URL.
    try {
      var asUrl = new URL(text);
      var codeParam = asUrl.searchParams.get('code');
      if (codeParam) {
        return codeParam.trim();
      }
    } catch (e) {
      // Not a valid URL, ignore and continue.
    }

    // If not a URL, just return the trimmed value (maybe the QR encodes only the code).
    return text;
  }

  /**
   * Initialize html5-qrcode scanner for a given wrapper.
   *
   * When a QR code is decoded, we:
   * - Normalize it to a ticket code
   * - Fill the ticket code input
   * - Trigger an AJAX lookup
   */
  function initQrScannerForWrapper($wrapper, onCodeScanned) {
    var $qrArea = $wrapper.find('.evt-checkin-qr-area');
    if (!$qrArea.length) return;

    // If library is missing, show a soft message and bail.
    if (typeof Html5Qrcode === 'undefined') {
      $qrArea.html(
        '<div class="evt-checkin-qr-unavailable">' +
          '<p>' + escapeHtml(EvtTicketsCheckin.i18n.scannerUnavailable) + '</p>' +
        '</div>'
      );
      return;
    }

    var containerId = 'evt-qr-reader-' + Math.random().toString(36).substr(2, 9);

    var html = '';
    html += '<div class="evt-checkin-qr-controls">';
    html += '  <button type="button" class="evt-checkin-qr-button-start">' +
              escapeHtml(evtText('Start camera scanner')) +
            '</button>';
    html += '  <button type="button" class="evt-checkin-qr-button-stop" disabled>' +
              escapeHtml(evtText('Stop camera')) +
            '</button>';
    html += '</div>';
    html += '<div class="evt-checkin-qr-reader-wrapper">';
    html += '  <div id="' + containerId + '" class="evt-checkin-qr-reader"></div>';
    html += '  <p class="evt-checkin-qr-hint">' +
              escapeHtml(EvtTicketsCheckin.i18n.scannerHint) +
            '</p>';
    html += '  <div class="evt-checkin-qr-status"></div>';
    html += '</div>';

    $qrArea.html(html);

    var $startBtn = $qrArea.find('.evt-checkin-qr-button-start');
    var $stopBtn  = $qrArea.find('.evt-checkin-qr-button-stop');
    var $status   = $qrArea.find('.evt-checkin-qr-status');

    var html5QrCode = null;
    var isRunning   = false;

    function setStatusText(text) {
      $status.text(text || '');
    }

    function setButtonsState(running) {
      isRunning = running;
      $startBtn.prop('disabled', running);
      $stopBtn.prop('disabled', !running);
    }

    function startScanner() {
      if (isRunning) return;

      if (!html5QrCode) {
        html5QrCode = new Html5Qrcode(containerId);
      }

      setStatusText(EvtTicketsCheckin.i18n.scannerStarting);
      setButtonsState(true);

      var config = {
        fps: 10,
        qrbox: { width: 250, height: 250 }
      };

      html5QrCode
        .start(
          { facingMode: 'environment' },
          config,
          function onScanSuccess(decodedText /*, decodedResult */) {
            if (!decodedText) return;

            // Normalize to just the ticket code.
            var code = normalizeScannedCode(decodedText);

            // If we didn't get anything meaningful, stop and show a message.
            if (!code) {
              setStatusText(EvtTicketsCheckin.i18n.genericErrorBody);
              return;
            }

            // Stop scanning once we get a code to avoid duplicates.
            stopScanner();

            // Pass code to the callback so the wrapper can do AJAX lookup.
            onCodeScanned(code);
          },
          function onScanError(/* errorMessage */) {
            // Ignore continuous decoding errors to avoid log spam.
          }
        )
        .then(function () {
          setStatusText(EvtTicketsCheckin.i18n.scannerHint);
        })
        .catch(function (error) {
          setStatusText(error || EvtTicketsCheckin.i18n.genericErrorBody);
          setButtonsState(false);
        });
    }

    function stopScanner() {
      if (!html5QrCode || !isRunning) {
        setButtonsState(false);
        setStatusText('');
        return;
      }

      setStatusText(EvtTicketsCheckin.i18n.scannerStopping);

      html5QrCode
        .stop()
        .then(function () {
          return html5QrCode.clear();
        })
        .then(function () {
          setButtonsState(false);
          setStatusText('');
        })
        .catch(function () {
          setButtonsState(false);
          setStatusText('');
        });
    }

    $startBtn.on('click', function () {
      startScanner();
    });

    $stopBtn.on('click', function () {
      stopScanner();
    });

    // Clean up if needed on page unload.
    $(window).on('beforeunload', function () {
      try {
        stopScanner();
      } catch (e) {
        // ignore
      }
    });
  }

  $(function () {
    if (typeof EvtTicketsCheckin === 'undefined') {
      return;
    }

    var ajaxUrl = EvtTicketsCheckin.ajaxUrl;
    var nonce   = EvtTicketsCheckin.nonce;

    $('.evt-checkin-wrapper').each(function () {
      var $wrapper          = $(this);
      var $statusContainer  = $wrapper.find('.evt-checkin-status-container');
      var $ticketContainer  = $wrapper.find('.evt-checkin-ticket-container');
      var $searchForm       = $wrapper.find('.evt-checkin-form-search');
      var $searchInput      = $searchForm.find('input[name="ticket_code"]');
      var submitting        = false;

      function parseResponse(raw) {
        // If it's already an object with "success", just return it.
        if (raw && typeof raw === 'object' && typeof raw.success !== 'undefined') {
          return raw;
        }

        // If it's a string, try to parse JSON (in case something messed with headers).
        if (typeof raw === 'string') {
          try {
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed.success !== 'undefined') {
              return parsed;
            }
          } catch (e) {
            // Not valid JSON.
          }
        }

        // Fallback: return null so we show generic error.
        return null;
      }

      function handleResponse(raw) {
        var response = parseResponse(raw);

        if (!response) {
          renderStatus($statusContainer, {
            type: 'error',
            title: EvtTicketsCheckin.i18n.genericErrorTitle,
            body: EvtTicketsCheckin.i18n.genericErrorBody
          });
          $ticketContainer.empty();
          return;
        }

        if (!response.success) {
          var msg = (response.data && response.data.message)
            ? response.data.message
            : EvtTicketsCheckin.i18n.genericErrorBody;

          renderStatus($statusContainer, {
            type: 'error',
            title: EvtTicketsCheckin.i18n.genericErrorTitle,
            body: msg
          });
          $ticketContainer.empty();
          return;
        }

        var payload = response.data || {};
        renderStatus($statusContainer, payload.status);
        renderTicketDetails($ticketContainer, payload.ticket);
      }

      function submitAjax(mode, extraData) {
        if (submitting) return;
        submitting = true;

        var $submitButton;
        if (mode === 'confirm') {
          $submitButton = $wrapper.find('.evt-checkin-form-confirm button[type="submit"]');
        } else {
          $submitButton = $searchForm.find('button[type="submit"]');
        }

        if ($submitButton.length) {
          $submitButton.prop('disabled', true);
        }

        var data = {
          action: 'evt_tickets_checkin',
          nonce: nonce,
          mode: mode
        };

        if (extraData && typeof extraData === 'object') {
          Object.keys(extraData).forEach(function (key) {
            data[key] = extraData[key];
          });
        }

        $.post(ajaxUrl, data)
          .done(function (response) {
            handleResponse(response);
          })
          .fail(function () {
            renderStatus($statusContainer, {
              type: 'error',
              title: EvtTicketsCheckin.i18n.genericErrorTitle,
              body: EvtTicketsCheckin.i18n.genericErrorBody
            });
          })
          .always(function () {
            submitting = false;
            if ($submitButton && $submitButton.length) {
              $submitButton.prop('disabled', false);
            }
          });
      }

      // Intercept submit events from search & confirm forms inside this wrapper.
      $wrapper.on('submit', '.evt-checkin-form-search, .evt-checkin-form-confirm', function (e) {
        e.preventDefault();

        var $form = $(this);

        if ($form.hasClass('evt-checkin-form-confirm')) {
          var ticketId = $form.find('input[name="ticket_id"]').val();
          if (!ticketId) {
            renderStatus($statusContainer, {
              type: 'error',
              title: EvtTicketsCheckin.i18n.genericErrorTitle,
              body: EvtTicketsCheckin.i18n.missingTicketId
            });
            return;
          }
          submitAjax('confirm', { ticket_id: ticketId });
        } else {
          var code = $form.find('input[name="ticket_code"]').val();
          if (!code) {
            renderStatus($statusContainer, {
              type: 'error',
              title: EvtTicketsCheckin.i18n.genericErrorTitle,
              body: EvtTicketsCheckin.i18n.missingCode
            });
            return;
          }
          submitAjax('lookup', { ticket_code: code });
        }
      });

      // Auto-lookup if ?code= is in the URL.
      try {
        var url = new URL(window.location.href);
        var codeParam = url.searchParams.get('code');
        if (codeParam && !$searchInput.val()) {
          $searchInput.val(codeParam);
          submitAjax('lookup', { ticket_code: codeParam });
        }
      } catch (e) {
        // Ignore URL parsing errors.
      }

      // Initialize QR scanner (real html5-qrcode integration).
      initQrScannerForWrapper($wrapper, function (code) {
        if (!code) return;
        if ($searchInput.length) {
          $searchInput.val(code);
        }
        submitAjax('lookup', { ticket_code: code });
      });
    });
  });
})(jQuery);
