<?php

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/settings/class-settings-schema.php';

/**
 * Centralized settings storage and schema.
 */
class Settings_Store
{
    public const OPTION_KEY = 'evt_tickets_settings';

    /**
     * Sentinel used by secret fields: when a secret field is submitted with
     * this value (or empty), the stored value is kept instead of overwritten.
     */
    public const SECRET_KEEP_SENTINEL = '__KEEP__';

    /**
     * Setting key => wp-config.php constant override.
     * When the constant is defined and truthy it takes precedence over the stored option.
     *
     * @var array<string,string>
     */
    public const SECRET_CONSTANTS = [
        'stripe_secret_key'          => 'EVT_STRIPE_SECRET_KEY',
        'stripe_test_secret_key'     => 'EVT_STRIPE_TEST_SECRET_KEY',
        'stripe_webhook_secret'      => 'EVT_STRIPE_WEBHOOK_SECRET',
        'stripe_test_webhook_secret' => 'EVT_STRIPE_TEST_WEBHOOK_SECRET',
        'epay_merchant_id'           => 'EVT_EPAY_MERCHANT_ID',
        'epay_secret'                => 'EVT_EPAY_SECRET',
    ];

    /**
     * @var Settings_Schema
     */
    private Settings_Schema $schema;

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $fields_index = [];

    /**
     * @var array<int,array<string,mixed>>
     */
    private array $sections = [];

    public function __construct()
    {
        // Lazy-load schema to avoid triggering translation loading too early
        // (WP 6.7+ warns when translations are loaded before `init`).
        $this->schema = new Settings_Schema();
    }

    /**
     * List all sections with their fields metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sections(): array
    {
        $this->ensure_schema_loaded();
        return $this->sections;
    }

    /**
     * Get a specific field configuration by key.
     *
     * @param string $key
     * @return array<string,mixed>|null
     */
    public function field(string $key): ?array
    {
        $this->ensure_schema_loaded();
        return $this->fields_index[$key] ?? null;
    }

    /**
     * Retrieve a setting with a default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = '')
    {
        $constant = $this->secret_constant_for($key);
        if (null !== $constant && defined($constant)) {
            $override = constant($constant);
            if (! empty($override)) {
                return $override;
            }
        }

        $options = get_option(self::OPTION_KEY, []);
        return $options[$key] ?? $default;
    }

    /**
     * Return the wp-config.php constant name that overrides a setting key, if any.
     *
     * @param string $key
     * @return string|null
     */
    public function secret_constant_for(string $key): ?string
    {
        return self::SECRET_CONSTANTS[$key] ?? null;
    }

    /**
     * Sanitize the incoming settings array using the schema.
     *
     * @param mixed $input
     * @return array<string,mixed>
     */
    public function sanitize($input): array
    {
        $this->ensure_schema_loaded();
        $existing = get_option(self::OPTION_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        // Keep existing values for fields not present in the submitted form
        // (e.g. when settings UI is split across tabs).
        $output = [];
        foreach ($this->fields_index as $key => $field) {
            if (array_key_exists($key, $existing)) {
                $output[$key] = $existing[$key];
            }
        }

        if (! is_array($input)) {
            return $output;
        }

        foreach ($this->fields_index as $key => $field) {
            if (array_key_exists($key, $input)) {
                // Secret fields keep their stored value unless a new non-empty
                // value was actually submitted (blank/sentinel never wipe it).
                if ('secret' === ($field['type'] ?? '') && $this->should_keep_secret($input[$key])) {
                    continue;
                }
                $output[$key] = $this->sanitize_field($input[$key], $field);
            }
        }

        // Preserve Staff-only settings that are managed outside the main Settings tabs.
        if (class_exists(Staff_Access_Manager::class)) {
            $staff_checkin_key = Staff_Access_Manager::SETTING_CHECKIN_PAGE_URL;

            if (array_key_exists($staff_checkin_key, $existing)) {
                $output[$staff_checkin_key] = Staff_Access_Manager::normalize_checkin_page_url((string) $existing[$staff_checkin_key]);
            }

            if (array_key_exists($staff_checkin_key, $input)) {
                $output[$staff_checkin_key] = Staff_Access_Manager::normalize_checkin_page_url((string) $input[$staff_checkin_key]);
            }
        }

        return $output;
    }

    private function ensure_schema_loaded(): void
    {
        if (! empty($this->sections) || ! empty($this->fields_index)) {
            return;
        }

        $this->sections = $this->schema->build();
        $this->fields_index = $this->index_fields($this->sections);
    }

    /**
     * Flatten fields for quicker lookup.
     *
     * @param array<int,array<string,mixed>> $sections
     * @return array<string,array<string,mixed>>
     */
    private function index_fields(array $sections): array
    {
        $fields = [];

        foreach ($sections as $section) {
            if (empty($section['fields']) || ! is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }

    /**
     * Whether a secret field submission should keep the stored value.
     * Empty values and the sentinel both mean "leave the stored secret unchanged".
     *
     * @param mixed $value
     * @return bool
     */
    private function should_keep_secret($value): bool
    {
        if (self::SECRET_KEEP_SENTINEL === $value) {
            return true;
        }
        return '' === trim((string) $value);
    }

    /**
     * Apply the correct sanitizer per field.
     *
     * @param mixed $value
     * @param array<string,mixed> $field
     * @return mixed
     */
    private function sanitize_field($value, array $field)
    {
        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return call_user_func($field['sanitize_callback'], $value);
        }

        switch ($field['type'] ?? 'text') {
            case 'checkbox':
            case 'checkbox_matrix':
                return ! empty($value) ? 1 : 0;
            case 'number':
                return absint($value);
            case 'select':
                $value = sanitize_text_field((string) $value);
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                if (isset($options[$value])) {
                    return $value;
                }
                return $field['default'] ?? '';
            case 'color':
                $value = sanitize_hex_color((string) $value);
                return $value ? $value : '';
            case 'email':
                return sanitize_email((string) $value);
            case 'secret':
                return sanitize_text_field((string) $value);
            case 'textarea':
                return wp_kses_post((string) $value);
            case 'text':
            default:
                return sanitize_text_field((string) $value);
        }
    }
}
