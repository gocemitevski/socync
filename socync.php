<?php
/**
 * Plugin Name: Socync - Automatic social media posting and scheduling
 * Description: Automatically publish your WordPress posts to X (Twitter), LinkedIn, Facebook and Bluesky when you publish content on your site.
 * Version: 0.7.5
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author: Goce Mitevski
 * Author URI: https://www.gocemitevski.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: socync
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOCYNC_VERSION' ) ) {
    define( 'SOCYNC_VERSION', '0.7.5' );
}
if ( ! defined( 'SOCYNC_PLUGIN_DIR' ) ) {
    define( 'SOCYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
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
require_once dirname( __FILE__ ) . '/includes/class-dev-logger.php';
require_once dirname( __FILE__ ) . '/admin/class-admin.php';

// Load provider classes
require_once dirname( __FILE__ ) . '/providers/class-x-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-linkedin-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-facebook-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-bluesky-provider.php';

const SOCYNC_ENC_PREFIX = 'SSENC:';

/**
 * Get the encryption key for credential storage.
 *
 * @return string
 */
function socync_encryption_key(): string {
    $raw = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
    return hash( 'sha256', $raw, true );
}

/**
 * Encrypt a value for secure credential storage.
 *
 * @param  string $value Plaintext value.
 * @return string Encrypted value (marker + base64-encoded IV + ciphertext), or original value on failure.
 */
function socync_encrypt( string $value ): string {
    if ( '' === $value ) {
        return $value;
    }

    $key       = socync_encryption_key();
    $method    = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length( $method );
    $iv        = openssl_random_pseudo_bytes( $iv_length );

    $encrypted = openssl_encrypt( $value, $method, $key, 0, $iv );

    if ( false === $encrypted ) {
        return $value;
    }

    return SOCYNC_ENC_PREFIX . base64_encode( $iv . $encrypted );
}

/**
 * Decrypt a value retrieved from credential storage.
 *
 * @param  string $value Encrypted value (marker + base64-encoded IV + ciphertext).
 * @return string Decrypted plaintext, or original value if not encrypted.
 */
function socync_decrypt( string $value ): string {
    if ( '' === $value ) {
        return $value;
    }

    if ( 0 !== strpos( $value, SOCYNC_ENC_PREFIX ) ) {
        return $value;
    }

    $raw     = substr( $value, strlen( SOCYNC_ENC_PREFIX ) );
    $decoded = base64_decode( $raw, true );

    if ( false === $decoded ) {
        return $value;
    }

    $key       = socync_encryption_key();
    $method    = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length( $method );

    if ( strlen( $decoded ) < $iv_length ) {
        return $value;
    }

    $iv         = substr( $decoded, 0, $iv_length );
    $ciphertext = substr( $decoded, $iv_length );

    $decrypted = openssl_decrypt( $ciphertext, $method, $key, 0, $iv );

    if ( false === $decrypted ) {
        return $value;
    }

    return $decrypted;
}

/**
 * List of sensitive option names to encrypt at rest.
 *
 * @return string[]
 */
function socync_encrypted_options(): array {
    return array(
        'socync_x_api_key_secret',
        'socync_x_access_token',
        'socync_x_access_token_secret',
        'socync_x_client_secret',
        'socync_x_token',
        'socync_linkedin_client_secret',
        'socync_facebook_app_secret',
        'socync_bluesky_app_password',
        'socync_bluesky_refresh_jwt',
        'socync_facebook_page_token',
        'socync_linkedin_token',
        'socync_facebook_token',
        'socync_bluesky_token',
    );
}

/**
 * Encrypt a credential option value before writing to the database.
 *
 * Handles both string values and arrays (encrypts sensitive fields within arrays).
 *
 * @param  mixed $value The value to encrypt.
 * @return mixed The encrypted value.
 */
function socync_encrypt_option_value( $value ) {
    if ( is_array( $value ) ) {
        $sensitive_keys = array( 'access_token', 'refresh_token' );
        foreach ( $value as $k => $v ) {
            if ( in_array( $k, $sensitive_keys, true ) && is_string( $v ) && '' !== $v ) {
                $value[ $k ] = socync_encrypt( $v );
            }
        }
        return $value;
    }
    if ( is_string( $value ) && '' !== $value ) {
        return socync_encrypt( $value );
    }
    return $value;
}

