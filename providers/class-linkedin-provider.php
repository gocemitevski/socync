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

    const BASE_URL = 'https://api.linkedin.com/v2';

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
            return new WP_Error('not_connected', 'LinkedIn not connected.');
        }

        $response = $this->get_api(
            self::BASE_URL . '/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organizationalTarget~(id,localizedName,vanityName)))'
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! isset( $response['elements'] ) || ! is_array( $response['elements'] ) ) {
            return array();
        }

        $orgs = array();
        foreach ( $response['elements'] as $element ) {
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

        $new_access_token = sanitize_text_field(wp_unslash($token_response['access_token']));

        if ( isset($token_response['expires_in']) ) {
            $expiry_seconds = intval($token_response['expires_in']);
            $this->token_expiry = time() + $expiry_seconds;
        } else {
            $this->token_expiry = time() + 86400;
        }

        update_option('socialsync_linkedin_token', array(
            'access_token'  => $new_access_token,
            'refresh_token' => isset( $token_response['refresh_token'] ) ? sanitize_text_field( wp_unslash( $token_response['refresh_token'] ) ) : $refresh_token,
            'expires_in'    => isset($token_response['expires_in']) ? intval($token_response['expires_in']) : 0,
            'created_at'    => time(),
        ));

        return true;
    }

    /**
     * Publish a UGC post to LinkedIn as the selected organization or user.
     *
     * @param string $content Post content.
     * @return array|WP_Error Response from LinkedIn API or error object.
     */
    public function publish( string $content ): array|WP_Error {
        if ( ! $this->is_connected() ) {
            return new WP_Error('not_connected', 'LinkedIn account not connected or access token has expired.');
        }

        $org_id = get_option( 'socialsync_linkedin_org_id', '' );
        if ( ! empty( $org_id ) ) {
            $author = 'urn:li:organization:' . $org_id;
        } else {
            $author = 'urn:li:person:' . get_option( 'socialsync_linkedin_person_id', '' );
        }

        if ( empty( $author ) ) {
            return new WP_Error( 'no_author', 'No LinkedIn profile or organization selected. Go to SocialSync settings.' );
        }

        $post_data = array(
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => array(
                'com.linkedin.ugc.ShareContent' => array(
                    'shareCommentary' => array(
                        'text' => is_string($content) ? $content : '',
                    ),
                    'shareMediaCategory' => 'NONE',
                ),
            ),
            'visibility' => array(
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ),
        );

        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type'  => 'application/json',
            'X-Restli-Format' => 'json',
        );

        $response = wp_remote_post(
            self::BASE_URL . '/ugcPosts',
            array(
                'headers'   => $headers,
                'body'      => json_encode($post_data),
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
            $error_data = json_decode($body, true);

            $message = isset($error_data['message']) ? sanitize_text_field($error_data['message']) : sanitize_text_field($body);

            return new WP_Error(
                'linkedin_post_error',
                $message,
                array('status' => intval($status_code))
            );
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);
        $post_id = isset($decoded_response['id']) ? $decoded_response['id'] : '';

        $preview = esc_html(substr($response_body, 0, 80)) . '...';

        return array(
            'success' => true,
            'message' => sprintf(
                __('Posted to LinkedIn (Post ID: %s)', 'social-sync'),
                $post_id ? esc_html($post_id) : $preview
            ),
        );
    }

    public function disconnect(): bool {
        delete_option('socialsync_linkedin_token');
        delete_option('socialsync_linkedin_org_id');
        delete_option('socialsync_linkedin_orgs_cache');
        delete_option('socialsync_linkedin_person_id');
        delete_option('socialsync_linkedin_connected');
        delete_option('socialsync_linkedin_client_id');
        delete_option('socialsync_linkedin_client_secret');
        delete_option('socialsync_linkedin_redirect_url');
        delete_option('socialsync_linkedin_code_verifier');
        delete_transient('socialsync_linkedin_oauth_state');

        $this->log_success(
            'LinkedIn account disconnected',
            'LinkedIn',
            'success'
        );

        return true;
    }

    protected function log_error( string $error_message, array $context = array() ): void {
        $logs = get_option('socialsync_linkedin_logs', array());

        $log_entry = array(
            'message'    => esc_html($error_message),
            'platform'   => 'LinkedIn',
            'status'     => 'failed',
            'date'       => current_time('mysql'),
            'context'    => is_array($context) ? json_encode($context) : '',
        );

        $logs[] = $log_entry;

        if ( count($logs) > 100 ) {
            array_shift($logs);
        }

        update_option('socialsync_linkedin_logs', $logs, false);
    }

    protected function log_success( string $message, string $platform, string $status, array $context = array() ): void {
        $logs = get_option('socialsync_linkedin_logs', array());

        $log_entry = array(
            'message'    => esc_html($message),
            'platform'   => $platform,
            'status'     => $status,
            'date'       => current_time('mysql'),
            'context'    => is_array($context) ? json_encode($context) : '',
        );

        $logs[] = $log_entry;

        if ( count($logs) > 100 ) {
            array_shift($logs);
        }

        update_option('socialsync_linkedin_logs', $logs, false);
    }
}
