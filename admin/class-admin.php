<?php
/**
 * SocialSync Admin Class
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * SocialSync_Admin class that handles admin functionality including:
 * - Settings page with tabbed interface
 * - Schedule page for standalone scheduled posts
 * - Connection management for social platforms
 *
 * @package SocialSync
 */
class SocialSync_Admin {

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '0.5.6';

    /**
     * Constructor for admin class initialization.
     *
     * @return void Initializes admin hooks and settings page.
     */
    public function __construct() {
        // Register admin menu with manage_options capability check
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );

        // Enqueue admin scripts and styles for settings page
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Register admin_post handlers for OAuth flows and settings
        add_action( 'admin_post_socialsync_connect_x', array( $this, 'handle_connect_x' ) );
        add_action( 'admin_post_socialsync_disconnect_x', array( $this, 'handle_disconnect_x' ) );
        add_action( 'admin_post_socialsync_connect_linkedin', array( $this, 'handle_connect_linkedin' ) );
        add_action( 'admin_post_socialsync_disconnect_linkedin', array( $this, 'handle_disconnect_linkedin' ) );
        add_action( 'admin_post_socialsync_connect_facebook', array( $this, 'handle_connect_facebook' ) );
        add_action( 'admin_post_socialsync_disconnect_facebook', array( $this, 'handle_disconnect_facebook' ) );
        add_action( 'admin_post_socialsync_connect_bluesky', array( $this, 'handle_connect_bluesky' ) );
        add_action( 'admin_post_socialsync_disconnect_bluesky', array( $this, 'handle_disconnect_bluesky' ) );
        add_action( 'admin_post_socialsync_oauth_callback_x', array( $this, 'handle_oauth_callback_x' ) );
        add_action( 'admin_post_socialsync_oauth_callback_linkedin', array( $this, 'handle_oauth_callback_linkedin' ) );
        add_action( 'admin_post_socialsync_oauth_callback_facebook', array( $this, 'handle_oauth_callback_facebook' ) );
        add_action( 'admin_post_socialsync_delete_log', array( $this, 'handle_delete_log' ) );
        add_action( 'admin_post_socialsync_select_facebook_page', array( $this, 'handle_select_facebook_page' ) );
        add_action( 'admin_post_socialsync_select_linkedin_org', array( $this, 'handle_select_linkedin_org' ) );
        add_action( 'admin_post_socialsync_save_scheduled_post', array( $this, 'handle_save_scheduled_post' ) );
        add_action( 'admin_post_socialsync_delete_scheduled_post', array( $this, 'handle_delete_scheduled_post' ) );
        add_action( 'admin_post_socialsync_cancel_scheduled_post', array( $this, 'handle_cancel_scheduled_post' ) );
        add_action( 'admin_post_socialsync_save_platform_settings', array( $this, 'handle_save_platform_settings' ) );
        add_action( 'admin_post_socialsync_clear_log', array( $this, 'handle_clear_log' ) );
        add_action( 'admin_post_socialsync_save_log_settings', array( $this, 'handle_save_log_settings' ) );
        add_action( 'admin_post_socialsync_save_dev_settings', array( $this, 'handle_save_dev_settings' ) );
        add_action( 'admin_post_socialsync_clear_dev_log', array( $this, 'handle_clear_dev_log' ) );
        add_action( 'socialsync_purge_old_logs', array( $this, 'handle_purge_old_logs' ) );
        add_action( 'admin_notices', array( $this, 'admin_dry_run_notice' ) );
        add_filter( 'set-screen-option', array( $this, 'save_log_screen_option' ), 10, 3 );

