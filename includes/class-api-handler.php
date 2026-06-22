<?php
/**
 * SocialSync Base API Handler Class
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract base class for all social media provider API implementations.
 *
 * Provides common functionality like token management, request handling,
 * and response parsing that can be extended by X, LinkedIn, Facebook providers.
 */
abstract class SocialSync_API_Handler {

    /**
     * Platform identifier slug (e.g., 'x', 'linkedin', 'facebook').
     *
     * @var string|null Platform slug.
     */
    protected $platform = null;

    /**
     * Access token for API authentication.
     *
     * @var string|null Stored in wp_options.
     */
    protected $access_token = '';

    /**
     * Token expiration timestamp (Unix time).
     *
     * @var int|null When the access token expires.
     */
    protected $token_expiry = null;

    /**
     * Default request timeout in seconds.
     *
     * @var int Timeout for wp_remote_request().
     */
    const DEFAULT_TIMEOUT = 10; // 10 seconds per security best practices

    /**
     * Constructor for API handler.
     *
     * @param string $platform Platform slug (x, linkedin, facebook).
     */
    public function __construct( string $platform ) {
        $this->platform = sanitize_text_field( $platform );

        // Load token from wp_options table
        $this->load_token();
    }

    /**
     * Check if the API connection is valid and authenticated.
     *
     * @return bool True if connected, false otherwise.
     */
    public function is_connected(): bool {
        return ! empty( $this->access_token ) && $this->is_token_valid();
    }

    /**
     * Load access token from wp_options table.
     *
     * @return void Retrieves and sets the stored token.
     */
    protected function load_token(): void {
        if ( empty( $this->platform ) ) {
            return;
        }

        // Get stored token from wp_options table
        $token_data = get_option( 'socialsync_' . $this->platform . '_token', array() );

        if ( ! is_array( $token_data ) || empty( $token_data['access_token'] ) ) {
            return;
        }

        // Extract and validate token data from wp_options
        $this->access_token = sanitize_text_field( wp_unslash( $token_data['access_token'] ) );

        if ( ! empty( $token_data['expires_in'] ) && isset( $token_data['created_at'] ) ) {
            $expiry_time = $token_data['expires_in'];

            if ( preg_match( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $expiry_time ) ) {
                // ISO 8601 format - convert to Unix timestamp
                $this->token_expiry = strtotime( $expiry_time );
            } elseif ( is_numeric( $expiry_time ) && $expiry_time <= YEAR_IN_SECONDS ) {
                // Duration in seconds — compute absolute expiry from created_at
                $this->token_expiry = (int) $token_data['created_at'] + (int) $expiry_time;
            } else {
                // Absolute Unix timestamp
                $this->token_expiry = (int) $expiry_time;
            }
        }

        // Set default expiry if token has no expiry set
        if ( empty( $this->token_expiry ) ) {
            // Default to 3600 seconds (1 hour) for all providers
            $this->token_expiry = time() + 3600;
        }
    }

    /**
     * Check if the current access token is still valid.
     *
     * @return bool True if the token hasn't expired, false otherwise.
     */
    protected function is_token_valid(): bool {
        // If we don't have an expiry time, assume it's still valid
        if ( empty( $this->token_expiry ) ) {
            return true;
        }

        // Token has expired
        return $this->token_expiry > time();
    }

    /**
     * Refresh the access token from the social platform API.
     *
     * @return bool True if refresh was successful, false on failure.
     */
    abstract protected function refresh_token();

    /**
     * Send a POST request to the social media API.
     *
     * @param string $endpoint API endpoint URL (e.g., '/2/tweets' for X)
     * @param array  $data Associative array of data to send in POST body
     * @return array|WP_Error Response from API or error object
     */
    protected function post_api( string $endpoint, array $data ): array|WP_Error {
        // Set default timeout and prepare request args per security best practices
        $timeout = self::DEFAULT_TIMEOUT;

        // Build authentication header with Bearer token
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type'  => 'application/json',
        );

        // Prepare request arguments for wp_remote_request
        $args = array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => json_encode( $data ),
            'timeout'     => $timeout,
            'sslverify'   => true,
        );

        // Send the request and handle response
        $response = wp_remote_post(
            $endpoint,
            $args
        );

        // Check for HTTP errors in response headers or body
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Handle non-2xx responses from the API
        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            $body = wp_remote_retrieve_body( $response );
            $error_data = json_decode( $body, true );

            // Return structured error with message if available
            return new WP_Error(
                'api_error',
                isset( $error_data['message'] ) ? sanitize_text_field( $error_data['message'] ) : sanitize_text_field( $body ),
                array( 'status' => intval( $status_code ) )
            );
        }

        // Return successful response body as decoded JSON
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Send a GET request to the social media API.
     *
     * @param string $endpoint API endpoint URL
     * @return array|WP_Error Response from API or error object.
     */
    protected function get_api( string $endpoint ): array|WP_Error {
        // Set default timeout per security best practices
        $timeout = self::DEFAULT_TIMEOUT;

        // Build authentication header with Bearer token
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
        );

        // Prepare request arguments for wp_remote_request
        $args = array(
            'method'      => 'GET',
            'headers'     => $headers,
            'timeout'     => $timeout,
            'sslverify'   => true,
        );

        // Send the request and handle response
        $response = wp_remote_get(
            $endpoint,
            $args
        );

        // Check for HTTP errors in response headers or body
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Handle non-2xx responses from the API
        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            $body = wp_remote_retrieve_body( $response );
            $decoded = json_decode( $body, true );
            $message = is_array( $decoded ) && isset( $decoded['error']['message'] )
                ? sanitize_text_field( $decoded['error']['message'] )
                : sanitize_text_field( $body );
            return new WP_Error(
                'api_error',
                $message,
                array( 'status' => intval( $status_code ) )
            );
        }

        // Return successful response body as decoded JSON
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
