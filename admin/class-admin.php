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
 * - Post meta box integration
 * - Dashboard widget for quick stats
 *
 * @package SocialSync
 */
class SocialSync_Admin {

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '1.0.0';

    /**
     * Constructor for admin class initialization.
     *
     * @return void Initializes admin hooks and settings page.
     */
    public function __construct() {
        // Register admin menu with manage_options capability check
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );

        // Add post meta box on Classic Editor for social checkboxes and scheduling options
        add_action( 'add_meta_boxes', array( $this, 'register_post_metabox' ) );

        // Save post meta box data when post is published or updated manually
        add_action( 'publish_post', array( $this, 'save_post_data' ), 10, 2 );
        add_action( 'edit_attachment', array( $this, 'save_post_data' ) ); // For media library posts

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
        add_action( 'socialsync_purge_old_logs', array( $this, 'handle_purge_old_logs' ) );
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
            __( 'SocialSync', 'social-sync' ),
            __( 'SocialSync', 'social-sync' ),
            'manage_options',
            $page_slug,
            array( $this, 'render_settings_page' ),
            'dashicons-admin-settings',
            92
        );

        add_submenu_page(
            $page_slug,
            __( 'Connections', 'social-sync' ),
            __( 'Connections', 'social-sync' ),
            'manage_options',
            $page_slug,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            $page_slug,
            __( 'Schedule', 'social-sync' ),
            __( 'Schedule', 'social-sync' ),
            'manage_options',
            'social-sync-scheduled',
            array( $this, 'render_scheduled_posts_page' )
        );

        add_submenu_page(
            $page_slug,
            __( 'Log', 'social-sync' ),
            __( 'Log', 'social-sync' ),
            'manage_options',
            'social-sync-log',
            array( $this, 'render_logs_page' )
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
            wp_die( __( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        // Get and sanitize social connection settings from wp_options table
        $settings = get_option( 'socialsync_settings', array() );

        include_once dirname( __FILE__ ) . '/views/settings-page.php';
    }

    /**
     * Render the Schedule admin page.
     *
     * @return void
     */
    public function render_scheduled_posts_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        // Ensure the database table exists (creates it if plugin wasn't reactivated)
        SocialSync_Scheduled_Post::create_table();

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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        include_once dirname( __FILE__ ) . '/views/logs-page.php';
    }

    /**
     * Register post meta box for social settings in Classic Editor.
     *
     * @param string $post_type The current post type being edited.
     * @return void Adds meta box to post editing screen.
     */
    public function register_post_metabox( string $post_type ): void {
        if ( 'post' !== $post_type ) {
            return;
        }

        add_meta_box(
            'socialsync_settings',
            esc_html__( 'SocialSync', 'social-sync' ),
            array( $this, 'render_post_metabox' ),
            'post',
            'advanced',
            'default',
            true
        );
    }

    /**
     * Render the post meta box HTML with social checkboxes and scheduling options.
     *
     * @param WP_Post $post Current post object for Classic Editor.
     * @return void Outputs meta box content.
     */
    public function render_post_metabox( WP_Post $post ): void {
        $current_id = get_the_ID();

        echo '<div class="socialsync-meta-box">';

        echo '<hr class="wp-editor-separator">';
        echo '<h3>' . esc_html__( 'Post to', 'social-sync' ) . '</h3>';
        echo '<p>';

        $platform_data = array(
            'x' => array( 'name' => 'X (Twitter)', 'input_name' => '_socialsync_platforms[x]' ),
            'linkedin' => array( 'name' => 'LinkedIn', 'input_name' => '_socialsync_platforms[linkedin]' ),
            'facebook' => array( 'name' => 'Facebook', 'input_name' => '_socialsync_platforms[facebook]' ),
        );

        $selected_platforms = get_post_meta( $current_id, '_socialsync_platforms', true );
        if ( ! is_array( $selected_platforms ) ) {
            $selected_platforms = array();
        }

        foreach ( $platform_data as $key => $platform ) {
            $is_selected = ! empty( $selected_platforms[ $key ] );
            printf(
                '<label style="display:inline-block;margin-right:16px"><input type="checkbox" name="%s" value="%s" %s /> %s</label>',
                esc_attr( $platform['input_name'] ),
                esc_attr( $key ),
                checked( $is_selected, true, false ),
                esc_html( $platform['name'] )
            );
        }

        echo '</p>';

        echo '<hr class="wp-editor-separator">';
        echo '<h3>' . esc_html__( 'Custom Content', 'social-sync' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Leave blank to use the default format (Title + Excerpt + Permalink).', 'social-sync' ) . '</p>';

        printf(
            '<p><label for="socialsync_x_content">%s</label>
            <textarea id="socialsync_x_content" name="%s" class="large-text" rows="3" placeholder="%s"></textarea></p>
            <p><label for="socialsync_linkedin_content">%s</label>
            <textarea id="socialsync_linkedin_content" name="%s" class="large-text" rows="3" placeholder="%s"></textarea></p>
            <p><label for="socialsync_facebook_content">%s</label>
            <textarea id="socialsync_facebook_content" name="%s" class="large-text" rows="3" placeholder="%s"></textarea></p>',
            esc_html__( 'X (Twitter)', 'social-sync' ),
            esc_attr( '_socialsync_x_content' ),
            esc_attr__( 'Custom X post content...', 'social-sync' ),
            esc_html__( 'LinkedIn', 'social-sync' ),
            esc_attr( '_socialsync_linkedin_content' ),
            esc_attr__( 'Custom LinkedIn post content...', 'social-sync' ),
            esc_html__( 'Facebook', 'social-sync' ),
            esc_attr( '_socialsync_facebook_content' ),
            esc_attr__( 'Custom Facebook post content...', 'social-sync' )
        );

        echo '<hr class="wp-editor-separator">';
        echo '<h3>' . esc_html__( 'Schedule', 'social-sync' ) . '</h3>';

        $schedule_type = get_post_meta( $current_id, '_socialsync_schedule_type', true );
        if ( empty( $schedule_type ) ) {
            $schedule_type = 'immediate';
        }

        echo '<p>';
        printf(
            '<select name="%s" id="socialsync_schedule_type">
                <option value="immediate" %s>%s</option>
                <option value="scheduled" %s>%s</option>
            </select>',
            esc_attr( '_socialsync_schedule_type' ),
            selected( $schedule_type, 'immediate', false ),
            esc_html__( 'Immediate', 'social-sync' ),
            selected( $schedule_type, 'scheduled', false ),
            esc_html__( 'Scheduled', 'social-sync' )
        );
        echo '</p>';

        $current_schedule_date = get_post_meta( $current_id, '_socialsync_schedule_date', true );
        echo '<p>';
        printf(
            '<input type="datetime-local" id="socialsync_schedule_date" name="%s" value="%s" class="large-text" />',
            esc_attr( '_socialsync_schedule_date' ),
            esc_attr( $current_schedule_date )
        );
        echo '</p>';

        $publish_on_save = get_post_meta( $current_id, '_socialsync_publish_on_save', true );
        printf(
            '<p><label><input type="checkbox" name="%s" value="1" %s /> %s</label></p>',
            esc_attr( '_socialsync_publish_on_save' ),
            checked( $publish_on_save, '1', false ),
            esc_html__( 'Auto-publish when post is saved', 'social-sync' )
        );

        wp_nonce_field( 'socialsync_save_post', '_socialsync_nonce' );

        echo '<hr class="wp-editor-separator">';
        echo '<h3>' . esc_html__( 'Preview', 'social-sync' ) . '</h3>';
        echo '<div id="socialsync-preview-area" data-featured-image="' . esc_url( get_the_post_thumbnail_url( $current_id, 'medium' ) ?: '' ) . '">';
        foreach ( array('x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook') as $key => $label ) {
            printf(
                '<div class="socialsync-platform-preview" data-platform="%s">
                    <strong>%s</strong>
                    <img class="socialsync-preview-image" src="" alt="" />
                    <pre class="socialsync-preview-text"></pre>
                </div>',
                esc_attr( $key ),
                esc_html( $label )
            );
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Save social settings to wp_postmeta table when post is published.
     *
     * @param int  $post_id Post ID.
     * @param WP_Post|null $post Post object if available.
     * @return void Saves post data to wp_postmeta table.
     */
    public function save_post_data( int $post_id, ?WP_Post $post = null ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['_socialsync_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_socialsync_nonce'] ), 'socialsync_save_post' ) ) {
            return;
        }

        update_post_meta( $post_id, '_socialsync_platforms', array(
            'x' => isset( $_POST['_socialsync_platforms[x]'] ),
            'linkedin' => isset( $_POST['_socialsync_platforms[linkedin]'] ),
            'facebook' => isset( $_POST['_socialsync_platforms[facebook]'] ),
        ));

        update_post_meta( $post_id, '_socialsync_schedule_type', sanitize_text_field( wp_unslash( $_POST['_socialsync_schedule_type'] ?? 'immediate' ) ) );

        if ( ! empty( $_POST['_socialsync_schedule_date'] ) ) {
            update_post_meta( $post_id, '_socialsync_schedule_date', sanitize_text_field( wp_unslash( $_POST['_socialsync_schedule_date'] ) ) );
        } else {
            delete_post_meta( $post_id, '_socialsync_schedule_date' );
        }

        update_post_meta( $post_id, '_socialsync_publish_on_save', isset( $_POST['_socialsync_publish_on_save'] ) ? '1' : '' );

        foreach ( array( 'x', 'linkedin', 'facebook' ) as $platform ) {
            $key = '_socialsync_' . $platform . '_content';
            if ( isset( $_POST[ $key ] ) && ! empty( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }

        // Trigger posting if platforms are selected and post is published
        if ( 'publish' === get_post_status( $post_id ) && 'post' === get_post_type( $post_id ) ) {
            $platforms = get_post_meta( $post_id, '_socialsync_platforms', true );
            if ( is_array( $platforms ) ) {
                $has_platforms = false;
                foreach ( $platforms as $key => $val ) {
                    if ( $val ) {
                        $has_platforms = true;
                        break;
                    }
                }
                if ( $has_platforms ) {
                    $schedule_type = get_post_meta( $post_id, '_socialsync_schedule_type', true );
                    $schedule_date = get_post_meta( $post_id, '_socialsync_schedule_date', true );
                    SocialSync_Scheduler::get_instance()->enqueue_post( $post_id, $schedule_type, $schedule_date );
                }
            }
        }
    }

    /**
     * Handle saving general settings from the settings page.
     */
    /**
     * Save X API credentials (OAuth 1.0a User Context).
     */
    public function handle_connect_x(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_connect_x', 'socialsync-connect-x-nonce' );

        $api_key             = sanitize_text_field( wp_unslash( $_POST['x_api_key'] ?? '' ) );
        $api_key_secret      = sanitize_text_field( wp_unslash( $_POST['x_api_key_secret'] ?? '' ) );
        $access_token        = sanitize_text_field( wp_unslash( $_POST['x_access_token'] ?? '' ) );
        $access_token_secret = sanitize_text_field( wp_unslash( $_POST['x_access_token_secret'] ?? '' ) );

        if ( empty( $api_key ) || empty( $api_key_secret ) || empty( $access_token ) || empty( $access_token_secret ) ) {
            wp_die( esc_html__( 'All four credential fields are required.', 'social-sync' ) );
        }

        update_option( 'socialsync_x_api_key', $api_key );
        update_option( 'socialsync_x_api_key_secret', $api_key_secret );
        update_option( 'socialsync_x_access_token', $access_token );
        update_option( 'socialsync_x_access_token_secret', $access_token_secret );
        update_option( 'socialsync_x_connected', true );

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1' ) );
        exit;
    }

    /**
     * Disconnect X account.
     */
    public function handle_disconnect_x(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_x' );

        $provider = new SocialSync_X_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1' ) );
        exit;
    }

    /**
     * Initiate LinkedIn OAuth connection flow.
     */
    public function handle_connect_linkedin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_connect_linkedin', 'socialsync-connect-linkedin-nonce' );

        $client_id     = sanitize_text_field( wp_unslash( $_POST['linkedin_client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['linkedin_client_secret'] ?? '' ) );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_die( esc_html__( 'Client ID and Client Secret are required.', 'social-sync' ) );
        }

        update_option( 'socialsync_linkedin_client_id', $client_id );
        update_option( 'socialsync_linkedin_client_secret', $client_secret );

        $raw_state = wp_generate_password( 32, false );
        $state     = 'linkedin_' . $raw_state;
        set_transient( 'socialsync_linkedin_oauth_state', $state, 300 );

        $redirect_uri = admin_url( 'admin-post.php?action=socialsync_oauth_callback_linkedin' );

        $auth_url = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query( array(
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'w_member_social w_organization_social r_organization_social r_liteprofile r_emailaddress',
            'state'         => $state,
        ), '', '&', PHP_QUERY_RFC3986 );

        wp_redirect( $auth_url );
        exit;
    }

    /**
     * Disconnect LinkedIn account.
     */
    public function handle_disconnect_linkedin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_linkedin' );

        $provider = new SocialSync_LinkedIn_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1' ) );
        exit;
    }

    /**
     * Initiate Facebook OAuth connection flow.
     */
    public function handle_connect_facebook(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_connect_facebook', 'socialsync-connect-facebook-nonce' );

        $client_id     = sanitize_text_field( wp_unslash( $_POST['facebook_client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['facebook_client_secret'] ?? '' ) );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_die( esc_html__( 'Client ID and Client Secret are required.', 'social-sync' ) );
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

        wp_redirect( $auth_url );
        exit;
    }

    /**
     * Disconnect Facebook account.
     */
    public function handle_disconnect_facebook(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_facebook' );

        $provider = new SocialSync_Facebook_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1' ) );
        exit;
    }

    /**
     * Connect Bluesky account via session (app password).
     */
    public function handle_connect_bluesky(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_connect_bluesky', 'socialsync-connect-bluesky-nonce' );

        $identifier   = sanitize_text_field( wp_unslash( $_POST['bluesky_identifier'] ?? '' ) );
        $app_password = sanitize_text_field( wp_unslash( $_POST['bluesky_app_password'] ?? '' ) );

        if ( empty( $identifier ) || empty( $app_password ) ) {
            wp_die( esc_html__( 'Identifier and App Password are required.', 'social-sync' ) );
        }

        update_option( 'socialsync_bluesky_identifier', $identifier );
        update_option( 'socialsync_bluesky_app_password', $app_password );

        $provider = new SocialSync_Bluesky_Provider();
        if ( ! $provider->is_connected() ) {
            if ( ! $provider->refresh_token() ) {
                delete_option( 'socialsync_bluesky_identifier' );
                delete_option( 'socialsync_bluesky_app_password' );
                wp_die( esc_html__( 'Failed to authenticate with Bluesky. Check your identifier and App Password.', 'social-sync' ) );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1' ) );
        exit;
    }

    /**
     * Disconnect Bluesky account.
     */
    public function handle_disconnect_bluesky(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_disconnect_bluesky' );

        $provider = new SocialSync_Bluesky_Provider();
        $provider->disconnect();

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&disconnected=1' ) );
        exit;
    }

    /**
     * Save per-platform prefix text and hashtags.
     */
    public function handle_save_platform_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' );

        $platform   = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $allowed    = array( 'x', 'linkedin', 'facebook', 'bluesky' );
        if ( ! in_array( $platform, $allowed, true ) ) {
            wp_die( esc_html__( 'Invalid platform.', 'social-sync' ) );
        }
        $prefix     = sanitize_text_field( wp_unslash( $_POST['prefix_text'] ?? '' ) );
        $hashtags   = sanitize_text_field( wp_unslash( $_POST['hashtags'] ?? '' ) );
        $settings   = get_option( 'socialsync_settings', array() );
        $settings[ $platform . '_prefix_text' ] = $prefix;
        $settings[ $platform . '_hashtags' ]    = $hashtags;
        update_option( 'socialsync_settings', $settings );

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&updated=1' ) );
        exit;
    }

    /**
     * Handle selecting a Facebook Page for posting.
     */
    public function handle_select_facebook_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
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
                wp_die( esc_html__( 'Selected Facebook page not found. Please reconnect your Facebook account.', 'social-sync' ) );
            }
            update_option( 'socialsync_facebook_page_id', $page_id );
            update_option( 'socialsync_facebook_page_token', $page_token );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&page_selected=1' ) );
        exit;
    }

    /**
     * Handle selecting a LinkedIn Organization for posting.
     */
    public function handle_select_linkedin_org(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_select_linkedin_org', 'socialsync-select-linkedin-org-nonce' );

        $org_id   = sanitize_text_field( wp_unslash( $_POST['linkedin_org_id'] ?? '' ) );
        $org_name = sanitize_text_field( wp_unslash( $_POST['linkedin_org_name'] ?? '' ) );

        if ( empty( $org_id ) ) {
            delete_option( 'socialsync_linkedin_org_id' );
            delete_option( 'socialsync_linkedin_org_name' );
        } else {
            update_option( 'socialsync_linkedin_org_id', $org_id );
            update_option( 'socialsync_linkedin_org_name', $org_name );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&org_selected=1' ) );
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
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
        $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
        $error = sanitize_text_field( wp_unslash( $_GET['error'] ?? '' ) );

        if ( ! empty( $error ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_error=' . urlencode( $error ) ) );
            exit;
        }

        if ( empty( $code ) || empty( $state ) ) {
            wp_die( esc_html__( 'Invalid OAuth callback parameters.', 'social-sync' ) );
        }

        $expected_state = get_transient( 'socialsync_' . $platform . '_oauth_state' );
        if ( $expected_state !== $state ) {
            wp_die( esc_html__( 'Invalid OAuth state parameter.', 'social-sync' ) );
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
                wp_die( esc_html__( 'Unknown platform.', 'social-sync' ) );
        }

        update_option( 'socialsync_' . $platform . '_connected', true );
        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-settings&oauth_success=1' ) );
        exit;
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
            wp_die( esc_html( $response->get_error_message() ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            wp_die( esc_html__( 'Failed to obtain LinkedIn access token.', 'social-sync' ) );
        }

        update_option( 'socialsync_linkedin_token', array(
            'access_token'  => sanitize_text_field( $body['access_token'] ),
            'refresh_token' => isset( $body['refresh_token'] ) ? sanitize_text_field( $body['refresh_token'] ) : '',
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
            wp_die( esc_html__( 'Failed to obtain Facebook access token.', 'social-sync' ) );
        }

        update_option( 'socialsync_facebook_token', array(
            'access_token' => sanitize_text_field( $body['access_token'] ),
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_clear_log' );

        delete_option( 'socialsync_logs' );

        if ( class_exists( 'SocialSync_Scheduled_Post' ) ) {
            SocialSync_Scheduled_Post::clear_all();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=social-sync-log&log_cleared=1' ) );
        exit;
    }

    /**
     * Save log retention settings.
     */
    public function handle_save_log_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
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
        $table = $wpdb->prefix . 'socialsync_scheduled_posts';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE status IN ('published', 'failed', 'cancelled') AND scheduled_date < %s",
                gmdate( 'Y-m-d H:i:s', $cutoff )
            )
        );
    }

    /**
     * Enqueue admin scripts and styles for SocialSync settings page.
     *
     * @param string $hook Current admin page hook.
     * @return void Loads admin assets with proper nonce verification.
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Check if current admin page is SocialSync settings, scheduled posts, or post editor
        if ( strpos($hook, 'social-sync') === false && ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        // Enqueue admin CSS and JS with nonce verification for security
        wp_enqueue_style('socialsync-admin', plugin_dir_url( __FILE__ ) . 'css/socialsync.css', array(), $this->version );

        // Use wp_enqueue_script to load admin JavaScript with nonce verification
        wp_enqueue_script('socialsync-admin-script', plugin_dir_url( __FILE__ ) . 'js/socialsync.js', array('jquery'), $this->version, true);

        wp_localize_script('socialsync-admin-script', 'SocialSyncAdmin', array(
            'confirm_delete' => __( 'Delete this scheduled post?', 'social-sync' ),
            'confirm_cancel' => __( 'Cancel this scheduled post?', 'social-sync' ),
        ));
    }

    /**
     * Handle saving a new or edited scheduled post.
     */
    public function handle_save_scheduled_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }
        check_admin_referer( 'socialsync_save_scheduled_post', 'socialsync-scheduled-post-nonce' );

        $title          = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $content        = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $platforms      = isset( $_POST['platforms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['platforms'] ) ) : array();
        $scheduled_date = sanitize_text_field( wp_unslash( $_POST['scheduled_date'] ?? '' ) );
        $edit_id        = isset( $_POST['edit_id'] ) ? intval( $_POST['edit_id'] ) : 0;
        $post_now       = isset( $_POST['post_now'] );

        if ( empty( $content ) ) {
            wp_die( esc_html__( 'Content is required.', 'social-sync' ) );
        }
        if ( empty( $platforms ) ) {
            wp_die( esc_html__( 'Select at least one platform.', 'social-sync' ) );
        }

        if ( $post_now ) {
            $scheduled_date = current_time( 'mysql' );
        } elseif ( empty( $scheduled_date ) ) {
            wp_die( esc_html__( 'Scheduled date is required.', 'social-sync' ) );
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
            $settings       = get_option( 'socialsync_settings', array() );
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
                        $platform_errors[ $platform_slug ] = __( 'Unknown platform', 'social-sync' );
                        continue 2;
                }

                $post_content = $content;

                $prefix_key = $platform_slug . '_prefix_text';
                if ( ! empty( $settings[ $prefix_key ] ) ) {
                    $post_content = $settings[ $prefix_key ] . ' ' . $post_content;
                }
                $hashtags_key = $platform_slug . '_hashtags';
                if ( ! empty( $settings[ $hashtags_key ] ) ) {
                    $post_content .= "\n\n" . $settings[ $hashtags_key ];
                }

                $result = $provider->publish( $post_content );

                if ( is_wp_error( $result ) ) {
                    $platform_errors[ $platform_slug ] = $result->get_error_message();
                } elseif ( isset( $result['success'] ) && $result['success'] ) {
                    $platform_ok[] = $platform_slug;
                } else {
                    $msg = isset( $result['message'] ) ? $result['message'] : __( 'Unknown error', 'social-sync' );
                    $platform_errors[ $platform_slug ] = $msg;
                }
            }

            $all_success = empty( $platform_errors );
            SocialSync_Scheduled_Post::update( $insert_id, array(
                'status'         => $all_success ? 'published' : 'failed',
                'error_message'  => $all_success ? '' : wp_json_encode( $platform_errors ),
            ) );

            $redirect_url = admin_url( 'admin.php?page=social-sync-scheduled' );
            $query_args   = array();
            foreach ( $platform_ok as $slug ) {
                $query_args[ 'success_' . $slug ] = 1;
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'socialsync_delete_scheduled_post_' . $id );
        if ( empty( $id ) ) {
            wp_die( esc_html__( 'Invalid post ID.', 'social-sync' ) );
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'social-sync' ) );
        }

        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        check_admin_referer( 'socialsync_cancel_scheduled_post_' . $id );
        if ( empty( $id ) ) {
            wp_die( esc_html__( 'Invalid post ID.', 'social-sync' ) );
        }

        SocialSync_Scheduled_Post::update( $id, array( 'status' => 'cancelled' ) );

        $redirect = isset( $_GET['_redirect'] ) && 'log' === sanitize_text_field( wp_unslash( $_GET['_redirect'] ) )
            ? admin_url( 'admin.php?page=social-sync-log&cancelled=1' )
            : admin_url( 'admin.php?page=social-sync-scheduled&cancelled=1' );
        wp_safe_redirect( $redirect );
        exit;
    }
}
