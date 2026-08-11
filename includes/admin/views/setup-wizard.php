<?php
if (! defined('ABSPATH')) {
    exit;
}
// View variables ($step, $settings, …) are injected by the renderer via a
// controlled data array; they are not global state.
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Setup wizard admin view.
 *
 * @var string $step
 * @var array<string,string> $steps
 * @var array<string,mixed> $settings
 * @var array<string,mixed> $rules
 * @var int $saved
 * @var string $wizard_url
 * @var string $get_started_url
 * @var string $dashboard_url
 * @var string $settings_url
 * @var string $create_event_url
 * @var array<string,array{title:string,id:int,edit_url:string,view_url:string}> $starter_pages
 */

$step_keys = array_keys($steps);
$step_index = array_search($step, $step_keys, true);
if (false === $step_index) {
    $step_index = 0;
}
$prev_step = $step_index > 0 ? $step_keys[$step_index - 1] : '';
$next_step = isset($step_keys[$step_index + 1]) ? $step_keys[$step_index + 1] : '';

$from_name = isset($settings['from_name']) ? (string) $settings['from_name'] : wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
$from_email = isset($settings['from_email']) ? (string) $settings['from_email'] : (string) get_option('admin_email');
$subject = isset($settings['ticket_email_subject']) ? (string) $settings['ticket_email_subject'] : __('Your ticket for {event_name}', 'Event-Tickets-for-Elementor');
$greeting = isset($settings['email_greeting']) ? (string) $settings['email_greeting'] : __('Hi {attendee_name},', 'Event-Tickets-for-Elementor');
$intro = isset($settings['email_intro']) ? (string) $settings['email_intro'] : __('Thank you for registering for {event_name}.', 'Event-Tickets-for-Elementor');
$ticket_code_intro = isset($settings['email_ticket_code_intro']) ? (string) $settings['email_ticket_code_intro'] : __('Your ticket code is:', 'Event-Tickets-for-Elementor');
$attach_ics = empty($settings['email_attach_ics']) ? 0 : 1;
$email_preset_current = \EventTicketsElementor\Email\Email_Template_Presets::get_current();
$email_presets = \EventTicketsElementor\Email\Email_Template_Presets::presets();
$email_branding = \EventTicketsElementor\Email\Email_Preset_Branding::get_all();
$email_primary_color = isset($email_branding['primary_color']) ? (string) $email_branding['primary_color'] : '#111827';
$email_accent_color = isset($email_branding['accent_color']) ? (string) $email_branding['accent_color'] : '#7C3AED';
$pdf_template_preset = isset($settings['pdf_template_preset']) ? (string) $settings['pdf_template_preset'] : 'classic';
$pdf_allow_event_override = isset($settings['pdf_allow_event_override']) ? (int) $settings['pdf_allow_event_override'] : 1;
$pdf_show_logo = isset($settings['pdf_show_logo']) ? (int) $settings['pdf_show_logo'] : 1;
$pdf_accent = isset($settings['pdf_accent_color']) ? (string) $settings['pdf_accent_color'] : '#2563eb';
$pdf_attach = isset($settings['pdf_attach_to_emails']) ? (int) $settings['pdf_attach_to_emails'] : 1;
$enforce_conflicts = empty($rules['enforce_timeslot_exclusivity']) ? 0 : 1;
$buffer_minutes = isset($rules['timeslot_buffer_minutes']) ? (int) $rules['timeslot_buffer_minutes'] : 0;
?>
<div class="wrap evt-setup-wizard">
    <div class="evt-setup-wizard__container">
        <h1><?php esc_html_e('Event Tickets Setup Wizard', 'Event-Tickets-for-Elementor'); ?></h1>
        <p class="description"><?php esc_html_e('Configure the essentials first. You can refine everything later in Settings.', 'Event-Tickets-for-Elementor'); ?></p>

        <ol class="evt-setup-wizard__timeline" aria-label="<?php esc_attr_e('Setup steps', 'Event-Tickets-for-Elementor'); ?>">
            <?php foreach ($steps as $key => $label) : ?>
                <?php
                $index = array_search($key, $step_keys, true);
                $class = 'is-upcoming';
                if ($index < $step_index) {
                    $class = 'is-complete';
                } elseif ($key === $step) {
                    $class = 'is-active';
                }
                ?>
                <li class="<?php echo esc_attr($class); ?>">
                    <span class="evt-setup-wizard__dot" aria-hidden="true"></span>
                    <span class="evt-setup-wizard__label"><?php echo esc_html($label); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($saved) : ?>
            <div class="notice notice-success inline">
                <p><?php esc_html_e('Step saved.', 'Event-Tickets-for-Elementor'); ?></p>
            </div>
        <?php endif; ?>

        <div class="evt-setup-wizard__card">
            <?php if ('welcome' === $step) : ?>
                <h2><?php esc_html_e('Before You Start', 'Event-Tickets-for-Elementor'); ?></h2>
                <p><?php esc_html_e('This wizard will cover email basics, PDF defaults, ticket conflict protection, and starter pages.', 'Event-Tickets-for-Elementor'); ?></p>
                <ul>
                    <li><?php esc_html_e('Set sender details and core ticket email subject', 'Event-Tickets-for-Elementor'); ?></li>
                    <li><?php esc_html_e('Enable overlap prevention for attendee timeslots', 'Event-Tickets-for-Elementor'); ?></li>
                    <li><?php esc_html_e('Create recommended pages for events and ticket actions', 'Event-Tickets-for-Elementor'); ?></li>
                </ul>
                <div class="evt-setup-wizard__actions">
                    <a class="button button-primary button-hero" href="<?php echo esc_url(add_query_arg('step', 'email', $wizard_url)); ?>">
                        <?php esc_html_e('Start Wizard', 'Event-Tickets-for-Elementor'); ?>
                    </a>
                    <a class="button" href="<?php echo esc_url($get_started_url); ?>"><?php esc_html_e('Skip to Get Started', 'Event-Tickets-for-Elementor'); ?></a>
                    <a class="button" href="<?php echo esc_url($dashboard_url); ?>"><?php esc_html_e('Go to Dashboard', 'Event-Tickets-for-Elementor'); ?></a>
                </div>
            <?php elseif ('email' === $step) : ?>
                <h2><?php esc_html_e('Email Basics', 'Event-Tickets-for-Elementor'); ?></h2>
                <?php
                $templates = [];
                foreach (\EventTicketsElementor\Email\Email_Template_Presets::presets() as $key => $_label) {
                    $tpl = \EventTicketsElementor\Email\Email_Template_Presets::single($key);
                    $templates[$key] = [
                        'css'  => (string) ($tpl['css'] ?? ''),
                        'html' => (string) ($tpl['html'] ?? ''),
                    ];
                }
                $sample = [
                    'event_name'      => 'Sample Event',
                    'event_start'     => '2026-06-24 10:00',
                    'event_end'       => '2026-06-24 18:00',
                    'event_location'  => 'Sample Venue, Sample City',
                    'event_map_url'   => 'https://maps.google.com/?q=Sample+Venue',
                    'ticket_code'     => 'EVT-ABCDEFG123',
                    'attendee_name'   => 'Viktor',
                    'attendee_email'  => 'viktor@example.com',
                    'verify_url'      => 'https://example.com/ticket-checkin/?code=EVT-ABCDEFG123',
                    'ics_url'         => 'https://example.com/event.ics',
                    'google_cal_url'  => 'https://www.google.com/calendar/render?action=TEMPLATE',
                    'pdf_url'         => 'https://example.com/ticket.pdf',
                    'cancel_url'      => 'https://example.com/ticket-cancel/?ticket_code=EVT-ABCDEFG123',
                    'greeting'        => 'Hi Viktor,',
                    'intro'           => 'Thank you for registering for Sample Event.',
                    'tickets_html'    => '<div style="margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:10px;"><strong>Sample Event</strong> — EVT-ABCDEFG123</div>',
                    'site_name'       => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
                    'logo_url'        => \EventTicketsElementor\Email\Email_Preset_Branding::logo_url(),
                ];
                $preview_data = [
                    'templates' => $templates,
                    'sample'    => $sample,
                    'defaults'  => [
                        'primary_color'    => \EventTicketsElementor\Email\Email_Preset_Branding::primary_color(),
                        'accent_color'     => \EventTicketsElementor\Email\Email_Preset_Branding::accent_color(),
                        'background_color' => \EventTicketsElementor\Email\Email_Preset_Branding::background_color(),
                    ],
                ];
                ?>
                <script type="application/json" id="evt-email-presets-data">
                    <?php echo wp_json_encode($preview_data); ?>
                </script>
                <form method="post">
                    <?php wp_nonce_field('evt_setup_wizard'); ?>
                    <input type="hidden" name="evt_wizard_action" value="save_step" />
                    <input type="hidden" name="evt_wizard_step" value="email" />
                    <input type="hidden" name="evt_wizard_next" value="<?php echo esc_attr($next_step ?: 'rules'); ?>" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="evt-from-name"><?php esc_html_e('From Name', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-from-name" class="regular-text" type="text" name="evt_tickets_settings[from_name]" value="<?php echo esc_attr($from_name); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-from-email"><?php esc_html_e('From Email', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-from-email" class="regular-text" type="email" name="evt_tickets_settings[from_email]" value="<?php echo esc_attr($from_email); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-email-subject"><?php esc_html_e('Ticket Email Subject', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-email-subject" class="regular-text" type="text" name="evt_tickets_settings[ticket_email_subject]" value="<?php echo esc_attr($subject); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Attach .ics file', 'Event-Tickets-for-Elementor'); ?>
                                    <span class="dashicons dashicons-editor-help evt-setup-wizard__tip" title="<?php echo esc_attr__('An .ics file is a calendar invite that users can open to add the event to their calendar app.', 'Event-Tickets-for-Elementor'); ?>"></span>
                                </th>
                                <td>
                                    <p class="description evt-setup-wizard__help">
                                        <?php esc_html_e('An .ics file is a calendar invite file. Phones and email clients can open it directly to add the event to a calendar app.', 'Event-Tickets-for-Elementor'); ?>
                                    </p>
                                    <label>
                                        <input type="hidden" name="evt_tickets_settings[email_attach_ics]" value="0" />
                                        <input type="checkbox" name="evt_tickets_settings[email_attach_ics]" value="1" <?php checked($attach_ics, 1); ?> />
                                        <?php esc_html_e('Enabled', 'Event-Tickets-for-Elementor'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-email-greeting"><?php esc_html_e('Greeting', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-email-greeting" class="regular-text" type="text" name="evt_tickets_settings[email_greeting]" value="<?php echo esc_attr($greeting); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-email-intro"><?php esc_html_e('Intro Text', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><textarea id="evt-email-intro" class="large-text" rows="3" name="evt_tickets_settings[email_intro]"><?php echo esc_textarea($intro); ?></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-ticket-code-intro"><?php esc_html_e('Ticket Code Intro', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-ticket-code-intro" class="regular-text" type="text" name="evt_tickets_settings[email_ticket_code_intro]" value="<?php echo esc_attr($ticket_code_intro); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Email Template Preset', 'Event-Tickets-for-Elementor'); ?></th>
                                <td>
                                    <div class="evt-wizard-email-presets">
                                        <?php foreach ($email_presets as $preset_key => $preset_label) : ?>
                                            <?php $open = ($email_preset_current === $preset_key) ? ' open' : ''; ?>
                                            <details class="evt-email-preset-item" data-preset="<?php echo esc_attr($preset_key); ?>" <?php echo esc_attr($open); ?>>
                                                <summary>
                                                    <input type="radio" name="<?php echo esc_attr(\EventTicketsElementor\Email\Email_Template_Presets::OPTION_KEY); ?>" value="<?php echo esc_attr($preset_key); ?>" <?php checked($email_preset_current, $preset_key); ?> />
                                                    <strong><?php echo esc_html($preset_label); ?></strong>
                                                </summary>
                                                <div class="evt-wizard-email-presets__preview">
                                                    <iframe class="evt-email-preset-preview" data-preset="<?php echo esc_attr($preset_key); ?>" title="<?php echo esc_attr($preset_label); ?>" srcdoc=""></iframe>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-email-primary-color"><?php esc_html_e('Primary Color', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-email-primary-color" class="evt-email-color-field" type="text" name="<?php echo esc_attr(\EventTicketsElementor\Email\Email_Preset_Branding::OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($email_primary_color); ?>" data-default-color="#111827" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-email-accent-color"><?php esc_html_e('Accent Color', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-email-accent-color" class="evt-email-color-field" type="text" name="<?php echo esc_attr(\EventTicketsElementor\Email\Email_Preset_Branding::OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr($email_accent_color); ?>" data-default-color="#7C3AED" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="evt-setup-wizard__actions">
                        <?php if ($prev_step) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg('step', $prev_step, $wizard_url)); ?>"><?php esc_html_e('Back', 'Event-Tickets-for-Elementor'); ?></a>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save & Continue', 'Event-Tickets-for-Elementor'); ?></button>
                    </div>
                </form>
            <?php elseif ('pdf' === $step) : ?>
                <h2><?php esc_html_e('PDF Basics', 'Event-Tickets-for-Elementor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('evt_setup_wizard'); ?>
                    <input type="hidden" name="evt_wizard_action" value="save_step" />
                    <input type="hidden" name="evt_wizard_step" value="pdf" />
                    <input type="hidden" name="evt_wizard_next" value="<?php echo esc_attr($next_step ?: 'rules'); ?>" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="evt-pdf-template"><?php esc_html_e('Global PDF Template', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td>
                                    <select id="evt-pdf-template" name="evt_tickets_settings[pdf_template_preset]">
                                        <option value="classic" <?php selected($pdf_template_preset, 'classic'); ?>><?php esc_html_e('Classic', 'Event-Tickets-for-Elementor'); ?></option>
                                        <option value="minimal" <?php selected($pdf_template_preset, 'minimal'); ?>><?php esc_html_e('Minimal', 'Event-Tickets-for-Elementor'); ?></option>
                                        <option value="dark" <?php selected($pdf_template_preset, 'dark'); ?>><?php esc_html_e('Dark', 'Event-Tickets-for-Elementor'); ?></option>
                                    </select>
                                    <div class="evt-wizard-pdf-previews">
                                        <?php
                                        $preview_base = defined('EVT_TICKETS_PLUGIN_URL') ? EVT_TICKETS_PLUGIN_URL . 'assets/images/pdf-presets/' : '';
                                        if ($preview_base) :
                                        ?>
                                            <?php foreach (['classic' => __('Classic', 'Event-Tickets-for-Elementor'), 'minimal' => __('Minimal', 'Event-Tickets-for-Elementor'), 'dark' => __('Dark', 'Event-Tickets-for-Elementor')] as $key => $label) : ?>
                                                <div class="evt-wizard-pdf-preview <?php echo $pdf_template_preset === $key ? 'is-active' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>">
                                                    <strong><?php echo esc_html($label); ?></strong>
                                                    <img src="<?php echo esc_url($preview_base . $key . '.svg'); ?>" alt="<?php echo esc_attr($label); ?>" />
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Allow Per-Event Override', 'Event-Tickets-for-Elementor'); ?>
                                    <span class="dashicons dashicons-editor-help evt-setup-wizard__tip" title="<?php echo esc_attr__('If enabled, each event can choose its own PDF template instead of always using the global template.', 'Event-Tickets-for-Elementor'); ?>"></span>
                                </th>
                                <td>
                                    <label>
                                        <input type="hidden" name="evt_tickets_settings[pdf_allow_event_override]" value="0" />
                                        <input type="checkbox" name="evt_tickets_settings[pdf_allow_event_override]" value="1" <?php checked($pdf_allow_event_override, 1); ?> />
                                        <?php esc_html_e('Enabled', 'Event-Tickets-for-Elementor'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Show Logo', 'Event-Tickets-for-Elementor'); ?>
                                    <span class="dashicons dashicons-editor-help evt-setup-wizard__tip" title="<?php echo esc_attr__('Shows the site logo (or site icon fallback) in generated PDF tickets.', 'Event-Tickets-for-Elementor'); ?>"></span>
                                </th>
                                <td>
                                    <label>
                                        <input type="hidden" name="evt_tickets_settings[pdf_show_logo]" value="0" />
                                        <input type="checkbox" name="evt_tickets_settings[pdf_show_logo]" value="1" <?php checked($pdf_show_logo, 1); ?> />
                                        <?php esc_html_e('Enabled', 'Event-Tickets-for-Elementor'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="evt-pdf-accent"><?php esc_html_e('Accent Color', 'Event-Tickets-for-Elementor'); ?></label></th>
                                <td><input id="evt-pdf-accent" class="regular-text evt-color-field" type="text" name="evt_tickets_settings[pdf_accent_color]" value="<?php echo esc_attr($pdf_accent); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Attach PDF to Emails', 'Event-Tickets-for-Elementor'); ?></th>
                                <td>
                                    <label>
                                        <input type="hidden" name="evt_tickets_settings[pdf_attach_to_emails]" value="0" />
                                        <input type="checkbox" name="evt_tickets_settings[pdf_attach_to_emails]" value="1" <?php checked($pdf_attach, 1); ?> />
                                        <?php esc_html_e('Enabled', 'Event-Tickets-for-Elementor'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="evt-setup-wizard__actions">
                        <?php if ($prev_step) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg('step', $prev_step, $wizard_url)); ?>"><?php esc_html_e('Back', 'Event-Tickets-for-Elementor'); ?></a>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save & Continue', 'Event-Tickets-for-Elementor'); ?></button>
                    </div>
                </form>
            <?php elseif ('rules' === $step) : ?>
                <h2><?php esc_html_e('Ticket Rules', 'Event-Tickets-for-Elementor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('evt_setup_wizard'); ?>
                    <input type="hidden" name="evt_wizard_action" value="save_step" />
                    <input type="hidden" name="evt_wizard_step" value="rules" />
                    <input type="hidden" name="evt_wizard_next" value="<?php echo esc_attr($next_step ?: 'pages'); ?>" />
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Prevent timeslot conflicts', 'Event-Tickets-for-Elementor'); ?>
                                    <span class="dashicons dashicons-editor-help evt-setup-wizard__tip" title="<?php echo esc_attr__('If enabled, the system blocks issuing overlapping active tickets for the same attendee email.', 'Event-Tickets-for-Elementor'); ?>"></span>
                                </th>
                                <td>
                                    <p class="description evt-setup-wizard__help">
                                        <?php esc_html_e('Blocks creating a ticket when the same attendee email already holds an active ticket for another event that overlaps in time.', 'Event-Tickets-for-Elementor'); ?>
                                    </p>
                                    <label>
                                        <input type="hidden" name="<?php echo esc_attr(\EventTicketsElementor\Tickets\Ticket_Rules::OPTION_KEY); ?>[enforce_timeslot_exclusivity]" value="0" />
                                        <input type="checkbox" name="<?php echo esc_attr(\EventTicketsElementor\Tickets\Ticket_Rules::OPTION_KEY); ?>[enforce_timeslot_exclusivity]" value="1" <?php checked($enforce_conflicts, 1); ?> />
                                        <?php esc_html_e('Enabled', 'Event-Tickets-for-Elementor'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="evt-buffer-minutes"><?php esc_html_e('Buffer minutes', 'Event-Tickets-for-Elementor'); ?></label>
                                    <span class="dashicons dashicons-editor-help evt-setup-wizard__tip" title="<?php echo esc_attr__('Adds extra minutes before and after event windows when checking overlap conflicts.', 'Event-Tickets-for-Elementor'); ?>"></span>
                                </th>
                                <td>
                                    <p class="description evt-setup-wizard__help">
                                        <?php esc_html_e('Adds extra minutes before/after each event window when checking overlaps. Example: 15 adds a 15-minute safety buffer.', 'Event-Tickets-for-Elementor'); ?>
                                    </p>
                                    <input id="evt-buffer-minutes" type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr(\EventTicketsElementor\Tickets\Ticket_Rules::OPTION_KEY); ?>[timeslot_buffer_minutes]" value="<?php echo esc_attr((string) max(0, $buffer_minutes)); ?>" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="evt-setup-wizard__actions">
                        <?php if ($prev_step) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg('step', $prev_step, $wizard_url)); ?>"><?php esc_html_e('Back', 'Event-Tickets-for-Elementor'); ?></a>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save & Continue', 'Event-Tickets-for-Elementor'); ?></button>
                    </div>
                </form>
            <?php elseif ('pages' === $step) : ?>
                <h2><?php esc_html_e('Pages & Widgets', 'Event-Tickets-for-Elementor'); ?></h2>
                <p><?php esc_html_e('Create the recommended starter pages now without leaving the wizard.', 'Event-Tickets-for-Elementor'); ?></p>
                <p class="description"><?php esc_html_e('This creates 4 pages: Events, Get Ticket, Ticket Actions, and Staff Check-in.', 'Event-Tickets-for-Elementor'); ?></p>
                <p class="description"><?php esc_html_e('Pages include starter guidance blocks. Then edit each page with Elementor and place the relevant widgets.', 'Event-Tickets-for-Elementor'); ?></p>
                <div class="evt-setup-wizard__actions">
                    <button type="button" class="button button-primary evt-create-starter-pages"><?php esc_html_e('Create Starter Pages', 'Event-Tickets-for-Elementor'); ?></button>
                    <a class="button" href="<?php echo esc_url($create_event_url); ?>"><?php esc_html_e('Create First Event', 'Event-Tickets-for-Elementor'); ?></a>
                </div>
                <div class="evt-setup-wizard__status" aria-live="polite"></div>
                <ul class="evt-list">
                    <?php foreach ($starter_pages as $page) : ?>
                        <li>
                            <strong><?php echo esc_html($page['title']); ?></strong>
                            <?php if (! empty($page['id'])) : ?>
                                <span class="evt-meta">#<?php echo esc_html((string) $page['id']); ?></span>
                                <span class="evt-inline-links">
                                    <a href="<?php echo esc_url($page['edit_url']); ?>"><?php esc_html_e('Edit', 'Event-Tickets-for-Elementor'); ?></a>
                                    |
                                    <a href="<?php echo esc_url($page['view_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'Event-Tickets-for-Elementor'); ?></a>
                                </span>
                            <?php else : ?>
                                <span class="evt-meta"><?php esc_html_e('Not created yet', 'Event-Tickets-for-Elementor'); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post">
                    <?php wp_nonce_field('evt_setup_wizard'); ?>
                    <input type="hidden" name="evt_wizard_action" value="save_step" />
                    <input type="hidden" name="evt_wizard_step" value="pages" />
                    <input type="hidden" name="evt_wizard_next" value="<?php echo esc_attr($next_step ?: 'ready'); ?>" />
                    <div class="evt-setup-wizard__actions">
                        <?php if ($prev_step) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg('step', $prev_step, $wizard_url)); ?>"><?php esc_html_e('Back', 'Event-Tickets-for-Elementor'); ?></a>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'Event-Tickets-for-Elementor'); ?></button>
                    </div>
                </form>
            <?php else : ?>
                <h2><?php esc_html_e('You Are Ready', 'Event-Tickets-for-Elementor'); ?></h2>
                <p><?php esc_html_e('Core setup is complete. Continue with detailed setup in Get Started or jump directly into management screens.', 'Event-Tickets-for-Elementor'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('evt_setup_wizard'); ?>
                    <input type="hidden" name="evt_wizard_action" value="save_step" />
                    <input type="hidden" name="evt_wizard_step" value="ready" />
                    <input type="hidden" name="evt_wizard_next" value="ready" />
                    <input type="hidden" name="evt_wizard_finish" value="1" />
                    <div class="evt-setup-wizard__actions">
                        <a class="button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open Settings', 'Event-Tickets-for-Elementor'); ?></a>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Open Get Started', 'Event-Tickets-for-Elementor'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>