        // Hook into WordPress post publishing for auto-posting.
        add_action( 'transition_post_status', array( $this, 'on_post_status_transition' ), 10, 3 );
    }

    /**
     * Register admin menu pages with tabbed interface.
     *
     * @return void Creates admin menus for settings page and social posts dashboard.
     */
    public function add_admin_pages(): void {
        // Add main SocialSync Settings menu under Tools submenu
        $page_slug = 'social-sync-settings';

        add_menu_page(
            __( 'SocialSync', 'socialsync' ),
            __( 'SocialSync', 'socialsync' ),
            'manage_options',
            $page_slug,
            array( $this, 'render_settings_page' ),
            'dashicons-admin-settings',
            92
        );

        add_submenu_page(
            $page_slug,
            __( 'Connections', 'socialsync' ),
            __( 'Connections', 'socialsync' ),
            'manage_options',
            $page_slug,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $page_slug,
            __( 'Schedule', 'socialsync' ),
            __( 'Schedule', 'socialsync' ),
            'manage_options',
            'social-sync-scheduled',
            array( $this, 'render_scheduled_posts_page' )
        );

        $log_hook = add_submenu_page(
            $page_slug,
            __( 'Log', 'socialsync' ),
            __( 'Log', 'socialsync' ),
            'manage_options',
            'social-sync-log',
            array( $this, 'render_logs_page' )
        );
        add_action( "load-{$log_hook}", array( $this, 'add_log_screen_options' ) );

        add_submenu_page(
            $page_slug,
            __( 'Settings', 'socialsync' ),
            __( 'Settings', 'socialsync' ),
            'manage_options',
            'social-sync-dev',
            array( $this, 'render_dev_page' )
        );
    }

    /**
     * Render the main SocialSync settings page with tabbed interface.
     *
     * @return void Outputs HTML for admin settings page.
     */
    public function render_settings_page(): void {
        // Verify user capability before rendering page content
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // Get and sanitize social connection settings from wp_options table
        $settings = get_option( 'socialsync_settings', array() );

        include_once dirname( __FILE__ ) . '/views/connections-page.php';
    }

    /**
     * Render the Schedule admin page.
     *
     * @return void
     */
    public function render_scheduled_posts_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // Ensure the database table exists (creates it if plugin wasn't reactivated)
        SocialSync_Scheduled_Post::create_table();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

        include_once dirname( __FILE__ ) . '/views/scheduled-posts-page.php';
    }

    /**
     * Render the Logs page.
     *
     * @return void
     */
    public function render_logs_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        include_once dirname( __FILE__ ) . '/views/log-page.php';
    }

    /**
     * Register screen options for the Log page.
     */
    public function add_log_screen_options(): void {
        add_screen_option( 'per_page', array(
            'label'   => __( 'Log entries per page', 'socialsync' ),
            'default' => 20,
            'option'  => 'socialsync_log_per_page',
        ) );
    }

    /**
     * Save screen option value for log per page.
     *
     * @param mixed  $status Status value (false to bypass, true to save default).
     * @param string $option The option name.
     * @param mixed  $value  The submitted value.
     * @return mixed
     */
    public function save_log_screen_option( $status, string $option, $value ) {
        if ( 'socialsync_log_per_page' === $option ) {
            $value = intval( $value );
            if ( $value < 1 ) {
                $value = 20;
            } elseif ( $value > 200 ) {
                $value = 200;
            }
            return $value;
        }
        return $status;
    }



    /**
     * Handle saving general settings from the settings page.
     */
    /**
     * Save X API credentials (OAuth 1.0a User Context).
     */
    public function handle_connect_x(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_connect_x', 'socialsync-connect-x-nonce' );

        $client_id     = sanitize_text_field( wp_unslash( $_POST['x_client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['x_client_secret'] ?? '' ) );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_die( esc_html__( 'Client ID and Client Secret are required.', 'socialsync' ) );
        }

        update_option( 'socialsync_x_client_id', $client_id );
        update_option( 'socialsync_x_client_secret', $client_secret );

        $raw_state = wp_generate_password( 32, false );
        $state     = 'x_' . $raw_state;
        set_transient( 'socialsync_x_oauth_state', $state, 300 );

        $code_verifier = bin2hex( random_bytes( 32 ) );
        $code_challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
        set_transient( 'socialsync_x_code_verifier', $code_verifier, 300 );

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_x' );

        $auth_url = 'https://x.com/i/oauth2/authorize?' . http_build_query(
            array(
                'response_type'         => 'code',
                'client_id'             => $client_id,
                'redirect_uri'          => $redirect_uri,
                'scope'                 => 'tweet.read tweet.write users.read offline.access',
                'state'                 => $state,
                'code_challenge'        => $code_challenge,
                'code_challenge_method' => 'S256',
            ),
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        add_filter( 'allowed_redirect_hosts', function( $hosts ) {
            $hosts[] = 'x.com';
            return $hosts;
        } );
        wp_safe_redirect( $auth_url );
        exit;
    }

    /**
     * Disconnect X account.
     */
    public function handle_disconnect_x(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_x' );

        $provider = new SocialSync_X_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1&tab=x' ) );
        exit;
    }

    /**
     * Handle OAuth callback from X after user authorization.
     */
    public function handle_oauth_callback_x(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $error = sanitize_text_field( wp_unslash( $_GET['error'] ?? '' ) );

        if ( ! empty( $error ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( $error ) . '&tab=x' ) );
            exit;
        }

        if ( empty( $code ) || empty( $state ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Invalid OAuth callback parameters.' ) . '&tab=x' ) );
            exit;
        }

        $expected_state = get_transient( 'socialsync_x_oauth_state' );
        if ( $expected_state !== $state ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Invalid OAuth state parameter.' ) . '&tab=x' ) );
            exit;
        }
        delete_transient( 'socialsync_x_oauth_state' );

        $code_verifier = get_transient( 'socialsync_x_code_verifier' );
        delete_transient( 'socialsync_x_code_verifier' );

        if ( empty( $code_verifier ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'PKCE code verifier expired. Please try again.' ) . '&tab=x' ) );
            exit;
        }

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_x' );
        $this->exchange_x_token( $code, $redirect_uri, $code_verifier );

        update_option( 'socialsync_x_connected', true );
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1&tab=x' ) );
        exit;
    }

    /**
     * Exchange authorization code for X access token via OAuth 2.0 token endpoint.
     *
     * @param string $code         Authorization code from OAuth callback.
     * @param string $redirect_uri Redirect URI used in the auth request.
     * @param string $code_verifier PKCE code verifier generated during connect.
     */
    private function exchange_x_token( string $code, string $redirect_uri, string $code_verifier ): void {
        $client_id     = get_option( 'socialsync_x_client_id' );
        $client_secret = get_option( 'socialsync_x_client_secret' );
        $basic_auth    = base64_encode( $client_id . ':' . $client_secret );

        $response = wp_remote_post(
            'https://api.x.com/2/oauth2/token',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $basic_auth,
                ),
                'body' => array(
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                    'code_verifier' => $code_verifier,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_callback_event( 'X token exchange failed', array(
                'error' => $response->get_error_message(),
            ) );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Token exchange failed. Please try again.' ) . '&tab=x' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            $err = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : 'Failed to obtain X access token.' );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( $err ) . '&tab=x' ) );
            exit;
        }

        update_option(
            'socialsync_x_token',
            array(
                'access_token'  => $body['access_token'],
                'refresh_token' => isset( $body['refresh_token'] ) ? $body['refresh_token'] : '',
                'expires_in'    => intval( $body['expires_in'] ?? 7200 ),
                'created_at'    => time(),
            )
        );
    }

    /**
     * Initiate LinkedIn OAuth connection flow.
     */
    public function handle_connect_linkedin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_connect_linkedin', 'socialsync-connect-linkedin-nonce' );

        $client_id     = sanitize_text_field( wp_unslash( $_POST['linkedin_client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['linkedin_client_secret'] ?? '' ) );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_die( esc_html__( 'Client ID and Client Secret are required.', 'socialsync' ) );
        }

        update_option( 'socialsync_linkedin_client_id', $client_id );
        update_option( 'socialsync_linkedin_client_secret', $client_secret );

        $raw_state = wp_generate_password( 32, false );
        $state     = 'linkedin_' . $raw_state;
        set_transient( 'socialsync_linkedin_oauth_state', $state, 300 );

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_linkedin' );

        $scope = 'w_member_social w_organization_social r_organization_social r_liteprofile r_emailaddress';

        $auth_url = 'https://www.linkedin.com/oauth/v2/authorization?'
            . 'response_type=' . rawurlencode( 'code' )
            . '&client_id=' . rawurlencode( $client_id )
            . '&redirect_uri=' . rawurlencode( $redirect_uri )
            . '&scope=' . rawurlencode( $scope )
            . '&state=' . rawurlencode( $state );

        $this->log_callback_event( 'LinkedIn auth URL', array(
            'scope_encoded' => rawurlencode( $scope ),
        ) );

        add_filter( 'allowed_redirect_hosts', function( $hosts ) {
            $hosts[] = 'www.linkedin.com';
            return $hosts;
        } );
        wp_safe_redirect( $auth_url );
        exit;
    }

    /**
     * Disconnect LinkedIn account.
     */
    public function handle_disconnect_linkedin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_linkedin' );

        $provider = new SocialSync_LinkedIn_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1&tab=linkedin' ) );
        exit;
    }

    /**
     * Initiate Facebook OAuth connection flow.
     */
    public function handle_connect_facebook(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_connect_facebook', 'socialsync-connect-facebook-nonce' );

        $client_id     = sanitize_text_field( wp_unslash( $_POST['facebook_client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['facebook_client_secret'] ?? '' ) );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_die( esc_html__( 'Client ID and Client Secret are required.', 'socialsync' ) );
        }

        update_option( 'socialsync_facebook_app_id', $client_id );
        update_option( 'socialsync_facebook_app_secret', $client_secret );

        $raw_state = wp_generate_password( 32, false );
        $state     = 'facebook_' . $raw_state;
        set_transient( 'socialsync_facebook_oauth_state', $state, 300 );

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_facebook' );

        $auth_url = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query( array(
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'state'         => $state,
            'scope'         => 'pages_manage_posts,pages_read_engagement',
        ), '', '&', PHP_QUERY_RFC3986 );

        add_filter( 'allowed_redirect_hosts', function( $hosts ) {
            $hosts[] = 'www.facebook.com';
            return $hosts;
        } );
        wp_safe_redirect( $auth_url );
        exit;
    }

    /**
     * Disconnect Facebook account.
     */
    public function handle_disconnect_facebook(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_facebook' );

        $provider = new SocialSync_Facebook_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1&tab=facebook' ) );
        exit;
    }

    /**
     * Connect Bluesky account via session (app password).
     */
    public function handle_connect_bluesky(): void {
        try {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
            }
            check_admin_referer( 'socialsync_connect_bluesky', 'socialsync-connect-bluesky-nonce' );

            $identifier   = sanitize_text_field( wp_unslash( $_POST['bluesky_identifier'] ?? '' ) );
            $app_password = sanitize_text_field( wp_unslash( $_POST['bluesky_app_password'] ?? '' ) );

            if ( empty( $identifier ) || empty( $app_password ) ) {
                wp_die( esc_html__( 'Identifier and App Password are required.', 'socialsync' ) );
            }

            update_option( 'socialsync_bluesky_identifier', $identifier );
            update_option( 'socialsync_bluesky_app_password', $app_password );

            $provider = new SocialSync_Bluesky_Provider();
            if ( ! $provider->is_connected() ) {
                $result = $provider->refresh_token();
                if ( ! $result ) {
                    delete_option( 'socialsync_bluesky_identifier' );
                    delete_option( 'socialsync_bluesky_app_password' );
                    wp_die( esc_html__( 'Failed to authenticate with Bluesky. Check your identifier and App Password.', 'socialsync' ) );
                }
            }

            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1&tab=bluesky' ) );
            exit;
        } catch ( \Throwable $e ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'SocialSync Bluesky fatal error: ' . sanitize_text_field( $e->getMessage() ) );
            wp_die( esc_html__( 'Bluesky connection failed. Check the SocialSync log for details.', 'socialsync' ) );
        }
    }

    /**
     * Disconnect Bluesky account.
     */
    public function handle_disconnect_bluesky(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_bluesky' );

        $provider = new SocialSync_Bluesky_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1&tab=bluesky' ) );
        exit;
    }

    /**
     * Save per-platform prefix text and hashtags.
     */
    public function handle_save_platform_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' );

        $platform   = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $allowed    = array( 'x', 'linkedin', 'facebook', 'bluesky' );
        if ( ! in_array( $platform, $allowed, true ) ) {
            wp_die( esc_html__( 'Invalid platform.', 'socialsync' ) );
        }
        $prefix     = sanitize_text_field( wp_unslash( $_POST['prefix_text'] ?? '' ) );
        $hashtags   = sanitize_text_field( wp_unslash( $_POST['hashtags'] ?? '' ) );
        $settings   = get_option( 'socialsync_settings', array() );
        $settings[ $platform . '_prefix_text' ] = $prefix;
        $settings[ $platform . '_hashtags' ]    = $hashtags;
        update_option( 'socialsync_settings', $settings );

        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : '';
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&updated=1' ) . ( in_array( $tab, array( 'x', 'linkedin', 'facebook', 'bluesky' ), true ) ? '&tab=' . $tab : '' ) );
        exit;
    }

    /**
     * Handle selecting a Facebook Page for posting.
     */
    public function handle_select_facebook_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_select_facebook_page', 'socialsync-select-facebook-page-nonce' );

        $page_id = sanitize_text_field( wp_unslash( $_POST['facebook_page_id'] ?? '' ) );

        if ( empty( $page_id ) ) {
            delete_option( 'socialsync_facebook_page_id' );
            delete_option( 'socialsync_facebook_page_token' );
        } else {
            $fb_pages   = get_option( 'socialsync_facebook_pages_cache', array() );
            $page_token = '';
            foreach ( $fb_pages as $page ) {
                if ( isset( $page['id'] ) && $page['id'] === $page_id && isset( $page['access_token'] ) ) {
                    $page_token = $page['access_token'];
                    break;
                }
            }
            if ( empty( $page_token ) ) {
                wp_die( esc_html__( 'Selected Facebook page not found. Please reconnect your Facebook account.', 'socialsync' ) );
            }
            update_option( 'socialsync_facebook_page_id', $page_id );
            update_option( 'socialsync_facebook_page_token', $page_token );
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : '';
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&page_selected=1' ) . ( in_array( $tab, array( 'x', 'linkedin', 'facebook', 'bluesky' ), true ) ? '&tab=' . $tab : '' ) );
        exit;
    }

    /**
     * Handle selecting a LinkedIn Organization for posting.
     */
    public function handle_select_linkedin_org(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_select_linkedin_org', 'socialsync-select-linkedin-org-nonce' );

        $org_id = sanitize_text_field( wp_unslash( $_POST['linkedin_org_id'] ?? '' ) );

        if ( empty( $org_id ) ) {
            delete_option( 'socialsync_linkedin_org_id' );
        } else {
            update_option( 'socialsync_linkedin_org_id', $org_id );
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : '';
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&org_selected=1' ) . ( in_array( $tab, array( 'x', 'linkedin', 'facebook', 'bluesky' ), true ) ? '&tab=' . $tab : '' ) );
        exit;
    }

    /**
     * Handle OAuth callback from LinkedIn.
     */
    public function handle_oauth_callback_linkedin(): void {
        $this->process_oauth_callback( 'linkedin' );
    }

    /**
     * Handle OAuth callback from Facebook.
     */
    public function handle_oauth_callback_facebook(): void {
        $this->process_oauth_callback( 'facebook' );
    }

    /**
     * Process an OAuth callback for a given platform.
     */
    private function process_oauth_callback( string $platform ): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $safe_get = $_GET;
        unset( $safe_get['code'], $safe_get['state'] );
        $this->log_callback_event( 'OAuth callback received for ' . $platform, array(
            'get_params' => $safe_get,
        ) );

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->log_callback_event( 'Permission denied', array() );
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
        $error = sanitize_text_field( wp_unslash( $_GET['error'] ?? '' ) );

        $tab_param = in_array( $platform, array( 'x', 'linkedin', 'facebook', 'bluesky' ), true ) ? '&tab=' . $platform : '';

        if ( ! empty( $error ) ) {
            $this->log_callback_event( 'OAuth error returned: ' . $error, array() );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( $error ) . $tab_param ) );
            exit;
        }

        if ( empty( $code ) || empty( $state ) ) {
            $this->log_callback_event( 'Missing code or state', array(
                'code'  => $code,
                'state' => $state,
            ) );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Invalid OAuth callback parameters.' ) . $tab_param ) );
            exit;
        }

        $expected_state = get_transient( 'socialsync_' . $platform . '_oauth_state' );
        $this->log_callback_event( 'State comparison', array(
            'expected' => $expected_state,
            'received' => $state,
            'match'    => $expected_state === $state,
        ) );
        if ( $expected_state !== $state ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Invalid OAuth state parameter.' ) . $tab_param ) );
            exit;
        }
        delete_transient( 'socialsync_' . $platform . '_oauth_state' );

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_' . $platform );

        switch ( $platform ) {
            case 'linkedin':
                $this->exchange_linkedin_token( $code, $redirect_uri );
                break;
            case 'facebook':
                $this->exchange_facebook_token( $code, $redirect_uri );
                break;
            default:
                $this->log_callback_event( 'Unknown platform', array( 'platform' => $platform ) );
                wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Unknown platform.' ) ) );
                exit;
        }

        $this->log_callback_event( 'Token exchange complete, connected', array( 'platform' => $platform ) );
        update_option( 'socialsync_' . $platform . '_connected', true );
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1' . $tab_param ) );
        exit;
    }

    /**
     * Log an OAuth callback event to the global socialsync_logs.
     */
    private function log_callback_event( string $message, array $context = array() ): void {
        $logs = get_option( 'socialsync_logs', array() );
        $full_message = $message;
        if ( ! empty( $context ) ) {
            $full_message .= ' | ' . wp_json_encode( $context );
        }
        $logs[] = array(
            'id'       => uniqid(),
            'post_id'  => 0,
            'platform' => 'oauth',
            'status'   => 'info',
            'message'  => $full_message,
            'date'     => current_time( 'mysql' ),
            'type'     => 'oauth',
        );
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }
        update_option( 'socialsync_logs', $logs );
    }

    /**
     * Exchange authorization code for LinkedIn access token.
     *
     * @param string $code         Authorization code from OAuth callback.
     * @param string $redirect_uri Redirect URI used in the auth request.
     */
    private function exchange_linkedin_token( string $code, string $redirect_uri ): void {
        $client_id     = get_option( 'socialsync_linkedin_client_id' );
        $client_secret = get_option( 'socialsync_linkedin_client_secret' );

        $this->log_callback_event( 'Exchanging authorization code for LinkedIn token', array(
            'redirect_uri' => $redirect_uri,
        ) );

        $response = wp_remote_post( 'https://www.linkedin.com/oauth/v2/accessToken', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log_callback_event( 'LinkedIn token exchange wp_error', array(
                'error' => $response->get_error_message(),
            ) );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Token exchange failed. Please try again.' ) . '&tab=linkedin' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            $this->log_callback_event( 'LinkedIn token exchange failed - no access_token in response', array(
                'error' => isset( $body['error'] ) ? sanitize_text_field( $body['error'] ) : 'unknown',
            ) );
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . rawurlencode( 'Failed to obtain LinkedIn access token.' ) . '&tab=linkedin' ) );
            exit;
        }

        $this->log_callback_event( 'LinkedIn token exchange succeeded', array(
            'expires_in' => $body['expires_in'] ?? 'unknown',
        ) );

        update_option( 'socialsync_linkedin_token', array(
            'access_token'  => $body['access_token'],
            'refresh_token' => isset( $body['refresh_token'] ) ? $body['refresh_token'] : '',
            'expires_in'    => intval( $body['expires_in'] ?? 86400 ),
            'created_at'    => time(),
        ) );

        // Fetch and store LinkedIn person ID.
        $me_response = wp_remote_get( 'https://api.linkedin.com/v2/me', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $body['access_token'],
            ),
        ) );

        if ( ! is_wp_error( $me_response ) ) {
            $me_body = json_decode( wp_remote_retrieve_body( $me_response ), true );
            if ( isset( $me_body['id'] ) ) {
                update_option( 'socialsync_linkedin_person_id', sanitize_text_field( $me_body['id'] ) );
            }
        }
    }

    /**
     * Exchange authorization code for Facebook access token.
     *
     * @param string $code         Authorization code from OAuth callback.
     * @param string $redirect_uri Redirect URI used in the auth request.
     */
    private function exchange_facebook_token( string $code, string $redirect_uri ): void {
        $client_id     = get_option( 'socialsync_facebook_app_id' );
        $client_secret = get_option( 'socialsync_facebook_app_secret' );

        $token_url = add_query_arg( array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'client_secret' => $client_secret,
            'code'          => $code,
        ), 'https://graph.facebook.com/v18.0/oauth/access_token' );

        $response = wp_remote_get( $token_url, array(
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_die( esc_html( $response->get_error_message() ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            wp_die( esc_html__( 'Failed to obtain Facebook access token.', 'socialsync' ) );
        }

        update_option( 'socialsync_facebook_token', array(
            'access_token' => $body['access_token'],
            'expires_in'   => intval( $body['expires_in'] ?? 5184000 ),
            'created_at'   => time(),
        ) );

        // Fetch and cache Facebook Pages.
        $provider = new SocialSync_Facebook_Provider();
        $pages = $provider->get_pages();
        if ( ! is_wp_error( $pages ) && ! empty( $pages ) ) {
            update_option( 'socialsync_facebook_pages_cache', $pages );
        }
    }

    /**
     * Handle deleting a single log entry.
     */
    public function handle_delete_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_delete_log' );

        $log_id = sanitize_text_field( wp_unslash( $_GET['log_id'] ?? '' ) );
        if ( ! empty( $log_id ) ) {
            $logs = get_option( 'socialsync_logs', array() );
            foreach ( $logs as $index => $log ) {
                if ( isset( $log['id'] ) && $log['id'] === $log_id ) {
                    array_splice( $logs, $index, 1 );
                    break;
                }
            }
            update_option( 'socialsync_logs', $logs );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-log&log_deleted=1' ) );
        exit;
    }

    /**
     * Clear all log entries and standalone scheduled posts.
     */
    public function handle_clear_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_clear_log' );

        delete_option( 'socialsync_logs' );

        if ( class_exists( 'SocialSync_Scheduled_Post' ) ) {
            SocialSync_Scheduled_Post::clear_all();
        }

        if ( class_exists( 'SocialSync_Dev_Logger' ) ) {
            SocialSync_Dev_Logger::clear();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-log&log_cleared=1' ) );
        exit;
    }

    /**
     * Save log retention settings.
     */
    public function handle_save_log_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_save_log_settings', 'socialsync-log-settings-nonce' );

        $days = isset( $_POST['log_retention_days'] ) ? intval( $_POST['log_retention_days'] ) : 0;
        if ( $days < 0 ) {
            $days = 0;
        }
        update_option( 'socialsync_log_retention_days', $days );

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-log&settings_saved=1' ) );
        exit;
    }

    /**
     * Purge log entries older than the retention period.
     */
    public function handle_purge_old_logs(): void {
        if ( ! defined( 'DOING_CRON' ) ) {
            return;
        }

        $retention_days = intval( get_option( 'socialsync_log_retention_days', 0 ) );
        if ( $retention_days <= 0 ) {
            return;
        }

        $cutoff = strtotime( "-{$retention_days} days" );
        if ( ! $cutoff ) {
            return;
        }

        // Purge the socialsync_logs option (WP post logs)
        $logs = get_option( 'socialsync_logs', array() );
        if ( ! empty( $logs ) ) {
            $remaining = array();
            foreach ( $logs as $entry ) {
                $entry_date = isset( $entry['date'] ) ? strtotime( $entry['date'] ) : 0;
                if ( $entry_date && $entry_date >= $cutoff ) {
                    $remaining[] = $entry;
                }
            }
            if ( count( $remaining ) !== count( $logs ) ) {
                update_option( 'socialsync_logs', $remaining );
            }
        }

        // Purge standalone scheduled posts that have reached a terminal status (published, failed, cancelled)
        global $wpdb;
        $table = $wpdb->prefix . 'socialsync_scheduled_posts'; // phpcs:ignore WordPress.DB.DatabaseValue
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE status IN ('published', 'failed', 'cancelled', 'dry_run') AND scheduled_date < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                gmdate( 'Y-m-d H:i:s', $cutoff )
            )
        );
    }

    /**
     * Handle WordPress post status transitions for auto-posting.
     *
     * Fires when a post's status changes. On initial publish (not update/re-publish),
     * enqueues the post for auto-publishing to connected platforms.
     *
     * @param string   $new_status New post status.
     * @param string   $old_status Old post status.
     * @param \WP_Post $post       The post object.
     * @return void
     */
    public function on_post_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }
        if ( 'post' !== $post->post_type ) {
            return;
        }
        SocialSync_Scheduler::get_instance()->enqueue_post( $post->ID );
    }

    /**
     * Enqueue admin scripts and styles for SocialSync settings page.
     *
     * @param string $hook Current admin page hook.
     * @return void Loads admin assets with proper nonce verification.
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Check if current admin page is a SocialSync page
        if ( strpos($hook, 'social-sync') === false ) {
            return;
        }

        // Enqueue admin CSS and JS with nonce verification for security
        wp_enqueue_style('socialsync-admin', plugin_dir_url( __FILE__ ) . 'css/socialsync.css', array( 'list-tables' ), $this->version );

        // Use wp_enqueue_script to load admin JavaScript with nonce verification
        wp_enqueue_script('socialsync-admin-script', plugin_dir_url( __FILE__ ) . 'js/socialsync.js', array('jquery'), $this->version, true);

        wp_localize_script('socialsync-admin-script', 'SocialSyncAdmin', array(
            'confirm_delete' => __( 'Delete this scheduled post?', 'socialsync' ),
            'confirm_cancel' => __( 'Cancel this scheduled post?', 'socialsync' ),
        ));
    }

    /**
     * Handle saving a new or edited scheduled post.
     */
    public function handle_save_scheduled_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_save_scheduled_post', 'socialsync-scheduled-post-nonce' );

        $title          = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $content        = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $platforms      = isset( $_POST['platforms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['platforms'] ) ) : array();
        $scheduled_date = sanitize_text_field( wp_unslash( $_POST['scheduled_date'] ?? '' ) );
        $edit_id        = isset( $_POST['edit_id'] ) ? intval( $_POST['edit_id'] ) : 0;
        $post_now       = isset( $_POST['post_now'] );

        if ( empty( $content ) ) {
            wp_die( esc_html__( 'Content is required.', 'socialsync' ) );
        }
        if ( empty( $platforms ) ) {
            wp_die( esc_html__( 'Select at least one platform.', 'socialsync' ) );
        }

        if ( $post_now ) {
            $scheduled_date = current_time( 'mysql' );
        } elseif ( empty( $scheduled_date ) ) {
            wp_die( esc_html__( 'Scheduled date is required.', 'socialsync' ) );
        }

        $data = array(
            'title'          => $title,
            'content'        => $content,
            'platforms'      => wp_json_encode( $platforms ),
            'scheduled_date' => str_replace( 'T', ' ', $scheduled_date ),
            'status'         => 'scheduled',
        );

        if ( $edit_id ) {
            SocialSync_Scheduled_Post::update( $edit_id, $data );
            $insert_id = $edit_id;
        } else {
            $insert_id = SocialSync_Scheduled_Post::insert( $data );
        }

        if ( $post_now ) {
            $platform_errors = array();
            $platform_ok     = array();

            foreach ( $platforms as $platform_slug ) {
                switch ( $platform_slug ) {
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
                        $platform_errors[ $platform_slug ] = __( 'Unknown platform', 'socialsync' );
                        continue 2;
                }

                $post_url = '';
                if ( preg_match( '/https?:\/\/[^\s<>"\'()]+/', $content, $matches ) ) {
                    $post_url = rtrim( $matches[0], '.,;:!?)\'"]' );
                }

                SocialSync_Dev_Logger::log( 'post_built', array(
                    'platform' => $platform_slug,
                    'content'  => $content,
                    'url'      => $post_url,
                    'summary'  => 'Post Now content ready for ' . $platform_slug,
                ) );

                if ( SocialSync_Dev_Logger::is_dry_run() ) {
                    SocialSync_Dev_Logger::log( 'dry_run_skip', array(
                        'platform' => $platform_slug,
                        'content'  => $content,
                        'url'      => $post_url,
                        'summary'  => 'DRY RUN - Would publish to ' . $platform_slug,
                    ) );
                    $platform_ok[] = $platform_slug;
                    continue;
                }

                $result = $provider->publish( $content, $post_url );

                if ( is_wp_error( $result ) ) {
                    $platform_errors[ $platform_slug ] = $result->get_error_message();
                } elseif ( isset( $result['success'] ) && $result['success'] ) {
                    $platform_ok[] = $platform_slug;
                } else {
                    $msg = isset( $result['message'] ) ? $result['message'] : __( 'Unknown error', 'socialsync' );
                    $platform_errors[ $platform_slug ] = $msg;
                }
            }

            $all_success   = empty( $platform_errors );
            $all_dry_run   = SocialSync_Dev_Logger::is_dry_run() && empty( $platform_errors );
            SocialSync_Scheduled_Post::update( $insert_id, array(
                'status'         => $all_dry_run ? 'dry_run' : ( $all_success ? 'published' : 'failed' ),
                'error_message'  => $all_success ? '' : wp_json_encode( $platform_errors ),
            ) );

            $redirect_url = admin_url( 'admin.php?page=social-sync-scheduled' );
            $query_args   = array();
            if ( $all_dry_run ) {
                $query_args['dry_run'] = 1;
            } else {
                foreach ( $platform_ok as $slug ) {
                    $query_args[ 'success_' . $slug ] = 1;
                }
            }
            foreach ( $platform_errors as $slug => $msg ) {
                $query_args[ 'error_' . $slug ] = rawurlencode( $msg );
            }
            wp_safe_redirect( add_query_arg( $query_args, $redirect_url ) );
            exit;
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-scheduled&saved=1' ) );
        }
        exit;
    }

    /**
     * Handle deleting a scheduled post.
     */
    public function handle_delete_scheduled_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'socialsync_delete_scheduled_post_' . $id );
        if ( empty( $id ) ) {
            wp_die( esc_html__( 'Invalid post ID.', 'socialsync' ) );
        }

        SocialSync_Scheduled_Post::delete( $id );

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-scheduled&deleted=1' ) );
        exit;
    }

    /**
     * Handle cancelling a scheduled post.
     */
    public function handle_cancel_scheduled_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'socialsync_cancel_scheduled_post_' . $id );
        if ( empty( $id ) ) {
            wp_die( esc_html__( 'Invalid post ID.', 'socialsync' ) );
        }

        SocialSync_Scheduled_Post::update( $id, array( 'status' => 'cancelled' ) );

        $redirect = isset( $_GET['_redirect'] ) && 'log' === sanitize_text_field( wp_unslash( $_GET['_redirect'] ) )
            ? admin_url( 'admin.php?page=social-sync-log&cancelled=1' )
            : admin_url( 'admin.php?page=social-sync-scheduled&cancelled=1' );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render the developer debug page.
     */
    public function render_dev_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }

        include_once dirname( __FILE__ ) . '/views/settings-page.php';
    }

    /**
     * Save developer mode settings.
     */
    public function handle_save_dev_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_save_dev_settings', 'socialsync-dev-settings-nonce' );

        update_option( 'socialsync_dev_mode', isset( $_POST['dev_mode'] ) ? 1 : 0 );

        if ( isset( $_POST['dev_mode'] ) ) {
            update_option( 'socialsync_dry_run', isset( $_POST['dry_run'] ) ? 1 : 0 );
        } else {
            update_option( 'socialsync_dry_run', 0 );
        }

        $platforms = isset( $_POST['autopost_platforms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['autopost_platforms'] ) ) : array();
        $allowed   = array( 'x', 'linkedin', 'facebook', 'bluesky' );
        $platforms = array_intersect( $platforms, $allowed );
        update_option( 'socialsync_autopost_platforms', array_values( $platforms ) );

        $delay = isset( $_POST['autopost_delay'] ) ? max( 0, intval( wp_unslash( $_POST['autopost_delay'] ) ) ) : 2;
        update_option( 'socialsync_autopost_delay', $delay );

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-dev&saved=1' ) );
        exit;
    }

    /**
     * Clear the developer event log.
     */
    public function handle_clear_dev_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'socialsync' ) );
        }
        check_admin_referer( 'socialsync_clear_dev_log' );

        SocialSync_Dev_Logger::clear();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-dev&cleared=1' ) );
        exit;
    }

    /**
     * Show a global admin notice when Dry Run mode is active.
     */
    public function admin_dry_run_notice(): void {
        if ( ! current_user_can( 'manage_options' ) || ! SocialSync_Dev_Logger::is_dry_run() ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'social-sync' ) ) {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e( 'SocialSync Dry Run mode is active. API calls will be logged but not sent.', 'socialsync' ); ?></p>
        </div>
        <?php
    }
}
