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
            'display'  => __( 'Every Minute', 'socialsync' ),
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
        if ( class_exists( 'SocialSync_Scheduled_Post' ) ) {
            SocialSync_Scheduled_Post::create_table();
        }

        if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
            wp_schedule_event(
                time(),
                'socialsync_every_minute',
                self::CRON_EVENT
            );
        }
    }

    /**
     * Enqueue a WordPress post for auto-publishing to connected platforms.
     *
     * Called when a post is published for the first time. Inserts a row into
     * the scheduled posts table with a 2-minute delay, filtered by the
     * autopost-enabled platforms setting.
     *
     * @param int $post_id The WordPress post ID.
     * @return void
     */
    public function enqueue_post( int $post_id ): void {
        if ( ! $post_id ) {
            return;
        }

        $autopost_platforms = get_option( 'socialsync_autopost_platforms', array() );
        if ( ! is_array( $autopost_platforms ) || empty( $autopost_platforms ) ) {
            return;
        }

        // Only keep platforms that are actually connected.
        $platforms = array();
        foreach ( $autopost_platforms as $slug ) {
            if ( get_option( 'socialsync_' . $slug . '_connected', false ) ) {
                $platforms[] = $slug;
            }
        }

        if ( empty( $platforms ) ) {
            return;
        }

        $local_timestamp = time() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
        $delay_minutes   = max( 0, (int) get_option( 'socialsync_autopost_delay', 2 ) );
        $scheduled_date  = gmdate( 'Y-m-d H:i:s', $local_timestamp + $delay_minutes * MINUTE_IN_SECONDS );

        SocialSync_Scheduled_Post::insert( array(
            'post_id'        => $post_id,
            'title'          => '',
            'content'        => '',
            'platforms'      => wp_json_encode( $platforms ),
            'scheduled_date' => $scheduled_date,
            'status'         => 'scheduled',
        ) );

        SocialSync_Dev_Logger::log( 'post_enqueued', array(
            'post_id'       => $post_id,
            'platforms'     => $platforms,
            'scheduled_for' => $scheduled_date,
            'summary'       => 'WP post #' . $post_id . ' enqueued for auto-posting',
        ) );
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
        if ( ! defined( 'DOING_CRON' ) && ! defined( 'WP_CLI' ) ) {
            return;
        }

        SocialSync_Dev_Logger::log( 'cron_run', array(
            'summary' => 'Cron run started',
        ) );

        $lock_key = 'socialsync_cron_lock';
        $lock_value = time();
        if ( ! add_option( $lock_key, $lock_value, '', 'no' ) ) {
            $existing = get_option( $lock_key );
            if ( $existing && $existing > ( time() - 5 * MINUTE_IN_SECONDS ) ) {
                SocialSync_Dev_Logger::log( 'cron_run', array(
                    'summary' => 'Cron skipped - lock held',
                ) );
                return;
            }
            update_option( $lock_key, $lock_value );
        }

        try {
            // Retrieve all posts from queue table/meta that are ready to be published
            $posts = $this->get_ready_posts();

            SocialSync_Dev_Logger::log( 'cron_run', array(
                'summary' => 'Found ' . count( $posts ) . ' posts to process',
            ) );

            // Process each post in the queue with rate limiting delay
            foreach ( $posts as $post ) {
                try {
                    $log_post_id = 'wp_post' === ( $post['source'] ?? '' ) ? (int) ( $post['post_id'] ?? 0 ) : $post['id'];

                    // Skip if this post is still scheduled for a future date
                    if ( $this->is_post_scheduled_for_future( $post ) ) {
                        continue;
                    }

                    SocialSync_Dev_Logger::log( 'publish_event', array(
                        'post_id'  => $post['id'] ?? 0,
                        'platform' => implode( ',', array_keys( array_filter( $post['platforms'] ) ) ),
                        'summary'  => 'Processing post #' . ( $post['id'] ?? 0 ) . ' (' . ( $post['source'] ?? 'standalone' ) . ')',
                    ) );

                    $all_success = true;
                    $has_dry_run = false;
                    $has_real    = false;

                    // Process each connected platform for the queued post
                    $active_platforms = array_keys( array_filter( $post['platforms'] ) );
                    foreach ( $active_platforms as $platform_slug ) {
                        // Attempt to publish to this platform with timeout protection
                        $result = $this->publish_to_platform( $post, $platform_slug );

                        if ( isset( $result['dry_run'] ) && $result['dry_run'] ) {
                            $has_dry_run = true;
                            continue;
                        }
                        $has_real = true;

                        if ( is_wp_error( $result ) ) {
                            $all_success = false;
                            $this->log_action(
                                $log_post_id,
                                $platform_slug,
                                'failed',
                                $result->get_error_message(),
                                $post['source'] ?? 'standalone'
                            );
                        } elseif ( isset( $result['success'] ) && false === $result['success'] ) {
                            $all_success = false;
                            // Log the failed action for debugging and user visibility
                            $this->log_action(
                                $log_post_id,
                                $platform_slug,
                                'failed',
                                isset( $result['message'] ) ? $result['message'] : '',
                                $post['source'] ?? 'standalone'
                            );
                        }
                    }

                    // Mark post as published or failed to prevent re-processing
                    if ( empty( $active_platforms ) ) {
                        $new_status = 'failed';
                    } else {
                        $new_status = $has_real ? ( $all_success ? 'published' : 'failed' ) : ( $has_dry_run ? 'dry_run' : 'scheduled' );
                    }
                    if ( isset( $post['row_id'] ) && in_array( $post['source'] ?? '', array( 'standalone', 'wp_post' ), true ) ) {
                        SocialSync_Scheduled_Post::update( $post['row_id'], array(
                            'status' => $new_status,
                        ) );
                    }
                } catch ( \Throwable $e ) {
                    SocialSync_Dev_Logger::log( 'publish_error', array(
                        'post_id'  => $post['id'] ?? 0,
                        'error'    => $e->getMessage(),
                        'summary'  => 'Unhandled exception in post #' . ( $post['id'] ?? 0 ) . ': ' . $e->getMessage(),
                    ) );
                    if ( isset( $post['row_id'] ) && in_array( $post['source'] ?? '', array( 'standalone', 'wp_post' ), true ) ) {
                        SocialSync_Scheduled_Post::update( $post['row_id'], array( 'status' => 'failed' ) );
                    }
                    $this->log_action(
                        $log_post_id ?? 0,
                        'system',
                        'failed',
                        $e->getMessage(),
                        $post['source'] ?? 'standalone'
                    );
                }
            }
        } finally {
            delete_option( $lock_key );
        }
    }

    /**
     * Get all posts from the queue that are ready for publishing.
     *
     * @return array Array of post data objects with id, platforms, and schedule date.
     */
    private function get_ready_posts(): array {
        $ready_posts = array();

        // Get standalone scheduled posts from custom table
        $standalone_posts = SocialSync_Scheduled_Post::get_due();
        foreach ( $standalone_posts as $item ) {
            $platforms = json_decode( $item->platforms, true );
            if ( ! is_array( $platforms ) || empty( $platforms ) ) {
                continue;
            }

            $post_id = isset( $item->post_id ) ? intval( $item->post_id ) : 0;
            $ready_posts[] = array(
                'id'        => $item->id,
                'post_id'   => $post_id,
                'title'     => $item->title,
                'content'   => $item->content,
                'platforms' => array_fill_keys( $platforms, true ),
                'scheduled' => $item->scheduled_date,
                'source'    => $post_id > 0 ? 'wp_post' : 'standalone',
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
        $log_post_id = 'wp_post' === ( $post['source'] ?? '' ) ? (int) ( $post['post_id'] ?? 0 ) : $post['id'];
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
                    $log_post_id,
                    sanitize_text_field( $platform_slug ),
                    'failed',
                    __( 'Unknown or unsupported social media platform.', 'socialsync' ),
                    $post['source'] ?? 'standalone'
                );

                return array(
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %s: Platform slug */
                        __( 'Unsupported platform: %s', 'socialsync' ),
                        sanitize_text_field( wp_unslash( $platform_slug ) )
                    ),
                );
        }

        // Get the post content.
        if ( ! empty( $post['post_id'] ) ) {
            $wp_post = get_post( $post['post_id'] );
            if ( $wp_post ) {
                $title     = $wp_post->post_title;
                $permalink = get_permalink( $wp_post );
                $settings  = get_option( 'socialsync_settings', array() );
                $prefix    = $settings[ $platform_slug . '_prefix_text' ] ?? '';
                $hashtags  = $settings[ $platform_slug . '_hashtags' ] ?? '';

                $content  = '';
                if ( ! empty( $prefix ) ) {
                    $content .= $prefix . ': ';
                }
                $content .= $title . ' ' . $permalink;
                if ( ! empty( $hashtags ) ) {
                    $content .= ' ' . $hashtags;
                }
            } else {
                $content = $post['content'] ?? '';
            }
        } else {
            $content = $post['content'] ?? '';
        }
        $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
        $content = apply_filters( 'socialsync_post_content', $content, $post['post_id'], $platform_slug );

        // Extract URL from content for link attachment.
        $post_url = '';
        if ( preg_match( '/https?:\/\/[^\s<>"\'()]+/', $content, $matches ) ) {
            $post_url = rtrim( $matches[0], '.,;:!?)\'"]' );
        }

        if ( empty($content) ) {
            return array(
                'success' => false,
                'message' => __( 'Post title is required but missing.', 'socialsync' ),
            );
        }

        SocialSync_Dev_Logger::log( 'post_built', array(
            'platform'      => $platform_slug,
            'content'       => $content,
            'url'           => $post_url,
            'post_id'       => $post['id'] ?? 0,
            'summary'       => 'Post content ready for ' . $platform_slug,
        ) );

        if ( SocialSync_Dev_Logger::is_dry_run() ) {
            SocialSync_Dev_Logger::log( 'dry_run_skip', array(
                'platform'      => $platform_slug,
                'content'       => $content,
                'url'           => $post_url,
                'endpoint'      => get_class( $provider ),
                'post_id'       => $post['id'] ?? 0,
                'summary'       => 'DRY RUN - Would publish to ' . $platform_slug,
            ) );
            return array(
                'success' => true,
                'message' => 'DRY RUN - skipped',
                'dry_run' => true,
            );
        }

        // Attempt to publish content using the provider's publish method
        SocialSync_Dev_Logger::log( 'publish_attempt', array(
            'platform'      => $platform_slug,
            'content'       => substr( $content, 0, 200 ),
            'url'           => $post_url,
            'summary'       => 'Calling ' . get_class( $provider ) . '::publish() for ' . $platform_slug,
        ) );

        try {
            $result = $provider->publish( $content, $post_url );
            SocialSync_Dev_Logger::log( 'publish_result', array(
                'platform'      => $platform_slug,
                'result_type'   => is_wp_error( $result ) ? 'WP_Error' : ( is_array( $result ) ? 'array' : gettype( $result ) ),
                'has_success'   => is_array( $result ) && isset( $result['success'] ) ? ( $result['success'] ? 'true' : 'false' ) : 'n/a',
                'summary'       => is_wp_error( $result ) ? 'WP_Error: ' . $result->get_error_message() : 'publish() returned OK for ' . $platform_slug,
            ) );
        } catch ( \Throwable $e ) {
            SocialSync_Dev_Logger::log( 'publish_error', array(
                'platform'      => $platform_slug,
                'error'         => $e->getMessage(),
                'file'          => $e->getFile() . ':' . $e->getLine(),
                'summary'       => 'Exception in ' . $platform_slug . ' publish(): ' . $e->getMessage(),
            ) );
            $result = new WP_Error( 'publish_exception', $e->getMessage() );
        }

        // Log success or failure for debugging and user visibility
        if ( isset($result['success']) ) {
            if ( true === $result['success'] ) {
                // Log successful post with optional post ID from response data
                $this->log_action(
                    $log_post_id,
                    sanitize_text_field($platform_slug),
                    'success',
                    isset($result['data']['id']) ? sprintf(
                        /* translators: %s: Platform post ID */
                        __( 'Posted successfully. Post ID: %s', 'socialsync' ),
                        esc_html( wp_unslash( $result['data']['id'] ) )
                    ) : '',
                    $post['source'] ?? 'standalone'
                );
            } else {
                // Log failed post with error message from API response or generic failure
                $this->log_action(
                    $log_post_id,
                    sanitize_text_field($platform_slug),
                    'failed',
                    isset($result['message']) ? 
                        esc_html( wp_unslash( $result['message'] ) ) :
                        sprintf(
                            /* translators: %s: Platform slug */
                            __( 'Failed to post. API returned an error.', 'socialsync' ),
                            sanitize_text_field( wp_unslash( $platform_slug ) )
                        ),
                    $post['source'] ?? 'standalone'
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
    private function log_action( int $post_id = 0, string $platform = '', string $status = 'success', string $message = '', string $type = 'standalone' ): void {

        // Retrieve existing logs from wp_options table
        $logs = get_option( 'socialsync_logs', array() );

        // Add new log entry with timestamp and metadata
        $log_entry = apply_filters(
            'socialsync_log_entry',
            array(
                'id'          => uniqid(),
                'post_id'     => intval( wp_unslash( $post_id ) ),
                'log_version' => 2,
                'platform'    => sanitize_text_field($platform),
                'status'      => sanitize_text_field($status),
                'message'     => sanitize_textarea_field(wp_unslash($message)),
                'date'        => current_time('mysql'),
                'type'        => in_array( $type, array( 'standalone', 'wp_post' ), true ) ? $type : 'standalone',
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
