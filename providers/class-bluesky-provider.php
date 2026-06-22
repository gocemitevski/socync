<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/includes/class-api-handler.php';

class SocialSync_Bluesky_Provider extends SocialSync_API_Handler {

    const SESSION_ENDPOINT  = 'https://bsky.social/xrpc/com.atproto.server.createSession';
    const REFRESH_ENDPOINT  = 'https://bsky.social/xrpc/com.atproto.server.refreshSession';
    const RECORD_ENDPOINT   = 'https://bsky.social/xrpc/com.atproto.repo.createRecord';

    public function __construct() {
        parent::__construct( 'bluesky' );
    }

    private function get_identifier(): string {
        return get_option( 'socialsync_bluesky_identifier', '' );
    }

    private function get_app_password(): string {
        return get_option( 'socialsync_bluesky_app_password', '' );
    }

    public function is_connected(): bool {
        return ! empty( $this->access_token ) && ! empty( get_option( 'socialsync_bluesky_did', '' ) );
    }

    protected function refresh_token(): bool {
        if ( ! empty( $this->access_token ) && $this->is_token_valid() ) {
            return true;
        }

        $refresh_jwt = get_option( 'socialsync_bluesky_refresh_jwt', '' );
        if ( empty( $refresh_jwt ) ) {
            return $this->create_session();
        }

        $response = wp_remote_post( self::REFRESH_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $refresh_jwt,
            ),
            'timeout' => self::DEFAULT_TIMEOUT,
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->create_session();
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            return $this->create_session();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['accessJwt'] ) ) {
            return $this->create_session();
        }

        $this->store_session( $body );
        return true;
    }

    private function create_session(): bool {
        $identifier  = $this->get_identifier();
        $app_password = $this->get_app_password();

        if ( empty( $identifier ) || empty( $app_password ) ) {
            return false;
        }

        $response = wp_remote_post( self::SESSION_ENDPOINT, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'identifier' => $identifier,
                'password'   => $app_password,
            ) ),
            'timeout' => self::DEFAULT_TIMEOUT,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['accessJwt'] ) ) {
            return false;
        }

        $this->store_session( $body );
        return true;
    }

    private function store_session( array $body ): void {
        $access_jwt  = sanitize_text_field( $body['accessJwt'] );
        $refresh_jwt = sanitize_text_field( $body['refreshJwt'] );
        $did         = sanitize_text_field( $body['did'] );

        $this->access_token = $access_jwt;
        $this->token_expiry = time() + 7200;

        update_option( 'socialsync_bluesky_token', array(
            'access_token'  => $access_jwt,
            'created_at'    => time(),
        ) );
        update_option( 'socialsync_bluesky_refresh_jwt', $refresh_jwt );
        update_option( 'socialsync_bluesky_did', $did );
        update_option( 'socialsync_bluesky_connected', true );
    }

    public function publish( string $content ): array|WP_Error {
        if ( ! $this->refresh_token() ) {
            return new WP_Error( 'not_connected', 'Bluesky account not connected. Please reconnect.' );
        }

        if ( ! $this->is_connected() ) {
            return new WP_Error( 'not_connected', 'Bluesky account not connected or session expired.' );
        }

        $did = get_option( 'socialsync_bluesky_did', '' );

        $record = array(
            '$type'     => 'app.bsky.feed.post',
            'text'      => $content,
            'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
        );

        $response = wp_remote_post( self::RECORD_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'repo'       => $did,
                'collection' => 'app.bsky.feed.post',
                'record'     => $record,
            ) ),
            'timeout' => self::DEFAULT_TIMEOUT,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $resp_body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            $error_msg = __( 'Unknown Bluesky API error', 'social-sync' );
            if ( isset( $resp_body['message'] ) ) {
                $error_msg = sanitize_text_field( $resp_body['message'] );
            } elseif ( isset( $resp_body['error'] ) ) {
                $error_msg = sanitize_text_field( $resp_body['error'] );
            }
            return new WP_Error( 'bluesky_post_error', $error_msg, array( 'status' => intval( $status_code ) ) );
        }

        $post_id = isset( $resp_body['uri'] ) ? $resp_body['uri'] : '';
        return array(
            'success' => true,
            'data'    => array( 'id' => $post_id ),
            'message' => sprintf( __( 'Posted to Bluesky (URI: %s)', 'social-sync' ), $post_id ),
        );
    }

    public function disconnect(): bool {
        delete_option( 'socialsync_bluesky_token' );
        delete_option( 'socialsync_bluesky_refresh_jwt' );
        delete_option( 'socialsync_bluesky_did' );
        delete_option( 'socialsync_bluesky_identifier' );
        delete_option( 'socialsync_bluesky_app_password' );
        delete_option( 'socialsync_bluesky_connected' );
        return true;
    }
}
