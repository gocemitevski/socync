<?php
/**
 * SocialSync LinkedIn Provider Class
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname(dirname(__FILE__)) . '/includes/class-api-handler.php';

/**
 * LinkedIn API Provider for SocialSync plugin.
 */
class SocialSync_Linkedin_Provider extends SocialSync_API_Handler {

    const BASE_URL     = 'https://api.linkedin.com/v2';
    const POSTS_URL    = 'https://api.linkedin.com/rest/posts';
    const IMAGES_URL   = 'https://api.linkedin.com/rest/images?action=initializeUpload';

    public function __construct() {
        parent::__construct('linkedin');
    }

    /**
     * Fetch LinkedIn organizations the user administers.
     *
     * @return array|WP_Error List of organizations or error.
     */
    public function get_organizations(): array|WP_Error {
        if ( ! $this->is_connected() ) {
            return new WP_Error('not_connected', __( 'LinkedIn not connected.', 'socialsync' ));
        }

        $response = wp_remote_get(
            self::BASE_URL . '/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organizationalTarget~(id,localizedName,vanityName)))',
            array(
                'headers' => array(
                    'Authorization'      => 'Bearer ' . $this->access_token,
                    'LinkedIn-Version'   => '202401',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ),
                'timeout' => self::DEFAULT_TIMEOUT,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = wp_remote_retrieve_response_code( $response );

        if ( ! is_numeric( $status ) || intval( $status ) < 200 || intval( $status ) >= 300 ) {
            $err_msg = isset( $body['message'] ) ? $body['message'] : '';
            return new WP_Error( 'linkedin_api_error', '' !== $err_msg ? sanitize_text_field( $err_msg ) : __( 'Unknown error', 'socialsync' ) );
        }

        if ( ! isset( $body['elements'] ) || ! is_array( $body['elements'] ) ) {
            return array();
        }

        $orgs = array();
        foreach ( $body['elements'] as $element ) {
            if ( isset( $element['organizationalTarget~'] ) ) {
                $orgs[] = array(
                    'id'          => $element['organizationalTarget~']['id'],
                    'name'        => $element['organizationalTarget~']['localizedName'],
                    'vanity_name' => $element['organizationalTarget~']['vanityName'] ?? '',
                );
            }
        }

        return $orgs;
    }

    /**
     * Refresh the LinkedIn OAuth 2.0 access token.
     *
     * @return bool True if refresh successful, false on failure.
     */
    protected function refresh_token(): bool {
        if ( $this->is_token_valid() ) {
            return true;
        }

        $token_data = get_option( 'socialsync_linkedin_token', array() );
        $refresh_token = isset( $token_data['refresh_token'] ) ? $token_data['refresh_token'] : '';

        if ( empty( $refresh_token ) ) {
            $this->log_error( 'LinkedIn token expired and no refresh token available.', array() );
            return false;
        }

        $post_data = array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => get_option('socialsync_linkedin_client_id'),
            'client_secret' => get_option('socialsync_linkedin_client_secret'),
        );

        $response = wp_remote_post(
            'https://www.linkedin.com/oauth/v2/accessToken',
            array(
                'headers'   => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'      => http_build_query($post_data),
                'timeout'   => self::DEFAULT_TIMEOUT,
                'sslverify' => true,
            )
        );

        if ( is_wp_error($response) ) {
            $this->log_error('LinkedIn token refresh failed with wp error', $response);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ( ! is_numeric($status_code) || intval($status_code) < 200 || intval($status_code) >= 300 ) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);

            $message = isset($error_data['error_description']) ? $error_data['error_description'] : $body;
            $this->log_error('LinkedIn token refresh failed: ' . $message, array('status' => intval($status_code)));

            return false;
        }

        $token_response = json_decode(wp_remote_retrieve_body($response), true);

        if ( ! isset($token_response['access_token']) ) {
            $this->log_error('LinkedIn API returned no access token in refresh response', array());
            return false;
        }

        $new_access_token = $token_response['access_token'];

        if ( isset($token_response['expires_in']) ) {
            $expiry_seconds = intval($token_response['expires_in']);
            $this->token_expiry = time() + $expiry_seconds;
        } else {
            $this->token_expiry = time() + 86400;
        }

        update_option('socialsync_linkedin_token', array(
            'access_token'  => $new_access_token,
            'refresh_token' => isset( $token_response['refresh_token'] ) ? $token_response['refresh_token'] : $refresh_token,
            'expires_in'    => isset($token_response['expires_in']) ? intval($token_response['expires_in']) : 0,
            'created_at'    => time(),
        ));

        return true;
    }

