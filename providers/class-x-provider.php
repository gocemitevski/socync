<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/includes/class-api-handler.php';

class SocialSync_X_Provider extends SocialSync_API_Handler {

    const TWEETS_ENDPOINT = 'https://api.x.com/2/tweets';

    public function __construct() {
        parent::__construct( 'x' );
    }

    private function get_api_key(): string {
        return get_option( 'socialsync_x_api_key', '' );
    }

    private function get_api_key_secret(): string {
        return get_option( 'socialsync_x_api_key_secret', '' );
    }

    private function get_access_token(): string {
        return get_option( 'socialsync_x_access_token', '' );
    }

    private function get_access_token_secret(): string {
        return get_option( 'socialsync_x_access_token_secret', '' );
    }

    public function is_connected(): bool {
        return ! empty( $this->get_api_key() )
            && ! empty( $this->get_api_key_secret() )
            && ! empty( $this->get_access_token() )
            && ! empty( $this->get_access_token_secret() );
    }

    protected function refresh_token(): bool {
        return $this->is_connected();
    }

    public function publish( string $content, string $url = '' ): array|WP_Error {
        if ( ! $this->is_connected() ) {
            return new WP_Error( 'not_connected', 'X account not connected. Please add your API credentials.' );
        }

        $api_key    = $this->get_api_key();
        $api_secret = $this->get_api_key_secret();
        $token      = $this->get_access_token();
        $tok_secret = $this->get_access_token_secret();

        $timestamp = time();
        $nonce     = wp_generate_password( 32, false );

        $oauth_params = array(
            'oauth_consumer_key'     => $api_key,
            'oauth_nonce'            => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => $timestamp,
            'oauth_token'            => $token,
            'oauth_version'          => '1.0',
        );
        ksort( $oauth_params );

        $param_parts = array();
        foreach ( $oauth_params as $k => $v ) {
            $param_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
        }
        $param_string  = implode( '&', $param_parts );
        $base_string   = 'POST&' . rawurlencode( self::TWEETS_ENDPOINT ) . '&' . rawurlencode( $param_string );
        $signing_key   = rawurlencode( $api_secret ) . '&' . rawurlencode( $tok_secret );
        $signature     = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) );

        $oauth_params['oauth_signature'] = $signature;

        $header_parts = array();
        foreach ( $oauth_params as $k => $v ) {
            $header_parts[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
        }
        $auth_header = 'OAuth ' . implode( ', ', $header_parts );

        $tweet_data = array( 'text' => $content );
        $body       = wp_json_encode( $tweet_data );

        $args = array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
            ),
            'body'    => $body,
            'timeout' => 15,
        );

        $response = wp_remote_post( self::TWEETS_ENDPOINT, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $resp_body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 201 !== $status_code ) {
            $error_msg = __( 'Unknown X API error', 'social-sync' );
            if ( isset( $resp_body['detail'] ) ) {
                $error_msg = sanitize_text_field( $resp_body['detail'] );
            } elseif ( isset( $resp_body['errors'][0]['message'] ) && is_array( $resp_body['errors'] ) ) {
                $error_msg = sanitize_text_field( $resp_body['errors'][0]['message'] );
            } elseif ( isset( $resp_body['title'] ) ) {
                $error_msg = sanitize_text_field( $resp_body['title'] );
            }
            if ( 401 === $status_code ) {
                $error_msg .= ' ' . __( 'Check that your app has OAuth 1.0a enabled in X Developer Portal and your access token has Read + Write permission.', 'social-sync' );
            }
            return new WP_Error(
                'x_post_error',
                $error_msg,
                array( 'status' => $status_code )
            );
        }

        $post_id = isset( $resp_body['data']['id'] ) ? $resp_body['data']['id'] : '';
        return array(
            'success' => true,
            'data'    => array( 'id' => $post_id ),
            'message' => sprintf( __( 'Posted to X (Tweet ID: %s)', 'social-sync' ), $post_id ),
        );
    }

    public function disconnect(): bool {
        delete_option( 'socialsync_x_api_key' );
        delete_option( 'socialsync_x_api_key_secret' );
        delete_option( 'socialsync_x_access_token' );
        delete_option( 'socialsync_x_access_token_secret' );
        delete_option( 'socialsync_x_token' );
        delete_option( 'socialsync_x_connected' );
        return true;
    }
}
