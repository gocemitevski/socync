<?php
/**
 * Plugin Name: SocialSync - Automatic X (Twitter), LinkedIn, Facebook & Bluesky Posting for WordPress
 * Description: Automatically publish your WordPress posts to X (Twitter), LinkedIn, Facebook and Bluesky when you publish content on your site.
 * Version: 0.4.0
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
    define( 'SOCIALSYNC_VERSION', '0.4.0' );
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
require_once dirname( __FILE__ ) . '/includes/class-dev-logger.php';
require_once dirname( __FILE__ ) . '/admin/class-admin.php';

// Load provider classes
require_once dirname( __FILE__ ) . '/providers/class-x-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-linkedin-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-facebook-provider.php';
require_once dirname( __FILE__ ) . '/providers/class-bluesky-provider.php';

const SOCIALSYNC_ENC_PREFIX = 'SSENC:';

/**
 * Get the encryption key for credential storage.
 *
 * @return string
 */
function socialsync_encryption_key(): string {
    $raw = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
    return hash( 'sha256', $raw, true );
}

/**
 * Encrypt a value for secure credential storage.
 *
 * @param  string $value Plaintext value.
 * @return string Encrypted value (marker + base64-encoded IV + ciphertext), or original value on failure.
 */
function socialsync_encrypt( string $value ): string {
    if ( '' === $value ) {
        return $value;
    }

    $key       = socialsync_encryption_key();
    $method    = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length( $method );
    $iv        = openssl_random_pseudo_bytes( $iv_length );

    $encrypted = openssl_encrypt( $value, $method, $key, 0, $iv );

    if ( false === $encrypted ) {
        return $value;
    }

    return SOCIALSYNC_ENC_PREFIX . base64_encode( $iv . $encrypted );
}

/**
 * Decrypt a value retrieved from credential storage.
 *
 * @param  string $value Encrypted value (marker + base64-encoded IV + ciphertext).
 * @return string Decrypted plaintext, or original value if not encrypted.
 */
function socialsync_decrypt( string $value ): string {
    if ( '' === $value ) {
        return $value;
    }

    if ( 0 !== strpos( $value, SOCIALSYNC_ENC_PREFIX ) ) {
        return $value;
    }

    $raw     = substr( $value, strlen( SOCIALSYNC_ENC_PREFIX ) );
    $decoded = base64_decode( $raw, true );

    if ( false === $decoded ) {
        return $value;
    }

    $key       = socialsync_encryption_key();
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
function socialsync_encrypted_options(): array {
    return array(
        'socialsync_x_api_key_secret',
        'socialsync_x_access_token',
        'socialsync_x_access_token_secret',
        'socialsync_linkedin_client_secret',
        'socialsync_facebook_app_secret',
        'socialsync_bluesky_app_password',
        'socialsync_bluesky_refresh_jwt',
        'socialsync_facebook_page_token',
        'socialsync_linkedin_token',
        'socialsync_facebook_token',
        'socialsync_bluesky_token',
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
function socialsync_encrypt_option_value( $value ) {
    if ( is_array( $value ) ) {
        $sensitive_keys = array( 'access_token', 'refresh_token' );
        foreach ( $value as $k => $v ) {
            if ( in_array( $k, $sensitive_keys, true ) && is_string( $v ) && '' !== $v ) {
                $value[ $k ] = socialsync_encrypt( $v );
            }
        }
        return $value;
    }
    if ( is_string( $value ) && '' !== $value ) {
        return socialsync_encrypt( $value );
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
function socialsync_decrypt_option_value( $value ) {
    if ( is_array( $value ) ) {
        $sensitive_keys = array( 'access_token', 'refresh_token' );
        foreach ( $value as $k => $v ) {
            if ( in_array( $k, $sensitive_keys, true ) && is_string( $v ) && '' !== $v ) {
                $decrypted = socialsync_decrypt( $v );
                if ( $decrypted !== $v ) {
                    $value[ $k ] = $decrypted;
                }
            }
        }
        return $value;
    }
    if ( is_string( $value ) && '' !== $value ) {
        $decrypted = socialsync_decrypt( $value );
        if ( $decrypted !== $value ) {
            return $decrypted;
        }
    }
    return $value;
}

/**
 * Register encryption/decryption filters for sensitive credential options.
 */
function socialsync_register_encryption_filters(): void {
    $options = socialsync_encrypted_options();
    foreach ( $options as $option ) {
        add_filter( 'option_' . $option, 'socialsync_decrypt_option_value', 5 );
        add_filter( 'pre_update_option_' . $option, 'socialsync_encrypt_option_value', 5 );
    }
}
add_action( 'init', 'socialsync_register_encryption_filters', 0 );

/**
 * Migrate existing plaintext credentials to encrypted storage.
 *
 * Runs once after first load with encryption enabled. Reads each credential
 * (which returns plaintext via the decrypt filter — no-op for unencrypted data)
 * and re-saves it (which encrypts it via the pre_update filter).
 */
function socialsync_migrate_plaintext_credentials(): void {
    if ( get_option( 'socialsync_credentials_encrypted', false ) ) {
        return;
    }

    $options = socialsync_encrypted_options();
    foreach ( $options as $option ) {
        $value = get_option( $option, null );
        if ( null !== $value ) {
            update_option( $option, $value );
        }
    }

    update_option( 'socialsync_credentials_encrypted', true );
}
add_action( 'init', 'socialsync_migrate_plaintext_credentials', 20 );

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