    /**
     * Publish a post to LinkedIn as the selected organization or user.
     *
     * @param string $content Post content.
     * @param string $url     Optional URL to attach as article link.
     * @return array|WP_Error Response from LinkedIn API or error object.
     */
    public function publish( string $content, string $url = '' ): array|WP_Error {
        // Dev log: entering LinkedIn publish
        SocialSync_Dev_Logger::log( 'linkedin_publish', array(
            'summary' => 'Entering LinkedIn publish()',
        ) );

        if ( ! $this->refresh_token() ) {
            SocialSync_Dev_Logger::log( 'linkedin_publish', array(
                'summary' => 'LinkedIN publish: refresh_token() FAILED. Token valid: ' . ( $this->is_token_valid() ? 'yes' : 'no' ) . ', token_expiry: ' . ( $this->token_expiry ?? 'null' ) . ', time(): ' . time(),
            ) );
            return new WP_Error( 'token_expired', __( 'LinkedIn access token expired and could not be refreshed. Please reconnect.', 'socialsync' ) );
        }

        SocialSync_Dev_Logger::log( 'linkedin_publish', array(
            'summary' => 'LinkedIn publish: refresh_token() OK, is_connected: ' . ( $this->is_connected() ? 'yes' : 'no' ) . ', has_token: ' . ( ! empty( $this->access_token ) ? 'yes' : 'no' ),
        ) );

        if ( ! $this->is_connected() ) {
            return new WP_Error('not_connected', __( 'LinkedIn account not connected or access token has expired.', 'socialsync' ));
        }

        $org_id    = get_option( 'socialsync_linkedin_org_id', '' );
        $person_id = get_option( 'socialsync_linkedin_person_id', '' );

        if ( ! empty( $org_id ) ) {
            $author = 'urn:li:organization:' . $org_id;
        } elseif ( ! empty( $person_id ) ) {
            $author = 'urn:li:person:' . $person_id;
        } else {
            SocialSync_Dev_Logger::log( 'linkedin_publish', array(
                'summary' => 'LinkedIn publish: no author (both org_id and person_id are empty)',
            ) );
            return new WP_Error( 'no_author', __( 'No LinkedIn profile or organization selected. Go to SocialSync settings.', 'socialsync' ) );
        }

        SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
            'step'    => 'before_api_call',
            'author'  => $author,
            'summary' => 'About to call LinkedIn API for author: ' . $author,
        ) );

        // Build post data for the new Posts API (/rest/posts)
        $post_data = array(
            'author'                     => $author,
            'commentary'                 => is_string( $content ) ? $content : '',
            'visibility'                 => 'PUBLIC',
            'lifecycleState'             => 'PUBLISHED',
            'isReshareDisabledByAuthor'  => false,
            'distribution'               => array(
                'feedDistribution'             => 'MAIN_FEED',
                'targetEntities'               => array(),
                'thirdPartyDistributionChannels' => array(),
            ),
        );

        // If URL provided, fetch OG metadata and add article content
        if ( ! empty( $url ) ) {
            $og = $this->fetch_og_metadata( $url );

            $article = array(
                'source'      => $url,
                'title'       => mb_substr( $og['title'] ?: wp_parse_url( $url, PHP_URL_HOST ) ?: $url, 0, 300 ),
                'description' => mb_substr( $og['description'], 0, 300 ),
            );

            // Upload OG image as thumbnail if available
            if ( ! empty( $og['image_url'] ) ) {
                $thumbnail_urn = $this->upload_thumbnail( $og['image_url'], $author );
                if ( $thumbnail_urn ) {
                    $article['thumbnail'] = $thumbnail_urn;
                }
            }

            $post_data['content'] = array(
                'article' => $article,
            );
        }

        $headers = array(
            'Authorization'              => 'Bearer ' . $this->access_token,
            'Content-Type'               => 'application/json',
            'X-Restli-Protocol-Version'  => '2.0.0',
            'LinkedIn-Version'           => '202606', // Version expires annually and needs to be updated! Next one will be 202706.
        );

        SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
            'step'    => 'wp_remote_post_start',
            'summary' => 'Sending POST to ' . self::POSTS_URL,
        ) );

        $response = wp_remote_post(
            self::POSTS_URL,
            array(
                'headers'   => $headers,
                'body'      => wp_json_encode( $post_data ),
                'timeout'   => self::DEFAULT_TIMEOUT,
                'sslverify' => true,
            )
        );

        SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
            'step'    => 'wp_remote_post_end',
            'is_wp_error' => is_wp_error( $response ) ? 'yes' : 'no',
            'summary' => 'wp_remote_post completed for LinkedIn' . ( is_wp_error( $response ) ? ' (WP_Error: ' . $response->get_error_message() . ')' : '' ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
            'step'        => 'response_handling',
            'status_code' => $status_code,
            'summary'     => 'LinkedIn API returned status ' . $status_code,
        ) );

        if ( ! is_numeric( $status_code ) || intval( $status_code ) < 200 || intval( $status_code ) >= 300 ) {
            $body       = wp_remote_retrieve_body( $response );
            $error_data = json_decode( $body, true );

            $message = isset( $error_data['message'] ) ? sanitize_text_field( $error_data['message'] ) : sanitize_text_field( $body );

            SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
                'step'    => 'api_error',
                'message' => $message,
                'summary' => 'LinkedIn API error: ' . $message,
            ) );

            return new WP_Error(
                'linkedin_post_error',
                $message,
                array( 'status' => intval( $status_code ) )
            );
        }

        // Extract post ID from response header (x-restli-id) or body
        $post_id = wp_remote_retrieve_header( $response, 'x-restli-id' );
        if ( empty( $post_id ) ) {
            $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
            $post_id = $decoded['id'] ?? '';
        }

        SocialSync_Dev_Logger::log( 'linkedin_publish_step', array(
            'step'    => 'success',
            'post_id' => $post_id,
            'summary' => 'LinkedIn publish succeeded, post_id: ' . ( $post_id ?: 'unknown' ),
        ) );

        return array(
            'success' => true,
            'message' => sprintf(
                /* translators: %s: LinkedIn post ID */
                __( 'Posted to LinkedIn (Post ID: %s)', 'socialsync' ),
                $post_id ? esc_html( $post_id ) : __( 'Success', 'socialsync' )
            ),
        );
    }

    private function fetch_og_metadata( string $url ): array {
        $meta = array(
            'title'       => '',
            'description' => '',
            'image_url'   => '',
        );

        if ( ! socialsync_is_safe_url( $url ) ) {
            return $meta;
        }

        $response = wp_remote_get( $url, array(
            'timeout'     => 10,
            'sslverify'   => true,
            'redirection' => 0,
            'user-agent'  => 'SocialSync/1.0',
        ) );

        if ( is_wp_error( $response ) ) {
            return $meta;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( ! is_numeric( $status ) || intval( $status ) < 200 || intval( $status ) >= 300 ) {
            return $meta;
        }

        $body = wp_remote_retrieve_body( $response );

        $meta['title']       = sanitize_text_field( $this->extract_og_property( $body, 'og:title' ) );
        $meta['description'] = sanitize_text_field( $this->extract_og_property( $body, 'og:description' ) );
        $meta['image_url']   = esc_url_raw( $this->extract_og_property( $body, 'og:image' ) );

        return $meta;
    }

    private function extract_og_property( string $html, string $property ): string {
        $patterns = array(
            '/<meta[^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*\/?>/i',
            '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]*\/?>/i',
        );
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $html, $m ) ) {
                return $m[1];
            }
        }
        return '';
    }

    private function upload_thumbnail( string $image_url, string $author ): ?string {
        if ( ! socialsync_is_safe_url( $image_url ) ) {
            return null;
        }

        // Step 1: Register the image upload with LinkedIn
        $register_response = wp_remote_post( self::IMAGES_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version'          => '202506',
            ),
            'body' => wp_json_encode( array(
                'initializeUploadRequest' => array(
                    'owner' => $author,
                ),
            ) ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $register_response ) ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'register',
                'error'   => $register_response->get_error_message(),
                'summary' => 'Image register failed: ' . $register_response->get_error_message(),
            ) );
            return null;
        }

        $register_status = wp_remote_retrieve_response_code( $register_response );
        $register_body   = json_decode( wp_remote_retrieve_body( $register_response ), true );

        if ( ! is_numeric( $register_status ) || intval( $register_status ) < 200 || intval( $register_status ) >= 300 ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'register',
                'status'  => $register_status,
                'summary' => 'Image register returned status ' . $register_status,
            ) );
            return null;
        }

        $upload_url = $register_body['value']['uploadUrl'] ?? '';
        $image_urn  = $register_body['value']['image'] ?? '';

        if ( empty( $upload_url ) || empty( $image_urn ) ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'register',
                'summary' => 'Image register response missing uploadUrl or image URN',
            ) );
            return null;
        }

        // Step 2: Download the image
        $image_response = wp_remote_head( $image_url, array(
            'timeout'     => 10,
            'sslverify'   => true,
            'redirection' => 0,
            'user-agent'  => 'SocialSync/1.0',
        ) );

        $max_size = 5 * 1024 * 1024; // 5MB limit

        if ( ! is_wp_error( $image_response ) ) {
            $content_length = wp_remote_retrieve_header( $image_response, 'content-length' );
            if ( ! empty( $content_length ) && intval( $content_length ) > $max_size ) {
                SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                    'step'    => 'download',
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
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'download',
                'error'   => $image_response->get_error_message(),
                'summary' => 'Image download failed: ' . $image_response->get_error_message(),
            ) );
            return null;
        }

        $dl_status = wp_remote_retrieve_response_code( $image_response );
        $image_data = wp_remote_retrieve_body( $image_response );

        if ( ! is_numeric( $dl_status ) || intval( $dl_status ) < 200 || intval( $dl_status ) >= 300 || empty( $image_data ) ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'download',
                'status'  => $dl_status,
                'summary' => 'Image download failed with status ' . $dl_status,
            ) );
            return null;
        }

        if ( strlen( $image_data ) > $max_size ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'download',
                'size'    => strlen( $image_data ),
                'summary' => 'Downloaded image exceeds 5MB, skipping thumbnail',
            ) );
            return null;
        }

        // Step 3: Upload the binary to LinkedIn
        $mime_type = 'image/jpeg';
        $content_type = wp_remote_retrieve_header( $image_response, 'content-type' );
        if ( ! empty( $content_type ) && in_array( $content_type, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
            $mime_type = $content_type;
        }

        $upload_response = wp_remote_request( $upload_url, array(
            'method'    => 'PUT',
            'headers'   => array(
                'Content-Type' => $mime_type,
            ),
            'body'      => $image_data,
            'timeout'   => 20,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $upload_response ) ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'binary_upload',
                'error'   => $upload_response->get_error_message(),
                'summary' => 'Binary upload failed: ' . $upload_response->get_error_message(),
            ) );
            return null;
        }

        $upload_status = wp_remote_retrieve_response_code( $upload_response );
        if ( ! is_numeric( $upload_status ) || intval( $upload_status ) < 200 || intval( $upload_status ) >= 300 ) {
            SocialSync_Dev_Logger::log( 'linkedin_upload', array(
                'step'    => 'binary_upload',
                'status'  => $upload_status,
                'summary' => 'Binary upload returned status ' . $upload_status,
            ) );
            return null;
        }

        SocialSync_Dev_Logger::log( 'linkedin_upload', array(
            'step'      => 'complete',
            'image_urn' => $image_urn,
            'summary'   => 'Thumbnail uploaded: ' . $image_urn,
        ) );

        return $image_urn;
    }

    public function disconnect(): bool {
        delete_option('socialsync_linkedin_token');
        delete_option('socialsync_linkedin_org_id');
        delete_option('socialsync_linkedin_person_id');
        delete_option('socialsync_linkedin_connected');
        delete_option('socialsync_linkedin_client_id');
        delete_option('socialsync_linkedin_client_secret');
        delete_option('socialsync_linkedin_redirect_url');
        delete_option('socialsync_linkedin_code_verifier');
        delete_option('socialsync_linkedin_logs');
        delete_transient('socialsync_linkedin_oauth_state');

        $this->log_success(
            'LinkedIn account disconnected',
            'linkedin',
            'success'
        );

        return true;
    }

    protected function log_error( string $error_message, array $context = array() ): void {
        $logs = get_option( 'socialsync_logs', array() );

        $full_message = $error_message;
        if ( ! empty( $context ) ) {
            $full_message .= ' | ' . wp_json_encode( $context );
        }

        $logs[] = array(
            'id'       => uniqid(),
            'post_id'  => 0,
            'platform' => 'linkedin',
            'status'   => 'failed',
            'message'  => $full_message,
            'date'     => current_time( 'mysql' ),
            'type'     => 'linkedin',
        );

        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }

        update_option( 'socialsync_logs', $logs, false );
    }

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
            'type'     => 'linkedin',
        );

        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -50 );
        }

        update_option( 'socialsync_logs', $logs, false );
    }
}
