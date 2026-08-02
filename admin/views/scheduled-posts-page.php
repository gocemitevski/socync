<?php
/**
 * Schedule admin page view.
 *
 * @package Socync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.Security.NonceVerification.Recommended

$editing_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
$edit_item  = null;
if ( $editing_id ) {
    $edit_item = Socync_Scheduled_Post::get( $editing_id );
}

$page_url = menu_page_url( 'socync-scheduled', false );
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Schedule', 'socync' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Scheduled post saved.', 'socync' ); ?></p></div>
    <?php endif; ?>
    <?php
    $platform_labels = array(
        'x'        => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'facebook' => 'Facebook',
        'bluesky'  => 'Bluesky',
    );
    foreach ( $platform_labels as $slug => $label ) :
        if ( isset( $_GET[ 'success_' . $slug ] ) ) :
        ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf(
                /* translators: %s: Platform name */
                __( '%s: Posted successfully.', 'socync' ),
                $label
            ) ); ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET[ 'error_' . $slug ] ) ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( sprintf(
                /* translators: 1: Platform name 2: Error message */
                __( '%1$s: %2$s', 'socync' ),
                $label,
                sanitize_text_field( wp_unslash( $_GET[ 'error_' . $slug ] ) )
            ) ); ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ( isset( $_GET['dry_run'] ) ) : ?>
        <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Dry run: No actual posts were published. Check the Developer page for details.', 'socync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Scheduled post deleted.', 'socync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['cancelled'] ) ) : ?>
        <div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Scheduled post cancelled.', 'socync' ); ?></p></div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'socync_save_scheduled_post', 'socync-scheduled-post-nonce' ); ?>
        <input type="hidden" name="action" value="socync_save_scheduled_post">
        <?php if ( $edit_item ) : ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr( $edit_item->id ); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="scheduled_content"><?php esc_html_e( 'Content', 'socync' ); ?></label></th>
                    <td>
                        <textarea id="scheduled_content" name="content" class="large-text" rows="5" required><?php echo $edit_item ? esc_textarea( $edit_item->content ) : ''; ?></textarea>
                        <p class="description"><?php esc_html_e( 'The message to post to the selected networks.', 'socync' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Platforms', 'socync' ); ?></th>
                    <td>
                        <?php
                        if ( $edit_item ) {
                            $selected_platforms = json_decode( $edit_item->platforms, true ) ?: array();
                        } else {
                            $selected_platforms = get_option( 'socync_autopost_platforms', array() );
                        }
                        if ( ! is_array( $selected_platforms ) ) {
                            $selected_platforms = array();
                        }
                        foreach ( $platform_labels as $key => $label ) :
                            $checked = in_array( $key, $selected_platforms, true );
                            printf(
                                '<label class="socync-platform-checkbox"><input type="checkbox" name="platforms[]" value="%s" %s /> %s</label>',
                                esc_attr( $key ),
                                checked( $checked, true, false ),
                                esc_html( $label )
                            );
                        endforeach;
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="scheduled_date"><?php esc_html_e( 'Schedule', 'socync' ); ?></label></th>
                    <td>
                        <input type="datetime-local" id="scheduled_date" name="scheduled_date" class="regular-text" value="<?php echo $edit_item ? esc_attr( str_replace( ' ', 'T', $edit_item->scheduled_date ) ) : ''; ?>">
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" name="save" class="button button-primary">
                <?php echo $edit_item ? esc_html__( 'Update Scheduled Post', 'socync' ) : esc_html__( 'Schedule', 'socync' ); ?>
            </button>
            <button type="submit" name="post_now" value="1" class="button"><?php esc_html_e( 'Post Now', 'socync' ); ?></button>
            <?php if ( $edit_item ) : ?>
                <a href="<?php echo esc_url( $page_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'socync' ); ?></a>
            <?php endif; ?>
        </p>
    </form>

<!-- phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.Security.NonceVerification.Recommended -->

</div>
