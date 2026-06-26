<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dev_mode  = get_option( 'socialsync_dev_mode', false );
$dry_run   = get_option( 'socialsync_dry_run', false );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Settings', 'social-sync' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['cleared'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Developer log cleared.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! $dev_mode ) : ?>
        <div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Enable Developer Mode to start collecting detailed event logs.', 'social-sync' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'socialsync_save_dev_settings', 'socialsync-dev-settings-nonce' ); ?>
        <input type="hidden" name="action" value="socialsync_save_dev_settings">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Developer Mode', 'social-sync' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dev_mode" value="1" <?php checked( $dev_mode ); ?>>
                            <?php esc_html_e( 'Enable verbose logging of all plugin events, API requests, and responses.', 'social-sync' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Dry Run', 'social-sync' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dry_run" value="1" <?php checked( $dry_run ); ?>>
                            <?php esc_html_e( 'Log everything but skip actual API calls. Requires Developer Mode to be enabled.', 'social-sync' ); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <hr>

        <h2><?php esc_html_e( 'Autoposting', 'social-sync' ); ?></h2>
        <p><?php esc_html_e( 'Select which platforms to auto-post to when a WordPress post is published.', 'social-sync' ); ?></p>

        <table class="form-table" role="presentation">
            <tbody>
                <?php
                $autopost_platforms = get_option( 'socialsync_autopost_platforms', array() );
                if ( ! is_array( $autopost_platforms ) ) {
                    $autopost_platforms = array();
                }
                $platform_labels = array(
                    'x'        => __( 'X (Twitter)', 'social-sync' ),
                    'linkedin' => __( 'LinkedIn', 'social-sync' ),
                    'facebook' => __( 'Facebook', 'social-sync' ),
                    'bluesky'  => __( 'Bluesky', 'social-sync' ),
                );
                foreach ( $platform_labels as $slug => $label ) :
                    $connected = get_option( 'socialsync_' . $slug . '_connected', false );
                    $disabled  = ! $connected ? 'disabled' : '';
                ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $label ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="autopost_platforms[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $autopost_platforms, true ) ); ?> <?php echo $disabled; ?>>
                            <?php if ( $connected ) : ?>
                                <?php esc_html_e( 'Auto-post to', 'social-sync' ); ?>
                                <?php echo esc_html( $label ); ?>
                                <?php esc_html_e( ' when publishing a post.', 'social-sync' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Not connected.', 'social-sync' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=social-sync-settings&tab=' . $slug ) ); ?>">
                                    <?php esc_html_e( 'Connect', 'social-sync' ); ?>
                                </a>
                            <?php endif; ?>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" name="save" class="button button-primary">
                <?php esc_html_e( 'Save Settings', 'social-sync' ); ?>
            </button>
        </p>
    </form>
</div>
