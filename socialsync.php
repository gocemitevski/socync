<?php
/**
 * Plugin Name: SocialSync - Automatic X (Twitter), LinkedIn, Facebook & Bluesky Posting for WordPress
 * Description: Automatically publish your WordPress posts to X (Twitter), LinkedIn, Facebook and Bluesky when you publish content on your site.
 * Version: 1.0.0
 * Author: SocialSync Contributors
 * License: GPL v2 or later
 * Text Domain: social-sync
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOCIALSYNC_VERSION' ) ) {
    define( 'SOCIALSYNC_VERSION', '1.0.0' );
}
if ( ! defined( 'SOCIALSYNC_PLUGIN_DIR' ) ) {
    define( 'SOCIALSYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Load plugin files.
 *
 * @since 1.0.0
 */
require_once dirname( __FILE__ ) . '/includes/class-activator.php';
require_once dirname( __FILE__ ) . '/includes/class-deactivator.php';
require_once dirname( __FILE__ ) . '/includes/class-api-handler.php';
require_once dirname( __FILE__ ) . '/includes/class-scheduled-post.php';
require_once dirname( __FILE__ ) . '/includes/class-scheduler.php';
require_once dirname( __FILE__ ) . '/admin/class-admin.php';

// Load provider classes
require_once dirname( __FILE__ ) . '/providers/class-x-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-linkedin-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-facebook-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-bluesky-provider.php';

/**
 * SocialSync Plugin Class.
 *
 * @since 1.0.0
 */
class SocialSync_Plugin {

    /** @var SocialSync_Plugin Single instance of the plugin. */
    private static $instance = null;

    /** @var SocialSync_Admin Admin handler instance. */
    public $admin = null;

    /** @var string Current plugin version. */
    public $version = SOCIALSYNC_VERSION;

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
     */
    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Load plugin text domain for translations
        load_plugin_textdomain( 'social-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Register activation hook
        register_activation_hook( __FILE__, array( 'SocialSync_Activator', 'activate' ) );

        // Register deactivation hook
        register_deactivation_hook( __FILE__, array( 'SocialSync_Deactivator', 'deactivate' ) );

        // Initialize the admin interface and scheduler
        $this->admin = new SocialSync_Admin();
        SocialSync_Scheduler::get_instance();
    }
}

// Load plugin instance
function socialsync_plugin_instance(): SocialSync_Plugin {
    return SocialSync_Plugin::instance();
}

// Instantiate the plugin on activation
add_action( 'plugins_loaded', 'socialsync_plugin_instance' );
