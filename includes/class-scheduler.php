<?php
/**
 * SocialSync Scheduler Class
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * SocialSync Scheduler Class.
 *
 * Manages WP-Cron queue for delayed social media posting and publishes queued items at scheduled times.
 */
class SocialSync_Scheduler {

    /**
     * Plugin version.
     *
     * @var string Version number.
     */
    const PLUGIN_VERSION = '1.0';

    /**
     * Singleton instance of the scheduler.
     *
     * @var SocialSync_Scheduler|null Scheduler instance.
     */
    private static $instance = null;

    /**
     * WP-Cron event hook name for delayed posts.
     *
     * @var string Unique cron event identifier.
     */
    const CRON_EVENT = 'socialsync_run_delayed_posts';

    /**
     * Scheduler constructor (prevents direct instantiation).
     *
     * @return void Prevents multiple instantiations using singleton pattern.
     */
    private function __construct() {
        // Register custom cron schedule
        add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );

        // Set up WP-Cron event for running delayed posts queue
        add_action( self::CRON_EVENT, array( $this, 'run_delayed_posts' ), 1 );

        // Initialize scheduler on plugin activation
        $this->init();
    }

    /**
     * Register a one-minute cron schedule for processing delayed posts.
     *
     * @param array $schedules Existing cron schedules.
     * @return array
     */
    public function register_cron_schedule( array $schedules ): array {
        $schedules['socialsync_every_minute'] = array(
            'interval' => 60,
            'display'  => __( 'Every Minute', 'social-sync' ),
        );
        return $schedules;
    }

    /**
     * Get singleton instance of the scheduler.
     *
     * @return SocialSync_Scheduler Scheduler instance.
     */
    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the scheduler - called on plugin activation.
     *
     * @return void Sets up initial cron event with one-time schedule.
     */
    private function init(): void {
        if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
            wp_schedule_event(
                time(),
                'socialsync_every_minute',
                self::CRON_EVENT
            );
        }
    }

    /**
     * Enqueue a post for publishing.
     *
     * @param int    $post_id       Post ID.
     * @param string $schedule_type 'immediate' or 'scheduled'.
     * @param string $schedule_date Date/time string for scheduled posts.
     * @return void
     */
    public function enqueue_post( int $post_id, string $schedule_type = 'immediate', string $schedule_date = '' ): void {
        if ( 'scheduled' === $schedule_type && ! empty( $schedule_date ) ) {
            $timestamp = strtotime( $schedule_date );
            if ( $timestamp > time() ) {
                update_post_meta( $post_id, '_socialsync_status', 'scheduled' );
                wp_schedule_single_event( $timestamp, self::CRON_EVENT, array( $post_id ) );
                return;
            }
        }

        update_post_meta( $post_id, '_socialsync_status', 'pending' );
        wp_schedule_single_event( time(), self::CRON_EVENT, array( $post_id ) );
    }

    /**
     * Process all delayed social media posts from the queue.
     *
     * This is called by WP-Cron at scheduled intervals to process items that have
     * reached their publish time (immediate or delayed posting).
     *
     * @return void Processes each queued item and logs results.
     */
    public function run_delayed_posts(): void {
        // Retrieve all posts from queue table/meta that are ready to be published
        $posts = $this->get_ready_posts();

        // Process each post in the queue with rate limiting delay
        foreach ( $posts as $post ) {
            // Skip if this post is still scheduled for a future date
            if ( $this->is_post_scheduled_for_future( $post ) ) {
                continue;
            }

            $all_success = true;

            // Process each connected platform for the queued post
            $active_platforms = array_keys( array_filter( $post['platforms'] ) );
            foreach ( $active_platforms as $platform_slug ) {
                // Attempt to publish to this platform with timeout protection
                $result = $this->publish_to_platform( $post, $platform_slug );

                if ( is_wp_error( $result ) ) {
                    $all_success = false;
                } elseif ( isset( $result['success'] ) && false === $result['success'] ) {
                    $all_success = false;
                    // Log the failed action for debugging and user visibility
                    $this->log_action(
                        $post['id'],
                        $platform_slug,
                        'failed',
                        isset( $result['message'] ) ? $result['message'] : ''
                    );
                }
            }

            // Mark standalone scheduled post as published or failed
            if ( isset( $post['source'] ) && 'standalone' === $post['source'] && isset( $post['row_id'] ) ) {
                SocialSync_Scheduled_Post::update( $post['row_id'], array(
                    'status' => $all_success ? 'published' : 'failed',
                ) );
            }

        }
    }

    /**
     * Get all posts from the queue that are ready for publishing.
     *
     * @return array Array of post data objects with id, platforms, and schedule date.
     */
    private function get_ready_posts(): array {
        $ready_posts = array();

        // Get WP posts with pending/scheduled status
        $posts = get_posts(
            array(
                'post_type'      => 'any',
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'meta_query'     => array(
                    array(
                        'key'     => '_socialsync_status',
                        'value'   => array( 'pending', 'scheduled' ),
                        'compare' => 'IN',
                    ),
                ),
                'fields'         => 'ids',
            )
        );

        foreach ( $posts as $post_id ) {
            $status     = get_post_meta( $post_id, '_socialsync_status', true );
            $platforms  = get_post_meta( $post_id, '_socialsync_platforms', true );
            $schedule   = get_post_meta( $post_id, '_socialsync_schedule_date', true );

            if ( empty( $platforms ) || ! is_array( $platforms ) ) {
                continue;
            }

            if ( ! empty( $schedule ) && strtotime( $schedule ) > time() ) {
                continue;
            }

            $ready_posts[] = apply_filters(
                'socialsync_ready_post',
                array(
                    'id'        => $post_id,
                    'post_id'   => $post_id,
                    'platforms' => $platforms,
                    'scheduled' => $schedule ?: '',
                    'source'    => 'wp_post',
                ),
                $post_id
            );
        }

        // Get standalone scheduled posts from custom table
        $standalone_posts = SocialSync_Scheduled_Post::get_due();
        foreach ( $standalone_posts as $item ) {
            $platforms = json_decode( $item->platforms, true );
            if ( ! is_array( $platforms ) || empty( $platforms ) ) {
                continue;
            }

            $ready_posts[] = array(
                'id'        => $item->id,
                'post_id'   => 0,
                'title'     => $item->title,
                'content'   => $item->content,
                'platforms' => array_fill_keys( $platforms, true ),
                'scheduled' => $item->scheduled_date,
                'source'    => 'standalone',
                'row_id'    => $item->id,
            );
        }

        return apply_filters( 'socialsync_ready_posts', $ready_posts );
    }

    /**
     * Check if a post is scheduled for a future date.
     *
     * @param array $post Post data object from get_ready_posts().
     * @return bool True if scheduled for future (should skip), false if ready or immediate.
     */
    private function is_post_scheduled_for_future( array $post ): bool {
        if ( ! isset($post['scheduled']) || empty($post['scheduled']) ) {
            return false;
        }

        $schedule_date = strval( wp_unslash( $post['scheduled'] ) );

        $offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
        if ( ! empty($schedule_date) && strtotime( $schedule_date ) - $offset > time() ) {
            return true;
        }

        return false;
    }

    /**
     * Publish a post to the specified platform.
     *
     * @param array $post Post data object from get_ready_posts().
     * @param string $platform_slug Platform identifier (x, linkedin, facebook).
     * @return array Associative array with 'success' => bool and optional 'data'.
     */
    private function publish_to_platform( array $post, string $platform_slug ): array {
        // Instantiate the appropriate platform provider based on slug
        switch ( sanitize_text_field( $platform_slug ) ) {
            case 'x':
                $provider = new SocialSync_X_Provider();
                break;

            case 'linkedin':
                $provider = new SocialSync_LinkedIn_Provider();
                break;

            case 'facebook':
                $provider = new SocialSync_Facebook_Provider();
                break;

            case 'bluesky':
                $provider = new SocialSync_Bluesky_Provider();
                break;

            default:
                // Unknown platform - skip with error logging
                $this->log_action(
                    isset( $post['id'] ) ? intval( wp_unslash( $post['id'] ) ) : 0,
                    sanitize_text_field( $platform_slug ),
                    'failed',
                    __( 'Unknown or unsupported social media platform.', 'social-sync' )
                );

                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %s: Platform slug */
                        __( 'Unsupported platform: %s', 'social-sync' ),
                        sanitize_text_field( wp_unslash( $platform_slug ) )
                    ),
                );
        }

        // Get the post content based on source
        if ( isset( $post['source'] ) && 'standalone' === $post['source'] ) {
            $content = $post['content'];
        } else {
            $custom_content = get_post_meta( $post['post_id'], '_socialsync_' . $platform_slug . '_content', true );
            if ( ! empty( $custom_content ) ) {
                $content = $custom_content;
            } else {
                $content = get_the_title( $post['post_id'] );
                $excerpt = get_the_excerpt( $post['post_id'] );
                $permalink = get_permalink( $post['post_id'] );
                if ( ! empty( $excerpt ) ) {
                    $content .= "\n\n" . $excerpt;
                }
                $content .= "\n\n" . $permalink;
            }
        }
        $content = apply_filters( 'socialsync_post_content', $content, $post['post_id'], $platform_slug );

        // Prepend per-platform prefix text and append hashtags if set.
        $settings = get_option( 'socialsync_settings', array() );
        $prefix_key = $platform_slug . '_prefix_text';
        if ( ! empty( $settings[ $prefix_key ] ) ) {
            $content = $settings[ $prefix_key ] . ' ' . $content;
        }
        $hashtags_key = $platform_slug . '_hashtags';
        if ( ! empty( $settings[ $hashtags_key ] ) ) {
            $content .= "\n\n" . $settings[ $hashtags_key ];
        }

        if ( empty($content) ) {
            return array(
                'success' => false,
                'message' => __( 'Post title is required but missing.', 'social-sync' ),
            );
        }

        // Attempt to publish content using the provider's publish method
        $result = $provider->publish( $content );

        // Log success or failure for debugging and user visibility
        if ( isset($result['success']) ) {
            if ( true === $result['success'] ) {
                // Log successful post with optional post ID from response data
                $this->log_action(
                    isset($post['id']) ? intval( wp_unslash( $post['id'] ) ) : 0,
                    sanitize_text_field($platform_slug),
                    'success',
                    isset($result['data']['id']) ? sprintf(
                        /* translators: %s: Platform post ID */
                        __( 'Posted successfully. Post ID: %s', 'social-sync' ),
                        esc_html( wp_unslash( $result['data']['id'] ) )
                    ) : ''
                );

                // Clear the platform-specific queue data after successful publish
                delete_option('socialsync_platform_' . sanitize_text_field($platform_slug) . '_' . intval( wp_unslash( $post['id'] ) ));
            } else {
                // Log failed post with error message from API response or generic failure
                $this->log_action(
                    isset($post['id']) ? intval( wp_unslash( $post['id'] ) ) : 0,
                    sanitize_text_field($platform_slug),
                    'failed',
                    isset($result['message']) ? 
                        esc_html( wp_unslash( $result['message'] ) ) :
                        sprintf(
                            /* translators: %s: Platform slug */
                            __( 'Failed to post. API returned an error.', 'social-sync' ),
                            sanitize_text_field( wp_unslash( $platform_slug ) )
                        )
                );
            }
        }

        // Return the raw result (success or failure data)
        return apply_filters('socialsync_post_result', $result, $post, $platform_slug);
    }

    /**
     * Log an action to the socialsync_logs option for debugging and user visibility.
     *
     * @param int $post_id Post ID being shared (0 for global actions).
     * @param string $platform Platform slug (x, linkedin, facebook).
     * @param string $status Success or failed status.
     * @param string $message Error message if failed.
     * @return void Logs action to wp_options table.
     */
    private function log_action( int $post_id = 0, string $platform = '', string $status = 'success', string $message = '' ): void {

        // Retrieve existing logs from wp_options table
        $logs = get_option( 'socialsync_logs', array() );

        // Add new log entry with timestamp and metadata
        $log_entry = apply_filters(
            'socialsync_log_entry',
            array(
                'id'      => uniqid(),
                'post_id' => intval( wp_unslash( $post_id ) ),
                'platform'=> sanitize_text_field($platform),
                'status'  => sanitize_text_field($status),
                'message' => sanitize_textarea_field(wp_unslash($message)),
                'date'    => current_time('mysql'),
            )
        );

        // Append to logs array and limit storage to last 100 entries for performance (per plan.md)
        $logs[] = $log_entry;
        if ( count( $logs ) > 100 ) {
            $logs = array_slice($logs, -50);
        }

        // Store updated logs back to wp_options table
        update_option( 'socialsync_logs', $logs, true );
    }
}
