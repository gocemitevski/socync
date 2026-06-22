<?php
/**
 * Logs page view.
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$page_url      = menu_page_url( 'social-sync-log', false );

$platform_labels = array(
    'x'        => 'X (Twitter)',
    'linkedin' => 'LinkedIn',
    'facebook' => 'Facebook',
    'bluesky'  => 'Bluesky',
);

/**
 * Translate a platform slug to its display label.
 */
function socialsync_platform_label( string $slug, array $labels ): string {
    return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug );
}

// Gather WP post logs.
$wp_logs = get_option( 'socialsync_logs', array() );
$entries = array();

foreach ( $wp_logs as $entry ) {
    $post_id    = isset( $entry['post_id'] ) ? intval( $entry['post_id'] ) : 0;
    $post_title = $post_id ? get_the_title( $post_id ) : __( '(deleted)', 'social-sync' );
    $raw_status = isset( $entry['status'] ) ? $entry['status'] : '';
    $entries[]  = array(
        'type'      => 'wp_post',
        'post_id'   => $post_id,
        'title'     => $post_title ?: __( '(untitled)', 'social-sync' ),
        'platform'  => isset( $entry['platform'] ) ? $entry['platform'] : '',
        'status'    => 'pending' === $raw_status ? 'scheduled' : $raw_status,
        'date'      => isset( $entry['date'] ) ? $entry['date'] : '',
        'message'   => isset( $entry['message'] ) ? $entry['message'] : '',
        'log_id'    => isset( $entry['id'] ) ? $entry['id'] : '',
    );
}

// Gather standalone scheduled posts.
$scheduled_posts = SocialSync_Scheduled_Post::get_all( '', 200, 0 );
foreach ( $scheduled_posts as $item ) {
    $platforms = json_decode( $item->platforms, true );
    if ( ! is_array( $platforms ) ) {
        $platforms = array();
    }
    $entries[] = array(
        'type'      => 'scheduled',
        'post_id'   => $item->id,
        'title'     => mb_substr( $item->content, 0, 60 ) . ( mb_strlen( $item->content ) > 60 ? '...' : '' ),
        'platform'  => implode( ', ', $platforms ),
        'status'    => $item->status,
        'date'      => $item->scheduled_date,
        'message'   => $item->error_message ?: '',
        'log_id'    => 'sched_' . $item->id,
    );
}

// Sort by date descending.
usort( $entries, function( $a, $b ) {
    return strtotime( $a['date'] ) - strtotime( $b['date'] );
});

// Status counts.
$count_all       = count( $entries );
$count_scheduled = count( array_filter( $entries, function( $e ) { return 'scheduled' === $e['status']; } ) );
$count_published = count( array_filter( $entries, function( $e ) { return 'published' === $e['status']; } ) );
$count_failed    = count( array_filter( $entries, function( $e ) { return 'failed' === $e['status']; } ) );
$count_cancelled = count( array_filter( $entries, function( $e ) { return 'cancelled' === $e['status']; } ) );

