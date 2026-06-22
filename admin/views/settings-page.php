<?php
/**
 * Connections page view.
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'socialsync_settings', array() );
$x_connected = get_option( 'socialsync_x_connected', false );
$linkedin_connected = get_option( 'socialsync_linkedin_connected', false );
$facebook_connected = get_option( 'socialsync_facebook_connected', false );
$bluesky_connected = get_option( 'socialsync_bluesky_connected', false );
$x_api_key_exists = ! empty( get_option( 'socialsync_x_api_key', '' ) );
$linkedin_token = get_option( 'socialsync_linkedin_token', '' );
$facebook_token = get_option( 'socialsync_facebook_token', '' );
$bluesky_token = get_option( 'socialsync_bluesky_token', '' );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Connections', 'social-sync' ); ?></h1>

    <?php if ( isset( $_GET['oauth_success'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Connected successfully.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['oauth_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( sprintf( __( 'Connection failed: %s', 'social-sync' ), sanitize_text_field( wp_unslash( $_GET['oauth_error'] ) ) ) ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['disconnected'] ) ) : ?>
        <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Disconnected.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['org_selected'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Organization selection saved.', 'social-sync' ); ?></p></div>
    <?php endif; ?>

    <div class="socialsync-admin-wrapper">
        <div class="socialsync-tabs" role="tablist">
            <div class="socialsync-tab active" data-tab="x" role="tab" aria-selected="true" aria-controls="x" tabindex="0">X (Twitter)</div>
            <div class="socialsync-tab" data-tab="linkedin" role="tab" aria-selected="false" aria-controls="linkedin" tabindex="-1">LinkedIn</div>
            <div class="socialsync-tab" data-tab="facebook" role="tab" aria-selected="false" aria-controls="facebook" tabindex="-1">Facebook</div>
            <div class="socialsync-tab" data-tab="bluesky" role="tab" aria-selected="false" aria-controls="bluesky" tabindex="-1">Bluesky</div>
        </div>

        <div id="x" class="socialsync-tab-content active">
            <?php if ( $x_connected && $x_api_key_exists ) : ?>
                <div class="socialsync-connected-row">
                    <div class="notice notice-success inline">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Connected', 'social-sync' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=socialsync_disconnect_x&_wpnonce=' . wp_create_nonce( 'socialsync_disconnect_x' ) ) ); ?>" class="button button-disconnect"><?php esc_html_e( 'Disconnect', 'social-sync' ); ?></a>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' ); ?>
                <input type="hidden" name="action" value="socialsync_save_platform_settings">
                <input type="hidden" name="platform" value="x">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="x_prefix_text"><?php esc_html_e( 'Prefix Text', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="x_prefix_text" name="prefix_text" value="<?php echo esc_attr( $settings['x_prefix_text'] ?? '' ); ?>" placeholder="e.g. New post:" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional text prepended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="x_hashtags"><?php esc_html_e( 'Hashtags', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="x_hashtags" name="hashtags" value="<?php echo esc_attr( $settings['x_hashtags'] ?? '' ); ?>" placeholder="e.g. #tech, #news, #wordpress" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional hashtags appended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'social-sync' ); ?></button></p>
            </form>

            <?php if ( ! $x_connected || ! $x_api_key_exists ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Connect Account', 'social-sync' ); ?></h3>
                <p><?php esc_html_e( 'Enter your X API credentials. Generate an Access Token and Secret from your X Developer Portal App &raquo; Keys and Tokens (OAuth 1.0a User Context).', 'social-sync' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_connect_x', 'socialsync-connect-x-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_connect_x">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="x_api_key"><?php esc_html_e( 'API Key', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="x_api_key" name="x_api_key" class="large-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="x_api_key_secret"><?php esc_html_e( 'API Key Secret', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="x_api_key_secret" name="x_api_key_secret" class="large-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="x_access_token"><?php esc_html_e( 'Access Token', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="x_access_token" name="x_access_token" class="large-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="x_access_token_secret"><?php esc_html_e( 'Access Token Secret', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="x_access_token_secret" name="x_access_token_secret" class="large-text" required></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>
        </div>

        <div id="linkedin" class="socialsync-tab-content">
            <?php if ( $linkedin_connected && $linkedin_token ) : ?>
                <div class="socialsync-connected-row">
                    <div class="notice notice-success inline">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Connected', 'social-sync' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=socialsync_disconnect_linkedin&_wpnonce=' . wp_create_nonce( 'socialsync_disconnect_linkedin' ) ) ); ?>" class="button button-disconnect"><?php esc_html_e( 'Disconnect', 'social-sync' ); ?></a>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' ); ?>
                <input type="hidden" name="action" value="socialsync_save_platform_settings">
                <input type="hidden" name="platform" value="linkedin">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="linkedin_prefix_text"><?php esc_html_e( 'Prefix Text', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="linkedin_prefix_text" name="prefix_text" value="<?php echo esc_attr( $settings['linkedin_prefix_text'] ?? '' ); ?>" placeholder="e.g. New post:" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional text prepended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="linkedin_hashtags"><?php esc_html_e( 'Hashtags', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="linkedin_hashtags" name="hashtags" value="<?php echo esc_attr( $settings['linkedin_hashtags'] ?? '' ); ?>" placeholder="e.g. #tech, #news, #wordpress" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional hashtags appended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'social-sync' ); ?></button></p>
            </form>

            <?php if ( ! $linkedin_connected || ! $linkedin_token ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Connect Account', 'social-sync' ); ?></h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_connect_linkedin', 'socialsync-connect-linkedin-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_connect_linkedin">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="linkedin_client_id"><?php esc_html_e( 'Client ID', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="linkedin_client_id" name="linkedin_client_id" class="large-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="linkedin_client_secret"><?php esc_html_e( 'Client Secret', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="linkedin_client_secret" name="linkedin_client_secret" class="large-text" required></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>

            <?php
            $linkedin_org_id = get_option( 'socialsync_linkedin_org_id', '' );
            ?>
            <?php if ( $linkedin_connected && $linkedin_token ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Organization Settings', 'social-sync' ); ?></h3>
                <p class="description"><?php esc_html_e( 'To post as a LinkedIn Page instead of your personal profile, enter your Page ID below. Leave empty to post as yourself.', 'social-sync' ); ?></p>
                <p class="description">
                    <?php esc_html_e( 'How to find your Page ID:', 'social-sync' ); ?>
                    <a href="https://www.linkedin.com/help/linkedin/answer/a521928" target="_blank"><?php esc_html_e( 'LinkedIn Help Center', 'social-sync' ); ?></a>
                </p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_select_linkedin_org', 'socialsync-select-linkedin-org-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_select_linkedin_org">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="linkedin_org_id"><?php esc_html_e( 'LinkedIn Page ID', 'social-sync' ); ?></label></th>
                            <td>
                                <input type="text" id="linkedin_org_id" name="linkedin_org_id" value="<?php echo esc_attr( $linkedin_org_id ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. 1234567', 'social-sync' ); ?>">
                                <p class="description"><?php esc_html_e( 'Your LinkedIn Page numeric ID. When set, posts will be published as this Page instead of your profile.', 'social-sync' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Page ID', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>
        </div>

        <div id="facebook" class="socialsync-tab-content">
            <?php if ( $facebook_connected && $facebook_token ) : ?>
                <div class="socialsync-connected-row">
                    <div class="notice notice-success inline">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Connected', 'social-sync' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=socialsync_disconnect_facebook&_wpnonce=' . wp_create_nonce( 'socialsync_disconnect_facebook' ) ) ); ?>" class="button button-disconnect"><?php esc_html_e( 'Disconnect', 'social-sync' ); ?></a>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' ); ?>
                <input type="hidden" name="action" value="socialsync_save_platform_settings">
                <input type="hidden" name="platform" value="facebook">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="facebook_prefix_text"><?php esc_html_e( 'Prefix Text', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="facebook_prefix_text" name="prefix_text" value="<?php echo esc_attr( $settings['facebook_prefix_text'] ?? '' ); ?>" placeholder="e.g. New post:" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional text prepended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="facebook_hashtags"><?php esc_html_e( 'Hashtags', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="facebook_hashtags" name="hashtags" value="<?php echo esc_attr( $settings['facebook_hashtags'] ?? '' ); ?>" placeholder="e.g. #tech, #news, #wordpress" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional hashtags appended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'social-sync' ); ?></button></p>
            </form>

            <?php if ( ! $facebook_connected || ! $facebook_token ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Connect Account', 'social-sync' ); ?></h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_connect_facebook', 'socialsync-connect-facebook-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_connect_facebook">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="facebook_client_id"><?php esc_html_e( 'Client ID', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="facebook_client_id" name="facebook_client_id" class="large-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="facebook_client_secret"><?php esc_html_e( 'Client Secret', 'social-sync' ); ?></label></th>
                            <td><input type="text" id="facebook_client_secret" name="facebook_client_secret" class="large-text" required></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>

            <?php
            $fb_pages = get_option( 'socialsync_facebook_pages_cache', array() );
            $fb_page_id = get_option( 'socialsync_facebook_page_id', '' );
            ?>
            <?php if ( $facebook_connected && $facebook_token && ! empty( $fb_pages ) ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Page Settings', 'social-sync' ); ?></h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_select_facebook_page', 'socialsync-select-facebook-page-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_select_facebook_page">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="facebook_page_id"><?php esc_html_e( 'Post to Page', 'social-sync' ); ?></label></th>
                            <td>
                                <select name="facebook_page_id" id="facebook_page_id">
                                    <option value="">— <?php esc_html_e( 'Post as User (Timeline)', 'social-sync' ); ?> —</option>
                                    <?php foreach ( $fb_pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( $page['id'] ); ?>" <?php selected( $fb_page_id, $page['id'] ); ?>>
                                            <?php echo esc_html( $page['name'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Page Selection', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>
        </div>

        <div id="bluesky" class="socialsync-tab-content">
            <?php if ( $bluesky_connected && $bluesky_token ) : ?>
                <div class="socialsync-connected-row">
                    <div class="notice notice-success inline">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Connected', 'social-sync' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=socialsync_disconnect_bluesky&_wpnonce=' . wp_create_nonce( 'socialsync_disconnect_bluesky' ) ) ); ?>" class="button button-disconnect"><?php esc_html_e( 'Disconnect', 'social-sync' ); ?></a>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'socialsync_save_platform_settings', 'socialsync-platform-settings-nonce' ); ?>
                <input type="hidden" name="action" value="socialsync_save_platform_settings">
                <input type="hidden" name="platform" value="bluesky">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bluesky_prefix_text"><?php esc_html_e( 'Prefix Text', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="bluesky_prefix_text" name="prefix_text" value="<?php echo esc_attr( $settings['bluesky_prefix_text'] ?? '' ); ?>" placeholder="e.g. New post:" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional text prepended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bluesky_hashtags"><?php esc_html_e( 'Hashtags', 'social-sync' ); ?></label></th>
                        <td>
                            <input type="text" id="bluesky_hashtags" name="hashtags" value="<?php echo esc_attr( $settings['bluesky_hashtags'] ?? '' ); ?>" placeholder="e.g. #tech, #news, #wordpress" class="large-text">
                            <p class="description"><?php esc_html_e( 'Optional hashtags appended to every post sent to this platform.', 'social-sync' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'social-sync' ); ?></button></p>
            </form>

            <?php if ( ! $bluesky_connected || ! $bluesky_token ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Connect Account', 'social-sync' ); ?></h3>
                <p><?php esc_html_e( 'Enter your Bluesky handle (or email) and an App Password. Generate an App Password from Settings &raquo; App Passwords in Bluesky.', 'social-sync' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'socialsync_connect_bluesky', 'socialsync-connect-bluesky-nonce' ); ?>
                    <input type="hidden" name="action" value="socialsync_connect_bluesky">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="bluesky_identifier"><?php esc_html_e( 'Identifier', 'social-sync' ); ?></label></th>
                            <td>
                                <input type="text" id="bluesky_identifier" name="bluesky_identifier" class="large-text" required placeholder="handle.bsky.social">
                                <p class="description"><?php esc_html_e( 'Your Bluesky handle or email address.', 'social-sync' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bluesky_app_password"><?php esc_html_e( 'App Password', 'social-sync' ); ?></label></th>
                            <td>
                                <input type="password" id="bluesky_app_password" name="bluesky_app_password" class="large-text" required>
                                <p class="description"><?php esc_html_e( 'An App Password from Bluesky Settings &raquo; App Passwords.', 'social-sync' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Connect', 'social-sync' ); ?></button></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
