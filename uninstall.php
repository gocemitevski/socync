<?php
/**
 * SocialSync Uninstall Script.
 *
 * Cleans up plugin data when deleted from WordPress admin (preserves API keys per security best practices).
 *
 * @package SocialSync
 */

// Prevent direct access or loading on non-deletion contexts.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || false === WP_UNINSTALL_PLUGIN ) {
    exit;
}

/**
 * Clean up plugin-specific data from WordPress database.
 *
 * This script is executed when the plugin is deleted via admin panel.
 * It removes custom options and post meta while preserving user-provided API keys (security best practice).
 */
function socialsync_uninstall() {
    /**
     * Remove all SocialSync-specific option names from wp_options table.
     */
    global $wpdb;

    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( 'socialsync_' ) . '%'
        )
    );

    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( '_socialsync_' ) . '%'
        )
    );

    /**
     * Drop custom scheduled posts table.
     */
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}socialsync_scheduled_posts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange

    /**
     * Optionally remove scheduled events for delayed posts.
     */
    wp_clear_scheduled_hook( 'socialsync_run_delayed_posts' );
    wp_clear_scheduled_hook( 'socialsync_purge_old_logs' );

    /**
     * Debug log uninstall completion (for support purposes only - not in production).
     */
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'SocialSync plugin uninstalled. Database cleanup complete.' );
    }
}

socialsync_uninstall();
