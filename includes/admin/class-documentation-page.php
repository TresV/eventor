<?php

namespace EventTicketsElementor\Admin;

use EventTicketsElementor\Staff_Access_Manager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin documentation workspace.
 */
class Documentation_Page
{
    /**
     * Ordered document maps for the admin documentation workspaces.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_collection_config(string $page_slug): array
    {
        if ('evt-tickets-user-guide' === $page_slug) {
            return [
                'page_title'   => __('Event Tickets User Guide', 'Event-Tickets-for-Elementor'),
                'page_intro'   => __('Simple, practical guidance for setting up events, Elementor templates, emails, PDFs, cancellation, check-in, and everyday plugin use.', 'Event-Tickets-for-Elementor'),
                'sidebar_title'=> __('User Guide', 'Event-Tickets-for-Elementor'),
                'sidebar_copy' => __('Browse step-by-step guides written for site owners, editors, and non-technical admins.', 'Event-Tickets-for-Elementor'),
                'search_label' => __('Search user guide', 'Event-Tickets-for-Elementor'),
                'nav_label'    => __('User guide pages', 'Event-Tickets-for-Elementor'),
                'empty_message'=> __('No user guide files were found in the plugin package.', 'Event-Tickets-for-Elementor'),
                'docs'         => [
                    'home' => [
                        'title'       => __('Home', 'Event-Tickets-for-Elementor'),
                        'description' => __('Overview of the plugin and the full user guide map.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/README.md',
                    ],
                    'get-started' => [
                        'title'       => __('First Setup Path', 'Event-Tickets-for-Elementor'),
                        'description' => __('Recommended first steps after activation and the quickest route to a working event flow.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/GETTING-STARTED.md',
                    ],
                    'create-event' => [
                        'title'       => __('Create an Event', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to create, publish, and review an event from start to finish.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/CREATE-EVENT.md',
                    ],
                    'event-fields' => [
                        'title'       => __('Event Fields Explained', 'Event-Tickets-for-Elementor'),
                        'description' => __('What each field in the event editor does and when to use it.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/EVENT-FIELDS.md',
                    ],
                    'widgets' => [
                        'title'       => __('Widgets', 'Event-Tickets-for-Elementor'),
                        'description' => __('Available widgets, when to use each one, and the main setup options.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/WIDGETS.md',
                    ],
                    'dynamic-tags' => [
                        'title'       => __('Dynamic Tags', 'Event-Tickets-for-Elementor'),
                        'description' => __('Available event dynamic tags and how to use them inside Elementor templates.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/DYNAMIC-TAGS.md',
                    ],
                    'email' => [
                        'title'       => __('Email Setup', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to configure outgoing ticket emails, labels, attachments, and templates.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/EMAIL-SETUP.md',
                    ],
                    'pdf' => [
                        'title'       => __('PDF Setup', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to configure PDF tickets, branding, presets, and attachments.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/PDF-SETUP.md',
                    ],
                    'cancellation' => [
                        'title'       => __('Ticket Cancellation', 'Event-Tickets-for-Elementor'),
                        'description' => __('How cancellation works and how to prepare attendee-facing cancellation flows.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/TICKET-CANCELLATION.md',
                    ],
                    'checkin' => [
                        'title'       => __('Staff Check-in', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to set up the check-in page and run ticket validation on event day.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/STAFF-CHECKIN.md',
                    ],
                    'staff-role' => [
                        'title'       => __('Staff Role Setup', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to create, configure, and assign the built-in Staff role and access rules.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/STAFF-ROLE-SETUP.md',
                    ],
                    'tickets' => [
                        'title'       => __('Tickets and Exports', 'Event-Tickets-for-Elementor'),
                        'description' => __('How to review issued tickets, statuses, and CSV exports.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/TICKETS-AND-EXPORTS.md',
                    ],
                    'settings' => [
                        'title'       => __('Settings Overview', 'Event-Tickets-for-Elementor'),
                        'description' => __('What each settings area controls and when you should change it.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/SETTINGS-OVERVIEW.md',
                    ],
                    'troubleshooting' => [
                        'title'       => __('Troubleshooting', 'Event-Tickets-for-Elementor'),
                        'description' => __('Common setup mistakes and the quickest checks to resolve them.', 'Event-Tickets-for-Elementor'),
                        'file'        => 'user-guide/TROUBLESHOOTING.md',
                    ],
                ],
            ];
        }

        return [
            'page_title'   => __('Event Tickets Documentation', 'Event-Tickets-for-Elementor'),
            'page_intro'   => __('Bundled documentation for architecture, data flow, ticketing internals, Elementor integration, and operations.', 'Event-Tickets-for-Elementor'),
            'sidebar_title'=> __('Documentation', 'Event-Tickets-for-Elementor'),
            'sidebar_copy' => __('Browse the technical documentation included with the plugin build.', 'Event-Tickets-for-Elementor'),
            'search_label' => __('Search documentation', 'Event-Tickets-for-Elementor'),
            'nav_label'    => __('Documentation pages', 'Event-Tickets-for-Elementor'),
            'empty_message'=> __('No documentation files were found in the plugin package.', 'Event-Tickets-for-Elementor'),
            'docs'         => [
                'home' => [
                    'title'       => __('Home', 'Event-Tickets-for-Elementor'),
                    'description' => __('Overview of the plugin and the full technical documentation map.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'README.md',
                ],
                'architecture' => [
                    'title'       => __('Core Architecture', 'Event-Tickets-for-Elementor'),
                    'description' => __('Bootstrap flow, service groups, and the plugin loading model.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/01-core-architecture.md',
                ],
                'events' => [
                    'title'       => __('Events Module', 'Event-Tickets-for-Elementor'),
                    'description' => __('Event data, timeslots, discovery metadata, and query endpoints.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/02-events-module.md',
                ],
                'tickets' => [
                    'title'       => __('Tickets and Check-in', 'Event-Tickets-for-Elementor'),
                    'description' => __('Issuance paths, validation, cancellation, and staff check-in behavior.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/03-tickets-checkin-cancellation.md',
                ],
                'widgets' => [
                    'title'       => __('Widgets and Tags', 'Event-Tickets-for-Elementor'),
                    'description' => __('Elementor widgets, dynamic tags, and frontend rendering blocks.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/04-elementor-widgets-dynamic-tags.md',
                ],
                'delivery' => [
                    'title'       => __('Email, PDF, Calendar, QR', 'Event-Tickets-for-Elementor'),
                    'description' => __('Attendee delivery stack, ICS/PDF generation, and QR workflows.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/05-email-pdf-calendar-qr.md',
                ],
                'admin' => [
                    'title'       => __('Admin and Operations', 'Event-Tickets-for-Elementor'),
                    'description' => __('Settings, exports, maintenance tooling, and admin-side modules.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/06-admin-settings-ops.md',
                ],
                'staff-role' => [
                    'title'       => __('Staff Role and Access', 'Event-Tickets-for-Elementor'),
                    'description' => __('Built-in Staff role lifecycle, capability syncing, admin routing, and check-in redirects.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'modules/07-staff-role-access.md',
                ],
                'wporg-readme' => [
                    'title'       => __('WordPress.org Readme', 'Event-Tickets-for-Elementor'),
                    'description' => __('Packaging notes and release-readiness content for distribution.', 'Event-Tickets-for-Elementor'),
                    'file'        => 'wporg-readme.txt',
                ],
            ],
        ];
    }

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        if (false === strpos((string) $screen->id, 'evt-tickets')) {
            return;
        }

        wp_enqueue_style(
            'evt-tickets-admin',
            EVT_TICKETS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev'
        );

        if (false !== strpos((string) $screen->id, 'evt-tickets-documentation') || false !== strpos((string) $screen->id, 'evt-tickets-user-guide')) {
            wp_enqueue_script(
                'evt-tickets-admin-documentation',
                EVT_TICKETS_PLUGIN_URL . 'assets/js/admin-documentation.js',
                [],
                defined('EVT_TICKETS_VERSION') ? EVT_TICKETS_VERSION : 'dev',
                true
            );
        }
    }

    public function render(): void
    {
        if (! current_user_can(Staff_Access_Manager::CAP_MANAGE_PLUGIN)) {
            return;
        }

        $page_slug = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : 'evt-tickets-documentation';
        $collection = $this->get_collection_config($page_slug);
        $documents = $this->get_documents($collection);
        if ([] === $documents) {
            wp_die(esc_html((string) $collection['empty_message']));
        }

        $active_slug = isset($_GET['doc']) ? sanitize_key(wp_unslash($_GET['doc'])) : 'home';
        if (! isset($documents[$active_slug])) {
            reset($documents);
            $active_slug = (string) key($documents);
        }

        $active_document = $documents[$active_slug];
        $active_html     = $this->render_markdown($active_document['content']);

        $view = EVT_TICKETS_PLUGIN_DIR . 'includes/admin/views/documentation.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function get_documents(array $collection): array
    {
        $docs_root  = trailingslashit(EVT_TICKETS_PLUGIN_DIR . 'docs');
        $documents  = [];
        $locale_dir = $this->documentation_locale_dir();

        foreach (($collection['docs'] ?? []) as $slug => $document) {
            $relative_path = $document['file'];
            $absolute_path = $this->resolve_document_path($docs_root, $relative_path, $locale_dir);

            if (! file_exists($absolute_path) || ! is_readable($absolute_path)) {
                continue;
            }

            $content = (string) file_get_contents($absolute_path);
            if ('' === trim($content)) {
                continue;
            }

            $documents[$slug] = [
                'slug'        => $slug,
                'title'       => $document['title'],
                'description' => $document['description'],
                'file'        => ltrim(str_replace($docs_root, '', $absolute_path), '/'),
                'content'     => $content,
            ];
        }

        return $documents;
    }

    private function documentation_locale_dir(): string
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        return 0 === strpos((string) $locale, 'bg') ? 'bg' : 'en';
    }

    private function resolve_document_path(string $docs_root, string $relative_path, string $locale_dir): string
    {
        $localized_path = $docs_root . trailingslashit($locale_dir) . ltrim($relative_path, '/');
        if (file_exists($localized_path) && is_readable($localized_path)) {
            return $localized_path;
        }

        return $docs_root . $relative_path;
    }

    private function render_markdown(string $markdown): string
    {
        $lines             = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $html              = [];
        $paragraph_lines   = [];
        $list_items        = [];
        $list_type         = null;
        $code_lines        = [];
        $code_language     = '';
        $is_in_code_block  = false;

        $flush_paragraph = static function () use (&$html, &$paragraph_lines): void {
            if ([] === $paragraph_lines) {
                return;
            }

            $text = trim(implode(' ', $paragraph_lines));
            if ('' !== $text) {
                $html[] = '<p>' . Documentation_Page::render_inline_markdown($text) . '</p>';
            }

            $paragraph_lines = [];
        };

        $flush_list = static function () use (&$html, &$list_items, &$list_type): void {
            if ([] === $list_items || null === $list_type) {
                return;
            }

            $html[] = sprintf('<%1$s>%2$s</%1$s>', $list_type, implode('', $list_items));
            $list_items = [];
            $list_type  = null;
        };

        $flush_code = static function () use (&$html, &$code_lines, &$code_language): void {
            if ([] === $code_lines) {
                return;
            }

            $class_name = '' !== $code_language ? ' class="language-' . esc_attr(sanitize_html_class($code_language)) . '"' : '';
            $html[]     = '<pre><code' . $class_name . '>' . esc_html(implode("\n", $code_lines)) . '</code></pre>';
            $code_lines = [];
            $code_language = '';
        };

        foreach ($lines as $line) {
            if (preg_match('/^```([\w-]+)?\s*$/', $line, $matches)) {
                $flush_paragraph();
                $flush_list();

                if ($is_in_code_block) {
                    $flush_code();
                    $is_in_code_block = false;
                } else {
                    $code_language    = isset($matches[1]) ? trim($matches[1]) : '';
                    $is_in_code_block = true;
                }

                continue;
            }

            if ($is_in_code_block) {
                $code_lines[] = $line;
                continue;
            }

            if ('' === trim($line)) {
                $flush_paragraph();
                $flush_list();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $flush_paragraph();
                $flush_list();

                $level  = strlen($matches[1]);
                $text   = trim($matches[2]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, self::render_inline_markdown($text), $level);
                continue;
            }

            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $matches)) {
                $flush_paragraph();

                if ('ol' !== $list_type) {
                    $flush_list();
                    $list_type = 'ol';
                }

                $list_items[] = '<li>' . self::render_inline_markdown(trim($matches[1])) . '</li>';
                continue;
            }

            if (preg_match('/^\s*-\s+(.+)$/', $line, $matches)) {
                $flush_paragraph();

                if ('ul' !== $list_type) {
                    $flush_list();
                    $list_type = 'ul';
                }

                $list_items[] = '<li>' . self::render_inline_markdown(trim($matches[1])) . '</li>';
                continue;
            }

            $flush_list();
            $paragraph_lines[] = trim($line);
        }

        if ($is_in_code_block) {
            $flush_code();
        }

        $flush_paragraph();
        $flush_list();

        return implode("\n", $html);
    }

    private static function render_inline_markdown(string $text): string
    {
        $segments = preg_split('/(`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $output   = '';

        foreach ($segments as $segment) {
            if ('' === $segment) {
                continue;
            }

            if ('`' === $segment[0] && '`' === substr($segment, -1)) {
                $output .= '<code>' . esc_html(substr($segment, 1, -1)) . '</code>';
                continue;
            }

            $output .= self::render_non_code_inline_markdown($segment);
        }

        return $output;
    }

    private static function render_non_code_inline_markdown(string $text): string
    {
        $pattern = '/\[(.*?)\]\((https?:\/\/[^\s)]+)\)|\*\*(.*?)\*\*/';
        $offset  = 0;
        $output  = '';

        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return esc_html($text);
        }

        foreach ($matches as $match) {
            $full_match = $match[0][0];
            $position   = $match[0][1];

            $output .= esc_html(substr($text, $offset, $position - $offset));

            if (isset($match[1][0]) && '' !== $match[1][0] && isset($match[2][0]) && '' !== $match[2][0]) {
                $output .= sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    esc_url($match[2][0]),
                    esc_html($match[1][0])
                );
            } elseif (isset($match[3][0])) {
                $output .= '<strong>' . esc_html($match[3][0]) . '</strong>';
            } else {
                $output .= esc_html($full_match);
            }

            $offset = $position + strlen($full_match);
        }

        $output .= esc_html(substr($text, $offset));

        return $output;
    }
}
