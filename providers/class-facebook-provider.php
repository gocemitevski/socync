<?php
/**
 * SocialSync Facebook Provider Class
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once dirname(dirname(__FILE__)) . '/includes/class-api-handler.php';

/**
 * Facebook Graph API Provider for SocialSync plugin.
 *
 * Implements OAuth 2.0 authentication and post publishing using Facebook Graph API v18.0.
 */
class SocialSync_Facebook_Provider extends SocialSync_API_Handler {

    /**
     * Facebook Graph API base URL endpoint.
     *
     * @var string Facebook Graph API endpoints.
     */
    const BASE_URL = 'https://graph.facebook.com/v18.0';

    /**
     * Constructor for Facebook provider class.
     *
     * @param array $settings Connection settings from wp_options table.
     * @return void Loads OAuth 2.0 credentials and validates connection.
     */
    public function __construct() {
        parent::__construct('facebook');
    }

    /**
     * Fetch Facebook Pages the user administers.
     *
     * @return array|WP_Error List of pages or error.
     */
    public function get_pages() {
        if ( ! $this->is_connected() ) {
            return new WP_Error('not_connected', __( 'Facebook not connected.', 'socialsync' ));
        }

        $response = $this->get_api( self::BASE_URL . '/me/accounts?fields=id,name,access_token,picture' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return array();
        }

        return $response['data'];
    }