// Filter by status.
if ( $status_filter ) {
    $entries = array_values( array_filter( $entries, function( $e ) use ( $status_filter ) {
        return $status_filter === $e['status'];
    } ) );
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Log', 'social-sync' ); ?></h1>

    <?php if ( isset( $_GET['cancelled'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Scheduled post cancelled.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['log_cleared'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Log cleared.', 'social-sync' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['settings_saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Log settings saved.', 'social-sync' ); ?></p></div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <ul class="subsubsub">
        <li><a href="<?php echo esc_url( $page_url ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_all ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'scheduled', $page_url ) ); ?>" class="<?php echo 'scheduled' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Scheduled', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_scheduled ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'published', $page_url ) ); ?>" class="<?php echo 'published' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Published', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_published ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'failed', $page_url ) ); ?>" class="<?php echo 'failed' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Failed', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_failed ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'cancelled', $page_url ) ); ?>" class="<?php echo 'cancelled' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Cancelled', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_cancelled ); ?>)</span></a></li>
    </ul>

    <div class="tablenav top">
        <div class="tablenav-pages one-page">
            <span class="displaying-num"><?php echo esc_html( sprintf( __( '%d items', 'social-sync' ), count( $entries ) ) ); ?></span>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-content column-primary"><?php esc_html_e( 'Content', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-message"><?php esc_html_e( 'Message', 'social-sync' ); ?></th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if ( empty( $entries ) ) : ?>
                <tr class="no-items"><td class="colspanchange" colspan="6"><?php esc_html_e( 'No log entries found.', 'social-sync' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $entries as $entry ) : ?>
                    <tr>
                        <td class="column-source"><?php echo 'oauth' === $entry['platform'] ? esc_html__( 'SocialSync', 'social-sync' ) : ( 'wp_post' === $entry['type'] ? esc_html__( 'WP Post', 'social-sync' ) : esc_html__( 'Scheduled', 'social-sync' ) ); ?></td>
                        <td class="column-content column-primary" data-colname="<?php esc_attr_e( 'Content', 'social-sync' ); ?>">
                            <?php if ( 'wp_post' === $entry['type'] && $entry['post_id'] ) : ?>
                                <strong><a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'] ) ); ?>"><?php echo esc_html( $entry['title'] ); ?></a></strong>
                            <?php else : ?>
                                <strong><em><?php echo esc_html( $entry['title'] ); ?></em></strong>
                            <?php endif; ?>
                            <?php if ( 'wp_post' === $entry['type'] && $entry['log_id'] ) : ?>
                                <div class="row-actions">
                                    <span class="delete"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=socialsync_delete_log&log_id=' . $entry['log_id'] ), 'socialsync_delete_log' ) ); ?>" class="socialsync-delete-log" data-log-id="<?php echo esc_attr( $entry['log_id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'socialsync_delete_log' ) ); ?>"><?php esc_html_e( 'Delete', 'social-sync' ); ?></a></span>
                                </div>
                            <?php elseif ( 'scheduled' === $entry['type'] && $entry['post_id'] ) : ?>
                                <div class="row-actions">
                                    <?php if ( in_array( $entry['status'], array( 'scheduled', 'failed' ), true ) ) : ?>
                                        <span class="edit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=social-sync-scheduled&edit=' . $entry['post_id'] ) ); ?>"><?php esc_html_e( 'Edit', 'social-sync' ); ?></a> | </span>
                                    <?php endif; ?>
                                    <?php if ( 'scheduled' === $entry['status'] ) : ?>
                                        <span class="cancel"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=socialsync_cancel_scheduled_post&id=' . $entry['post_id'] . '&_redirect=log' ), 'socialsync_cancel_scheduled_post_' . $entry['post_id'] ) ); ?>"><?php esc_html_e( 'Cancel', 'social-sync' ); ?></a></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-platform"><?php echo esc_html( 'wp_post' === $entry['type'] ? socialsync_platform_label( $entry['platform'], $platform_labels ) : implode( ', ', array_map( function( $s ) use ( $platform_labels ) { return socialsync_platform_label( trim( $s ), $platform_labels ); }, explode( ',', $entry['platform'] ) ) ) ); ?></td>
                        <td class="column-status"><?php echo esc_html( ucfirst( $entry['status'] ) ); ?></td>
                        <td class="column-date"><?php echo esc_html( $entry['date'] ); ?></td>
                        <td class="column-message"><?php echo esc_html( $entry['message'] ?: '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-content column-primary"><?php esc_html_e( 'Content', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-message"><?php esc_html_e( 'Message', 'social-sync' ); ?></th>
            </tr>
        </tfoot>
    </table>

    <hr>

    <h2><?php esc_html_e( 'Log Management', 'social-sync' ); ?></h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'socialsync_save_log_settings', 'socialsync-log-settings-nonce' ); ?>
        <input type="hidden" name="action" value="socialsync_save_log_settings">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="log_retention_days"><?php esc_html_e( 'Auto-clear after', 'social-sync' ); ?></label></th>
                <td>
                    <input type="number" id="log_retention_days" name="log_retention_days" value="<?php echo esc_attr( get_option( 'socialsync_log_retention_days', 0 ) ); ?>" min="0" class="small-text">
                    <span class="description"><?php esc_html_e( 'days. Set to 0 to disable automatic clearing.', 'social-sync' ); ?></span>
                    <p class="description"><?php esc_html_e( 'Logs and terminal-status scheduled posts older than this will be purged daily.', 'social-sync' ); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'social-sync' ); ?></button>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=socialsync_clear_log' ), 'socialsync_clear_log' ) ); ?>" class="button button-disconnect" onclick="return confirm('<?php echo esc_js( __( 'Clear all log entries? This cannot be undone.', 'social-sync' ) ); ?>');"><?php esc_html_e( 'Clear Log', 'social-sync' ); ?></a>
        </p>
    </form>
</div>
