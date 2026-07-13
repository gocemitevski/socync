<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.Security.NonceVerification.Recommended

$dev_mode  = get_option( 'socialsync_dev_mode', false );
$dry_run   = get_option( 'socialsync_dry_run', false );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Settings', 'socialsync' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'socialsync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['cleared'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Developer log cleared.', 'socialsync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! $dev_mode ) : ?>
        <div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Enable Developer Mode to start collecting detailed event logs.', 'socialsync' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'socialsync_save_dev_settings', 'socialsync-dev-settings-nonce' ); ?>
        <input type="hidden" name="action" value="socialsync_save_dev_settings">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Developer Mode', 'socialsync' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dev_mode" value="1" <?php checked( $dev_mode ); ?>>
                            <?php esc_html_e( 'Enable verbose logging of all plugin events, API requests, and responses.', 'socialsync' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Dry Run', 'socialsync' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="dry_run" value="1" <?php checked( $dry_run ); ?> <?php disabled( ! $dev_mode ); ?>>
                            <?php esc_html_e( 'Log everything but skip actual API calls. Requires Developer Mode to be enabled.', 'socialsync' ); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <hr>

        <h2><?php esc_html_e( 'Autoposting', 'socialsync' ); ?></h2>
        <p><?php esc_html_e( 'Select which platforms to auto-post to when a WordPress post is published.', 'socialsync' ); ?></p>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="autopost_delay"><?php esc_html_e( 'Delay', 'socialsync' ); ?></label></th>
                    <td>
                        <input type="number" id="autopost_delay" name="autopost_delay" class="small-text" min="0" value="<?php echo esc_attr( get_option( 'socialsync_autopost_delay', 2 ) ); ?>">
                        <span class="description"><?php esc_html_e( 'minutes after publishing', 'socialsync' ); ?></span>
                    </td>
                </tr>
                <?php
                $autopost_platforms = get_option( 'socialsync_autopost_platforms', array() );
                if ( ! is_array( $autopost_platforms ) ) {
                    $autopost_platforms = array();
                }
                $platform_labels = array(
                    'x'        => __( 'X (Twitter)', 'socialsync' ),
                    'linkedin' => __( 'LinkedIn', 'socialsync' ),
                    'facebook' => __( 'Facebook', 'socialsync' ),
                    'bluesky'  => __( 'Bluesky', 'socialsync' ),
                );
                foreach ( $platform_labels as $slug => $label ) :
                    $connected = get_option( 'socialsync_' . $slug . '_connected', false );
                    $disabled  = ! $connected ? 'disabled' : '';
                ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $label ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="autopost_platforms[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $autopost_platforms, true ) ); ?> <?php echo esc_attr( $disabled ); ?>>
                            <?php if ( $connected ) : ?>
                                <?php esc_html_e( 'Auto-post to', 'socialsync' ); ?>
                                <?php echo esc_html( $label ); ?>
                                <?php esc_html_e( ' when publishing a post.', 'socialsync' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Not connected.', 'socialsync' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=social-sync-settings&tab=' . $slug ) ); ?>">
                                    <?php esc_html_e( 'Connect', 'socialsync' ); ?>
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
                <?php esc_html_e( 'Save', 'socialsync' ); ?>
            </button>
        </p>
    <!-- phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.Security.NonceVerification.Recommended -->
    </form>
</div>