    /**
     * Refresh the Facebook OAuth 2.0 Page Access Token.
     *
     * @return bool True if refresh successful, false on failure.
     */
    protected function refresh_token(): bool {
        // Check if current token is expired or invalid before refreshing
        if ( $this->is_token_valid() ) {
            return true; // Token still valid, no refresh needed
        }

        // Extend the existing user/page access token via fb_exchange_token
        $current_token = get_option( 'socialsync_facebook_token', '' );
        if ( empty( $current_token ) ) {
            return false;
        }

        $refresh_endpoint = self::BASE_URL . '/oauth/access_token';

        $post_data = array(
            'client_id'        => get_option('socialsync_facebook_app_id'),
            'client_secret'    => get_option('socialsync_facebook_app_secret'),
            'grant_type'       => 'fb_exchange_token',
            'fb_exchange_token'=> $current_token,
        );

        // Send token refresh request to Facebook Graph API OAuth endpoint
        $response = wp_remote_post(
            $refresh_endpoint,
            array(
                'headers'   => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'      => http_build_query($post_data),
                'timeout'   => self::DEFAULT_TIMEOUT,
                'sslverify' => true,
            )
        );

        // Check for HTTP errors in response headers or body
        if ( is_wp_error($response) ) {
            $this->log_error('Facebook token refresh failed with wp error', $response);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        // Handle non-2xx responses from Facebook Graph API
        if ( ! is_numeric($status_code) || intval($status_code) < 200 || intval($status_code) >= 300 ) {
            $body = wp_remote_retrieve_body($response);

            // Log error message from Facebook Graph API response
            $message = $body;
            $this->log_error('Facebook token refresh failed: ' . $message, array('status' => intval($status_code)));

            return false;
        }

        // Decode and store new Page Access Token from Facebook Graph API response
        $token_response = json_decode(wp_remote_retrieve_body($response), true);

        if ( ! isset($token_response['access_token']) ) {
            $this->log_error('Facebook API returned no access token in refresh response', array());
            return false;
        }

        // Extract and store new Facebook Page Access Token with expiry information
        $new_access_token = $token_response['access_token'];
        
        if ( isset($token_response['expires_in']) ) {
            $expiry_seconds = intval($token_response['expires_in']);
            $this->token_expiry = time() + $expiry_seconds;
        } else {
            // Default expiry of 2592000 seconds (30 days) if not provided
            $this->token_expiry = time() + 2592000;
        }

        // Store updated Facebook Page Access Token in wp_options table with expiry timestamp
        update_option('socialsync_facebook_token', array(
            'access_token' => $new_access_token,
            'expires_in'   => isset($token_response['expires_in']) ? intval($token_response['expires_in']) : 0,
            'created_at'   => time(),
        ));

        // Return success
        return true;
    }

    /**
     * Publish a post to Facebook Page using the Graph API.
     *
     * @param string $content Post content (title + excerpt or custom text) for Facebook.
     * @return array|WP_Error Response from Facebook Graph API or error object.
     */
    public function publish( string $content, string $url = '' ) {
        if ( ! $this->refresh_token() ) {
            return new WP_Error( 'token_expired', __( 'Facebook access token expired and could not be refreshed. Please reconnect.', 'socialsync' ) );
        }
        if ( ! $this->is_connected() ) {
            return new WP_Error('not_connected', __( 'Facebook Page not connected or access token has expired.', 'socialsync' ));
        }

        $page_id    = get_option( 'socialsync_facebook_page_id', '' );
        $page_token = get_option( 'socialsync_facebook_page_token', '' );

        if ( ! empty( $page_id ) && ! empty( $page_token ) ) {
            $endpoint  = self::BASE_URL . '/' . $page_id . '/feed';
            $post_data = array(
                'message'      => is_string($content) ? $content : '',
                'access_token' => $page_token,
            );
        } else {
            $endpoint  = self::BASE_URL . '/me/feed';
            $post_data = array(
                'message' => is_string($content) ? $content : '',
            );
        }

        if ( ! empty( $url ) ) {
            $post_data['link'] = $url;
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'body'      => $post_data,
                'headers'   => empty( $page_id ) ? array(
                    'Authorization' => 'Bearer ' . $this->access_token,
                ) : array(),
                'timeout'   => self::DEFAULT_TIMEOUT,
                'sslverify'  => true,
            )
        );

        if ( is_wp_error($response) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ( ! is_numeric($status_code) || intval($status_code) < 200 || intval($status_code) >= 300 ) {
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode( $body, true );
            $message = is_array( $decoded ) && isset( $decoded['error']['message'] )
                ? sanitize_text_field( $decoded['error']['message'] )
                : sanitize_text_field( $body );

            return new WP_Error(
                'facebook_post_error',
                $message,
                array('status' => intval($status_code))
            );
        }

        // Extract post ID from Facebook Graph API response
        $response_body = wp_remote_retrieve_body($response);

        // Decode JSON response to extract post_id
        $decoded_response = json_decode($response_body, true);
        $post_id = isset($decoded_response['id']) ? intval($decoded_response['id']) : 0;

        if ( $post_id === 0 ) {
            // Fallback to content preview if no post ID found
            $preview = mb_substr($response_body, 0, 80) . '...';
        } else {
            $preview = sprintf(
                /* translators: %d: Facebook post ID */
                __('Facebook Post #%d created successfully.', 'socialsync'),
                $post_id
            );
        }

		return array(
			'success' => true,
			'message' => $preview,
		);
	}

    /**
     * Disconnect the Facebook Page by clearing stored tokens from wp_options.
     *
     * @return bool True if disconnect was successful, false on failure.
     */
    public function disconnect(): bool {
        delete_option('socialsync_facebook_token');
        delete_option('socialsync_facebook_page_id');
        delete_option('socialsync_facebook_page_token');
        delete_option('socialsync_facebook_pages_cache');
        delete_option('socialsync_facebook_connected');
        delete_option('socialsync_facebook_app_id');
        delete_option('socialsync_facebook_app_secret');
        delete_option('socialsync_facebook_logs');
        delete_transient('socialsync_facebook_oauth_state');

        $this->log_success(
            'Facebook Page disconnected',
            'Facebook',
            'success'
        );

        return true;
    }

    /**
     * Log API errors for Facebook provider to unified log.
     *
     * @param string $error_message Error message description.
     * @param array  $context       Contextual data for error logging.
     * @return void Writes error entry to socialsync_logs.
     */
    protected function log_error( string $error_message, array $context = array() ): void {
        $logs = get_option( 'socialsync_logs', array() );

        $full_message = $error_message;
        if ( ! empty( $context ) ) {
            $full_message .= ' | ' . wp_json_encode( $context );
        }

        $logs[] = array(
            'id'       => uniqid(),
            'post_id'  => 0,
            'platform' => 'facebook',
            'status'   => 'failed',
            'message'  => $full_message,
            'date'     => current_time( 'mysql' ),
            'type'     => 'facebook',
        );

        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }

        update_option( 'socialsync_logs', $logs, false );
    }

    /**
     * Log successful API operations for Facebook provider to unified log.
     *
     * @param string $message  Success message to log.
     * @param string $platform Platform name for log categorization.
     * @param string $status   Operation status (e.g., 'success').
     * @param array  $context  Additional context data.
     * @return void Writes success entry to socialsync_logs.
     */
    protected function log_success( string $message, string $platform, string $status, array $context = array() ): void {
        $logs = get_option( 'socialsync_logs', array() );

        $full_message = $message;
        if ( ! empty( $context ) ) {
            $full_message .= ' | ' . wp_json_encode( $context );
        }

        $logs[] = array(
            'id'       => uniqid(),
            'post_id'  => 0,
            'platform' => $platform,
            'status'   => $status,
            'message'  => $full_message,
            'date'     => current_time( 'mysql' ),
            'type'     => 'facebook',
        );

        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }

        update_option( 'socialsync_logs', $logs, false );
    }
}
