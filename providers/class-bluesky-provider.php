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

    public function refresh_token(): bool {
        $stored_token = get_option( 'socialsync_bluesky_token', array() );
        $has_proper_expiry = ! empty( $stored_token['expires_in'] );

        if ( ! empty( $this->access_token ) && $this->is_token_valid() && $has_proper_expiry ) {
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
        $identifier   = $this->get_identifier();
        $app_password = $this->get_app_password();

        if ( empty( $identifier ) || empty( $app_password ) ) {
            $this->log_error( 'Bluesky create_session called with empty identifier or password', array() );
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
            $this->log_error( 'Bluesky create_session wp_error: ' . $response->get_error_message(), array() );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_str    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $body_str, true );

        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            $error_msg = 'Bluesky API error';
            if ( isset( $body['error'] ) ) {
                $error_msg .= ': ' . sanitize_text_field( $body['error'] );
            }
            if ( isset( $body['message'] ) ) {
                $error_msg .= ' - ' . sanitize_text_field( $body['message'] );
            }
            $this->log_error( $error_msg, array( 'status' => intval( $status_code ) ) );
            return false;
        }

        if ( ! isset( $body['accessJwt'] ) ) {
            $this->log_error( 'Bluesky create_session: no accessJwt in response', array( 'body' => $body_str ) );
            return false;
        }

        $this->store_session( $body );
        return true;
    }

    private function store_session( array $body ): void {
        $access_jwt  = $body['accessJwt'];
        $refresh_jwt = $body['refreshJwt'];
        $did         = $body['did'];

        $this->access_token = $access_jwt;
        $this->token_expiry = time() + 7200;

        update_option( 'socialsync_bluesky_token', array(
            'access_token'  => $access_jwt,
            'expires_in'    => 7200,
            'created_at'    => time(),
        ) );
        update_option( 'socialsync_bluesky_refresh_jwt', $refresh_jwt );
        update_option( 'socialsync_bluesky_did', $did );
        update_option( 'socialsync_bluesky_connected', true );
    }

    public function publish( string $content, string $url = '' ): array|WP_Error {
        if ( ! $this->refresh_token() ) {
            return new WP_Error( 'not_connected', 'Bluesky account not connected. Please reconnect.' );
        }

        if ( ! $this->is_connected() ) {
            return new WP_Error( 'not_connected', 'Bluesky account not connected or session expired.' );
        }

        $did = get_option( 'socialsync_bluesky_did', '' );

        $facets = $this->build_facets( $content );

        $embed = $this->build_link_embed( $url );
        $record = array(
            '$type'     => 'app.bsky.feed.post',
            'text'      => $content,
            'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
        );

        if ( ! empty( $facets ) ) {
            $record['facets'] = $facets;
        }

        if ( ! empty( $embed ) ) {
            $record['embed'] = $embed;
        }

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
            $error_msg = __( 'Unknown Bluesky API error', 'socialsync' );
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
            'message' => sprintf(
                /* translators: %s: Bluesky post URI */
                __( 'Posted to Bluesky (URI: %s)', 'socialsync' ),
                $post_id
            ),
        );
    }

    private function build_link_embed( string $url ): ?array {
        if ( empty( $url ) ) {
            return null;
        }

        if ( ! socialsync_is_safe_url( $url ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_embed', array(
                'url'     => $url,
                'summary' => 'Skipped OG fetch: URL points to private/reserved IP range',
            ) );
            return null;
        }

        $title       = '';
        $description = '';
        $thumb_blob  = null;

        SocialSync_Dev_Logger::log( 'bluesky_embed', array(
            'url'     => $url,
            'summary' => 'Fetching OG tags for Bluesky embed from: ' . $url,
        ) );

        $response = wp_remote_get( $url, array(
            'timeout'     => 10,
            'sslverify'   => true,
            'redirection' => 0,
            'user-agent'  => 'SocialSync/1.0',
        ) );

        if ( is_wp_error( $response ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_embed', array(
                'url'     => $url,
                'error'   => $response->get_error_message(),
                'summary' => 'Failed to fetch URL for OG tags: ' . $response->get_error_message(),
            ) );
        } else {
            $status = wp_remote_retrieve_response_code( $response );
            if ( is_numeric( $status ) && intval( $status ) >= 200 && intval( $status ) < 300 ) {
                $body = wp_remote_retrieve_body( $response );
                $title       = $this->extract_og_tag( $body, 'og:title' );
                $description = $this->extract_og_tag( $body, 'og:description' );
                $og_image    = $this->extract_og_tag( $body, 'og:image' );

                SocialSync_Dev_Logger::log( 'bluesky_embed', array(
                    'url'         => $url,
                    'og_title'    => $title,
                    'og_image'    => $og_image,
                    'summary'     => 'Extracted OG tags - title: ' . ( $title ?: '(none)' ) . ', image: ' . ( $og_image ?: '(none)' ),
                ) );

                if ( ! empty( $og_image ) ) {
                    $thumb_blob = $this->upload_image_blob( $og_image );
                    SocialSync_Dev_Logger::log( 'bluesky_embed', array(
                        'url'         => $url,
                        'og_image'    => $og_image,
                        'has_thumb'   => $thumb_blob ? 'yes' : 'no',
                        'summary'     => 'Thumbnail upload ' . ( $thumb_blob ? 'succeeded' : 'failed (silent null)' ),
                    ) );
                }
            } else {
                SocialSync_Dev_Logger::log( 'bluesky_embed', array(
                    'url'    => $url,
                    'status' => $status,
                    'summary' => 'URL fetch returned non-200 status: ' . $status,
                ) );
            }
        }

        if ( empty( $title ) ) {
            $title = wp_parse_url( $url, PHP_URL_HOST ) ?: $url;
        }

        $embed = array(
            '$type'    => 'app.bsky.embed.external',
            'external' => array(
                'uri'         => $url,
                'title'       => mb_substr( $title, 0, 300 ),
                'description' => mb_substr( $description, 0, 300 ),
            ),
        );

        if ( ! empty( $thumb_blob ) ) {
            $embed['external']['thumb'] = $thumb_blob;
        }

        SocialSync_Dev_Logger::log( 'bluesky_embed', array(
            'url'         => $url,
            'has_thumb'   => $thumb_blob ? 'yes' : 'no',
            'title'       => $title,
            'summary'     => 'Bluesky embed built for ' . $url . ( $thumb_blob ? ' (with thumbnail)' : ' (no thumbnail)' ),
        ) );

        return $embed;
    }

    private function extract_og_tag( string $html, string $property ): string {
        $patterns = array(
            '/<meta[^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*\/?>/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]*\/?>/i',
        );
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $html, $m ) ) {
                return sanitize_text_field( $m[1] );
            }
        }
        return '';
    }

    private function upload_image_blob( string $image_url ): ?array {
        if ( ! socialsync_is_safe_url( $image_url ) ) {
            return null;
        }

        SocialSync_Dev_Logger::log( 'bluesky_upload', array(
            'image_url' => $image_url,
            'summary'   => 'Downloading OG image: ' . $image_url,
        ) );

        $max_size = 5 * 1024 * 1024; // 5MB limit

        $head_response = wp_remote_head( $image_url, array(
            'timeout'     => 10,
            'sslverify'   => true,
            'redirection' => 0,
            'user-agent'  => 'SocialSync/1.0',
        ) );

        if ( ! is_wp_error( $head_response ) ) {
            $content_length = wp_remote_retrieve_header( $head_response, 'content-length' );
            if ( ! empty( $content_length ) && intval( $content_length ) > $max_size ) {
                SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                    'size'    => intval( $content_length ),
                    'summary' => 'OG image too large (' . size_format( intval( $content_length ) ) . '), skipping thumbnail',
                ) );
                return null;
            }
        }

        $image_response = wp_remote_get( $image_url, array(
            'timeout'     => 15,
            'sslverify'   => true,
            'redirection' => 0,
            'user-agent'  => 'SocialSync/1.0',
        ) );

        if ( is_wp_error( $image_response ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'error'   => $image_response->get_error_message(),
                'summary' => 'Image download failed: ' . $image_response->get_error_message(),
            ) );
            return null;
        }

        $status = wp_remote_retrieve_response_code( $image_response );
        if ( ! is_numeric( $status ) || intval( $status ) < 200 || intval( $status ) >= 300 ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'status'  => $status,
                'summary' => 'Image download returned status: ' . $status,
            ) );
            return null;
        }

        $image_data = wp_remote_retrieve_body( $image_response );
        if ( empty( $image_data ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'summary' => 'Image download returned empty body',
            ) );
            return null;
        }

        if ( strlen( $image_data ) > $max_size ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'size'    => strlen( $image_data ),
                'summary' => 'Downloaded image exceeds 5MB, skipping thumbnail',
            ) );
            return null;
        }

        $mime_type = 'image/jpeg';
        $content_type = wp_remote_retrieve_header( $image_response, 'content-type' );
        if ( ! empty( $content_type ) && in_array( $content_type, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
            $mime_type = $content_type;
        }

        SocialSync_Dev_Logger::log( 'bluesky_upload', array(
            'size'      => strlen( $image_data ),
            'mime_type' => $mime_type,
            'summary'   => 'Uploading ' . strlen( $image_data ) . ' bytes as ' . $mime_type . ' to Bluesky',
        ) );

        $blob_response = wp_remote_post( 'https://bsky.social/xrpc/com.atproto.repo.uploadBlob', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => $mime_type,
            ),
            'body'    => $image_data,
            'timeout' => 20,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $blob_response ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'error'   => $blob_response->get_error_message(),
                'summary' => 'Blob upload wp_error: ' . $blob_response->get_error_message(),
            ) );
            return null;
        }

        $blob_status = wp_remote_retrieve_response_code( $blob_response );
        $blob_body_str = wp_remote_retrieve_body( $blob_response );
        $blob_body = json_decode( $blob_body_str, true );

        if ( ! is_numeric( $blob_status ) || intval( $blob_status ) < 200 || intval( $blob_status ) >= 300 ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'status'      => $blob_status,
                'response'    => $blob_body_str,
                'summary'     => 'Blob upload returned status ' . $blob_status . ': ' . ( $blob_body['message'] ?? $blob_body_str ),
            ) );
            return null;
        }

        if ( ! isset( $blob_body['blob'] ) ) {
            SocialSync_Dev_Logger::log( 'bluesky_upload', array(
                'response'  => $blob_body_str,
                'summary'   => 'Blob upload response missing blob key',
            ) );
            return null;
        }

        SocialSync_Dev_Logger::log( 'bluesky_upload', array(
            'blob_ref' => $blob_body['blob']['ref']['$link'] ?? 'unknown',
            'summary'  => 'Blob upload succeeded, CID: ' . ( $blob_body['blob']['ref']['$link'] ?? 'unknown' ),
        ) );

        return $blob_body['blob'];
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

    private function build_facets( string $text ): array {
        $facets = array();

        // Match hashtags: #word
        preg_match_all( '/(?<=^|\s)#([\p{L}\p{N}_]+)/u', $text, $hashtag_matches, PREG_OFFSET_CAPTURE );
        foreach ( $hashtag_matches[0] as $match ) {
            $tag_text  = $match[0];
            $byte_start = intval( $match[1] );
            $byte_end   = $byte_start + strlen( $tag_text );

            // Skip if # is preceded by a word character (avoid matching mid-word)
            // The lookbehind in regex already handles this, but double-check
            if ( $byte_start > 0 && preg_match( '/[\p{L}\p{N}]/u', substr( $text, $byte_start - 1, 1 ) ) ) {
                continue;
            }

            $facets[] = array(
                'index'    => array(
                    'byteStart' => $byte_start,
                    'byteEnd'   => $byte_end,
                ),
                'features' => array(
                    array(
                        '$type' => 'app.bsky.richtext.facet#tag',
                        'tag'   => substr( $tag_text, 1 ),
                    ),
                ),
            );
        }

        // Match mentions: @handle or @handle.domain
        preg_match_all( '/(?<=^|\s)@([\p{L}\p{N}._-]+(\.[\p{L}\p{N}._-]+)+|[\p{L}\p{N}._-]+)/u', $text, $mention_matches, PREG_OFFSET_CAPTURE );
        foreach ( $mention_matches[0] as $match ) {
            $mention_text = $match[0];
            $byte_start   = intval( $match[1] );
            $byte_end     = $byte_start + strlen( $mention_text );

            $handle = ltrim( $mention_text, '@' );
            $did    = $this->resolve_handle( $handle );

            if ( $did ) {
                $facets[] = array(
                    'index'    => array(
                        'byteStart' => $byte_start,
                        'byteEnd'   => $byte_end,
                    ),
                    'features' => array(
                        array(
                            '$type' => 'app.bsky.richtext.facet#mention',
                            'did'   => $did,
                        ),
                    ),
                );
            }
        }

        // Match URLs: http:// or https:// links
        preg_match_all( '/https?:\/\/[^\s<>"\'(){}|\\^`[\]]+/u', $text, $url_matches, PREG_OFFSET_CAPTURE );
        foreach ( $url_matches[0] as $match ) {
            $url_text   = $match[0];
            $byte_start = intval( $match[1] );
            $byte_end   = $byte_start + strlen( $url_text );

            $facets[] = array(
                'index'    => array(
                    'byteStart' => $byte_start,
                    'byteEnd'   => $byte_end,
                ),
                'features' => array(
                    array(
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri'   => $url_text,
                    ),
                ),
            );
        }

        return $facets;
    }

    private function resolve_handle( string $handle ): ?string {
        $response = wp_remote_get(
            'https://bsky.social/xrpc/com.atproto.identity.resolveHandle?handle=' . rawurlencode( $handle ),
            array( 'timeout' => self::DEFAULT_TIMEOUT )
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( ! is_numeric( $status ) || intval( $status ) !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['did'] ) ? sanitize_text_field( $body['did'] ) : null;
    }

    protected function log_error( string $message, array $context = array() ): void {
        $logs = get_option( 'socialsync_logs', array() );
        $logs[] = array(
            'id'       => uniqid(),
            'post_id'  => 0,
            'platform' => 'bluesky',
            'status'   => 'failed',
            'message'  => $message . ( ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '' ),
            'date'     => current_time( 'mysql' ),
        );
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }
        update_option( 'socialsync_logs', $logs, false );
    }
}