/**
 * Decrypt a credential option value after reading from the database.
 *
 * Handles both string values and arrays (decrypts sensitive fields within arrays).
 *
 * @param  mixed $value The value to decrypt.
 * @return mixed The decrypted value.
 */
function socync_decrypt_option_value( $value ) {
    if ( is_array( $value ) ) {
        $sensitive_keys = array( 'access_token', 'refresh_token' );
        foreach ( $value as $k => $v ) {
            if ( in_array( $k, $sensitive_keys, true ) && is_string( $v ) && '' !== $v ) {
                $decrypted = socync_decrypt( $v );
                if ( $decrypted !== $v ) {
                    $value[ $k ] = $decrypted;
                }
            }
        }
        return $value;
    }
    if ( is_string( $value ) && '' !== $value ) {
        $decrypted = socync_decrypt( $value );
        if ( $decrypted !== $value ) {
            return $decrypted;
        }
    }
    return $value;
}

/**
 * Register encryption/decryption filters for sensitive credential options.
 */
function socync_register_encryption_filters(): void {
    $options = socync_encrypted_options();
    foreach ( $options as $option ) {
        add_filter( 'option_' . $option, 'socync_decrypt_option_value', 5 );
        add_filter( 'pre_update_option_' . $option, 'socync_encrypt_option_value', 5 );
    }
}
add_action( 'init', 'socync_register_encryption_filters', 0 );

/**
 * Migrate existing plaintext credentials to encrypted storage.
 *
 * Runs once after first load with encryption enabled. Reads each credential
 * (which returns plaintext via the decrypt filter — no-op for unencrypted data)
 * and re-saves it (which encrypts it via the pre_update filter).
 */
function socync_migrate_plaintext_credentials(): void {
    if ( get_option( 'socync_credentials_encrypted', false ) ) {
        return;
    }

    $options = socync_encrypted_options();
    foreach ( $options as $option ) {
        $value = get_option( $option, null );
        if ( null !== $value ) {
            update_option( $option, $value );
        }
    }

    update_option( 'socync_credentials_encrypted', true );
}
add_action( 'init', 'socync_migrate_plaintext_credentials', 20 );

/**
 * Validate that a URL points to a safe external host (not private/reserved IP range).
 *
 * Resolves the hostname to an IP and rejects RFC 1918 private ranges,
 * loopback, link-local, and unresolvable hostnames.
 *
 * @param  string $url URL to validate.
 * @return bool True if the URL is safe to fetch.
 */
function socync_is_safe_url( string $url ): bool {
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( ! $host ) {
        return false;
    }

    // gethostbyname returns the hostname unchanged for literal IPs and
    // unresolvable names — both are rejected (literal IPs are never
    // legitimate link targets in social posts and skipping DNS is a
    // deliberate simplification that also rejects IPv6-only hosts).
    $ip = gethostbyname( $host );
    if ( $ip === $host ) {
        return false;
    }

    return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Socync Plugin Class.
 *
 * @since 1.0.0
 */
class Socync_Plugin {

    /** @var Socync_Plugin Single instance of the plugin. */
    private static $instance = null;

    /** @var Socync_Admin Admin handler instance. */
    public $admin = null;

    /** @var string Current plugin version. */
    public $version = SOCYNC_VERSION;

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
        // Register activation hook
        register_activation_hook( __FILE__, array( 'Socync_Activator', 'activate' ) );

        // Register deactivation hook
        register_deactivation_hook( __FILE__, array( 'Socync_Deactivator', 'deactivate' ) );

        // Initialize the admin interface and scheduler
        $this->admin = new Socync_Admin();
        Socync_Scheduler::get_instance();
    }
}

/**
 * Load plugin text domain.
 */
function socync_load_textdomain(): void {
    load_plugin_textdomain( 'socync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'socync_load_textdomain' );

// Load plugin instance
function socync_plugin_instance(): Socync_Plugin {
    return Socync_Plugin::instance();
}

// Instantiate the plugin on activation
add_action( 'plugins_loaded', 'socync_plugin_instance' );
