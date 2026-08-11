<?php

/**
 * Schema maintenance for Event Tickets for Elementor.
 *
 * Runs idempotent, versioned upgrades that improve query performance
 * (e.g. targeted indexes on the postmeta table). Upgrades run once per
 * version — guarded by an option — from admin_init and plugin activation,
 * so existing installs pick them up without needing to re-activate.
 */

namespace EventTicketsElementor;

if (! defined('ABSPATH')) {
    exit;
}

class Schema
{
    /**
     * Bump whenever a new upgrade step is introduced so that installs
     * which have already run the previous version re-run the migration.
     */
    public const DB_VERSION = '2';

    /**
     * Option that stores the last applied schema version.
     */
    public const VERSION_OPTION = 'evt_tickets_db_version';

    /**
     * Name of the composite postmeta index used for meta_value lookups
     * (ticket codes, attendee emails, event start timestamps).
     */
    public const POSTMETA_INDEX = 'evt_meta_key_value';

    /**
     * Run any pending schema upgrades. Safe to call on every admin_init —
     * it no-ops once the stored version matches.
     */
    public static function maybe_upgrade(): void
    {
        if (get_option(self::VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        self::add_postmeta_lookup_index();

        update_option(self::VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * Add a (meta_key, meta_value) index on the postmeta table if missing.
     *
     * The plugin performs many exact meta_value lookups (ticket codes,
     * attendee emails, event start timestamps). The default wp_postmeta
     * indexes only cover the primary key and the post_id column, so those
     * lookups fall back to full table scans as the table grows. This index
     * keeps them cheap.
     */
    private static function add_postmeta_lookup_index(): void
    {
        global $wpdb;

        $table = $wpdb->postmeta;
        if (! $table || ! self::table_exists($table)) {
            return;
        }

        if (self::index_exists($table, self::POSTMETA_INDEX)) {
            return;
        }

        // 191-char prefixes keep the composite index within InnoDB's
        // traditional 767-byte per-index limit while covering practically
        // all meta_key names and the vast majority of meta values.
        $composite = "ALTER TABLE {$table} ADD INDEX "
            . self::POSTMETA_INDEX . " (meta_key(191), meta_value(191))";
        $single    = "ALTER TABLE {$table} ADD INDEX "
            . self::POSTMETA_INDEX . " (meta_value(191))";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->suppress_errors(true);
        $ok = $wpdb->query($composite);
        if (false === $ok && ! self::index_exists($table, self::POSTMETA_INDEX)) {
            // Very old servers / restrictive configs may reject the
            // composite index; fall back to a meta_value-only index.
            $ok = $wpdb->query($single);
        }
        $wpdb->suppress_errors(false);
        // phpcs:enable
    }

    /**
     * @param string $table
     */
    private static function table_exists(string $table): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    /**
     * @param string $table
     * @param string $index
     */
    private static function index_exists(string $table, string $index): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        if (! is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if (isset($row['Key_name']) && $row['Key_name'] === $index) {
                return true;
            }
        }

        return false;
    }
}
