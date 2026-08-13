<?php
/**
 * Socync Uninstall Script.
 *
 * Cleans up plugin data when deleted from WordPress admin. Persistent data is
 * removed only when the "Delete data on uninstall" option is enabled on the
 * Settings page; otherwise it is preserved so it can be restored on re-install.
 *
 * @package Socync
 */

// Prevent direct access or loading on non-deletion contexts.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || false === WP_UNINSTALL_PLUGIN ) {
    exit;
}

/**
 * Clean up plugin-specific data from WordPress database.
 *
 * This script is executed when the plugin is deleted via admin panel.
 * Cron events are always cleared; options, post meta, and custom tables are
 * removed only when the admin opted in on the Settings page.
 */
function socync_uninstall() {
    global $wpdb;

    /**
     * Cron events are transient runtime state, not user data — always clear them.
     */
    wp_clear_scheduled_hook( 'socync_run_delayed_posts' );
    wp_clear_scheduled_hook( 'socync_purge_old_logs' );
    wp_clear_scheduled_hook( 'socialsync_run_delayed_posts' );
    wp_clear_scheduled_hook( 'socialsync_purge_old_logs' );

    // Clear the cron lock so a re-install does not skip its first cron cycle
    // if the preserved lock is still within the scheduler's 5-minute window.
    delete_option( 'socync_cron_lock' );

    /**
     * Unless the admin opted in on the Settings page, keep all persistent data
     * so it is restored if the plugin is re-installed.
     */
    if ( ! get_option( 'socync_delete_data_on_uninstall', false ) ) {
        // Cancel any rows still marked as scheduled so they are not published
        // automatically if the plugin is re-installed.
        foreach ( array(
            $wpdb->prefix . 'socync_scheduled_posts',
            $wpdb->prefix . 'socialsync_scheduled_posts',
        ) as $cancel_table ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $cancel_table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $table_exists ) {
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "UPDATE {$cancel_table} SET status = 'cancelled' WHERE status = 'scheduled'"
                );
            }
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Socync plugin uninstalled. Database data preserved; pending scheduled posts cancelled.' );
        }
        return;
    }

    /**
     * Remove all Socync-specific option names from wp_options table.
     */
    foreach ( array( 'socync_', 'socialsync_' ) as $prefix ) {
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            )
        );
    }

    foreach ( array( '_socync_', '_socialsync_' ) as $prefix ) {
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            )
        );
    }

    /**
     * Drop custom scheduled posts table.
     */
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}socync_scheduled_posts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}socialsync_scheduled_posts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

    /**
     * Debug log uninstall completion (for support purposes only - not in production).
     */
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'Socync plugin uninstalled. Database cleanup complete.' );
    }
}

socync_uninstall();
