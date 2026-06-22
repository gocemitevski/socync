<?php
/**
 * SocialSync Deactivator
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialSync_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear the scheduled cron events
        wp_clear_scheduled_hook( 'socialsync_run_delayed_posts' );
        wp_clear_scheduled_hook( 'socialsync_purge_old_logs' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}