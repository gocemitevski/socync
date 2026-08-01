<?php
/**
 * Socync Deactivator
 *
 * @package Socync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Socync_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear the scheduled cron events
        wp_clear_scheduled_hook( 'socync_run_delayed_posts' );
        wp_clear_scheduled_hook( 'socync_purge_old_logs' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}