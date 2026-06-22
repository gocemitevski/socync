<?php
/**
 * SocialSync Activation/Deactivation Logic
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * SocialSync Activator Class.
 *
 * Handles plugin activation and deactivation hooks.
 */
class SocialSync_Activator {

    /**
     * Plugin activation: set up scheduled events, validate API keys.
     *
     * @return void Runs on first plugin activation.
     */
    public static function activate(): void {
        // Set default options if they don't exist yet
        $defaults = array(
            'cron_interval_minutes' => 1,
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key, false ) === false ) {
                add_option($key, $value);
            }
        }

        // Create custom database table for standalone scheduled posts
        if ( class_exists( 'SocialSync_Scheduled_Post' ) ) {
            SocialSync_Scheduled_Post::create_table();
        }

        // Schedule the one-time delayed posts queue event for first activation
        self::schedule_delayed_posts_queue();

        // Schedule daily log purge
        if ( ! wp_next_scheduled( 'socialsync_purge_old_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'socialsync_purge_old_logs' );
        }

        // Register uninstall cleanup script in socialsync.php
        register_uninstall_hook(
            dirname(dirname(dirname(__FILE__))) . '/socialsync.php',
            'socialsync_uninstall'
        );
    }

    /**
     * Schedule WP-Cron for delayed posts queue processing.
     *
     * @return void Schedules a single-event cron to process pending social posts.
     */
    private static function schedule_delayed_posts_queue(): void {
        $event_key = 'socialsync_run_delayed_posts';

        // Check if the cron event is already scheduled
        $scheduled = wp_next_scheduled( $event_key );

        if ( ! $scheduled ) {
            // Schedule a one-time event to run immediately after plugin activation
            wp_schedule_single_event(
                time(),
                $event_key,
                array( 'once' => true )
            );
        }
    }
}
