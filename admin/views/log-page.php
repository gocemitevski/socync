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
$source_filter = isset( $_GET['source'] ) ? sanitize_key( $_GET['source'] ) : '';
$page_url      = menu_page_url( 'social-sync-log', false );

$platform_labels = array(
    'x'        => 'X (Twitter)',
    'linkedin' => 'LinkedIn',
    'facebook' => 'Facebook',
    'bluesky'  => 'Bluesky',
);

$dev_event_labels = array(
    'enqueue_post'        => __( 'Enqueue Post', 'social-sync' ),
    'publish_event'       => __( 'Publish Event', 'social-sync' ),
    'cron_run'            => __( 'Cron Run', 'social-sync' ),
    'publish_to_platform' => __( 'Publish', 'social-sync' ),
    'dry_run_skip'        => __( 'DRY RUN - Skipped', 'social-sync' ),
    'api_request'         => __( 'API Request', 'social-sync' ),
    'api_response'        => __( 'API Response', 'social-sync' ),
    'post_built'          => __( 'Post Content Built', 'social-sync' ),
    'oauth_callback'      => __( 'OAuth Callback', 'social-sync' ),
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
    $log_type   = isset( $entry['type'] ) ? $entry['type'] : 'wp_post';

    // v1 entries stored the scheduled_post row ID instead of the WP post ID.
    // v2+ entries (after the scheduler fix) have the correct WP post ID.
    if ( 'wp_post' === $log_type && $post_id ) {
        $log_version = isset( $entry['log_version'] ) ? (int) $entry['log_version'] : 0;
        if ( $log_version < 2 ) {
            $scheduled = SocialSync_Scheduled_Post::get( $post_id );
            if ( $scheduled && $scheduled->post_id ) {
                $post_id = (int) $scheduled->post_id;
            }
        }
    }

    $post_title = 'wp_post' === $log_type && $post_id ? get_the_title( $post_id ) : '';
    $raw_status = isset( $entry['status'] ) ? $entry['status'] : '';
    $entries[]  = array(
        'type'      => $log_type,
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

// Gather developer log entries.
$dev_logs = SocialSync_Dev_Logger::get_logs( SocialSync_Dev_Logger::MAX_LOG );
foreach ( $dev_logs as $entry ) {
    $event    = isset( $entry['event'] ) ? $entry['event'] : '';
    $label    = isset( $dev_event_labels[ $event ] ) ? $dev_event_labels[ $event ] : ucfirst( $event );
    $platform = isset( $entry['platform'] ) ? $entry['platform'] : '';
    $summary  = isset( $entry['summary'] ) ? $entry['summary'] : '';

    $detail_lines = array();
    foreach ( array( 'content', 'url', 'endpoint', 'request_body', 'response_body', 'duration', 'post_id', 'message' ) as $key ) {
        if ( isset( $entry[ $key ] ) && '' !== $entry[ $key ] ) {
            $val = $entry[ $key ];
            if ( is_array( $val ) || is_object( $val ) ) {
                $val = print_r( $val, true );
            }
            $detail_lines[] = strtoupper( $key ) . ":\n" . $val;
        }
    }

    $entries[] = array(
        'type'         => 'dev_log',
        'post_id'      => isset( $entry['post_id'] ) ? intval( $entry['post_id'] ) : 0,
        'title'        => $label,
        'platform'     => $platform,
        'status'       => $event,
        'date'         => isset( $entry['time'] ) ? $entry['time'] : '',
        'message'      => $summary,
        'log_id'       => 'dev_' . md5( $entry['time'] . $event . wp_json_encode( $entry ) ),
        'detail_lines' => $detail_lines,
    );
}

// Sort by date descending.
usort( $entries, function( $a, $b ) {
    return strtotime( $b['date'] ) - strtotime( $a['date'] );
});

// Source counts (before filtering).
$count_all        = count( $entries );
$count_any_post   = count( array_filter( $entries, function( $e ) { return in_array( $e['type'], array( 'wp_post', 'standalone', 'oauth' ), true ); } ) );
$count_dev        = count( array_filter( $entries, function( $e ) { return 'dev_log' === $e['type']; } ) );
$count_scheduled  = count( array_filter( $entries, function( $e ) { return 'scheduled' === $e['status']; } ) );
$count_published  = count( array_filter( $entries, function( $e ) { return 'published' === $e['status']; } ) );
$count_failed     = count( array_filter( $entries, function( $e ) { return 'failed' === $e['status']; } ) );
$count_dry_run    = count( array_filter( $entries, function( $e ) { return 'dry_run' === $e['status']; } ) );
$count_cancelled  = count( array_filter( $entries, function( $e ) { return 'cancelled' === $e['status']; } ) );

// Filter by source.
if ( 'post' === $source_filter ) {
    $entries = array_values( array_filter( $entries, function( $e ) {
        return in_array( $e['type'], array( 'wp_post', 'standalone', 'oauth' ), true );
    } ) );
} elseif ( 'dev' === $source_filter ) {
    $entries = array_values( array_filter( $entries, function( $e ) {
        return 'dev_log' === $e['type'];
    } ) );
}

// Filter by status (hides dev entries when a specific status is selected).
if ( $status_filter ) {
    $entries = array_values( array_filter( $entries, function( $e ) use ( $status_filter ) {
        return $status_filter === $e['status'];
    } ) );
}

// Pagination.
$per_page = get_user_option( 'socialsync_log_per_page' );
if ( false === $per_page ) {
    $per_page = intval( get_option( 'socialsync_log_per_page', 20 ) );
}
if ( $per_page < 1 ) {
    $per_page = 20;
}
$paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$total_items = count( $entries );
$total_pages = ceil( $total_items / $per_page );
$offset      = ( $paged - 1 ) * $per_page;
$entries     = array_slice( $entries, $offset, $per_page );
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
        <li><a href="<?php echo esc_url( remove_query_arg( array( 'source', 'status', 'paged' ), $page_url ) ); ?>" class="<?php echo empty( $source_filter ) && empty( $status_filter ) ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_all ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'source', 'post', remove_query_arg( array( 'status', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'post' === $source_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Posts', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_any_post ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'source', 'dev', remove_query_arg( array( 'status', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'dev' === $source_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Developer', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_dev ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'published', remove_query_arg( array( 'source', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'published' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Published', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_published ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'failed', remove_query_arg( array( 'source', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'failed' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Failed', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_failed ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'scheduled', remove_query_arg( array( 'source', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'scheduled' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Scheduled', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_scheduled ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'dry_run', remove_query_arg( array( 'source', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'dry_run' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Dry Run', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_dry_run ); ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'cancelled', remove_query_arg( array( 'source', 'paged' ), $page_url ) ) ); ?>" class="<?php echo 'cancelled' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Cancelled', 'social-sync' ); ?> <span class="count">(<?php echo esc_html( $count_cancelled ); ?>)</span></a></li>
    </ul>

    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html( sprintf( __( '%d items', 'social-sync' ), $total_items ) ); ?></span>
            <?php if ( $total_pages > 1 ) : ?>
            <span class="pagination-links">
                <?php if ( $paged > 1 ) : ?>
                    <a class="first-page button" href="<?php echo esc_url( remove_query_arg( 'paged', $page_url ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'First page', 'social-sync' ); ?></span><span aria-hidden="true">&laquo;</span></a>
                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'social-sync' ); ?></span><span aria-hidden="true">&lsaquo;</span></a>
                <?php else : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                <?php endif; ?>
                <span class="paging-input">
                    <label for="socialsync-current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Current Page', 'social-sync' ); ?></label>
                    <input class="current-page" id="socialsync-current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $paged ); ?>" size="2" aria-describedby="socialsync-table-paging">
                    <span class="tablenav-paging-text" id="socialsync-table-paging"><?php
                        printf(
                            esc_html__( 'of %s', 'social-sync' ),
                            '<span class="total-pages">' . esc_html( $total_pages ) . '</span>'
                        );
                    ?></span>
                </span>
                <?php if ( $paged < $total_pages ) : ?>
                    <a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Next page', 'social-sync' ); ?></span><span aria-hidden="true">&rsaquo;</span></a>
                    <a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Last page', 'social-sync' ); ?></span><span aria-hidden="true">&raquo;</span></a>
                <?php else : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </div>
        <br class="clear">
    </div>

    <style>
        .socialsync-dev-details { display: none; margin: 8px 0 0; padding: 10px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow: auto; }
        .socialsync-dev-toggle { cursor: pointer; color: #2271b1; text-decoration: underline; }
        .socialsync-dev-dry-tag { display: inline-block; background: #b32d2e; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 3px; margin-left: 6px; font-weight: 600; }
        .column-status .event-type { font-weight: 600; }
        .column-status .event-type.dry-run { color: #b32d2e; }
        .column-status .event-type.api-request { color: #2271b1; }
        .column-status .event-type.api-response { color: #2c8a2c; }

        .tablenav-pages-navspan.button.disabled {
            min-width: 28px;
            text-align: center;
            opacity: 0.5;
            cursor: default;
        }
        .tablenav-pages .pagination-links .button,
        .tablenav-pages .pagination-links .tablenav-pages-navspan {
            min-width: 28px;
            text-align: center;
        }
        .tablenav-pages .current-page {
            width: auto;
        }
        .paging-input {
            margin-left: 2px;
            margin-right: 2px;
        }
        .tablenav-paging-text {
            margin-left: 2px;
        }
    </style>

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-content column-primary"><?php esc_html_e( 'Content', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-message"><?php esc_html_e( 'Message', 'social-sync' ); ?></th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if ( empty( $entries ) ) : ?>
                <tr class="no-items"><td class="colspanchange" colspan="6"><?php esc_html_e( 'No log entries found.', 'social-sync' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $entries as $entry ) : ?>
                    <tr>
                        <td class="column-date"><?php echo esc_html( $entry['date'] ); ?></td>
                        <td class="column-source">
                            <?php
                            if ( 'dev_log' === $entry['type'] ) {
                                esc_html_e( 'Developer', 'social-sync' );
                            } elseif ( 'oauth' === $entry['platform'] ) {
                                esc_html_e( 'SocialSync', 'social-sync' );
                            } elseif ( in_array( $entry['type'], array( 'wp_post', 'standalone' ), true ) ) {
                                esc_html_e( 'WP Post', 'social-sync' );
                            } else {
                                esc_html_e( 'Scheduled', 'social-sync' );
                            }
                            ?>
                        </td>
                        <td class="column-content column-primary" data-colname="<?php esc_attr_e( 'Content', 'social-sync' ); ?>">
                            <?php if ( 'dev_log' === $entry['type'] ) : ?>
                                <strong><em><?php echo esc_html( $entry['title'] ); ?></em></strong>
                            <?php elseif ( 'oauth' === $entry['type'] ) : ?>
                                <strong><em><?php echo esc_html( $entry['message'] ?: $entry['title'] ); ?></em></strong>
                            <?php elseif ( 'wp_post' === $entry['type'] && $entry['post_id'] ) : ?>
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
                        <td class="column-platform">
                            <?php
                            if ( 'dev_log' === $entry['type'] ) {
                                echo esc_html( $entry['platform'] ? socialsync_platform_label( $entry['platform'], $platform_labels ) : '' );
                            } else {
                                echo esc_html( 'wp_post' === $entry['type'] ? socialsync_platform_label( $entry['platform'], $platform_labels ) : implode( ', ', array_map( function( $s ) use ( $platform_labels ) { return socialsync_platform_label( trim( $s ), $platform_labels ); }, explode( ',', $entry['platform'] ) ) ) );
                            }
                            ?>
                        </td>
                        <td class="column-status">
                            <?php if ( 'dev_log' === $entry['type'] ) : ?>
                                <span class="event-type <?php echo esc_attr( sanitize_html_class( $entry['status'] ) ); ?>">
                                    <?php echo esc_html( isset( $dev_event_labels[ $entry['status'] ] ) ? $dev_event_labels[ $entry['status'] ] : ucfirst( $entry['status'] ) ); ?>
                                    <?php if ( 'dry_run_skip' === $entry['status'] ) : ?>
                                        <span class="socialsync-dev-dry-tag">DRY</span>
                                    <?php endif; ?>
                                </span>
                            <?php else : ?>
                                <?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
                            <?php endif; ?>
                        </td>
                        <td class="column-message">
                            <?php if ( 'dev_log' === $entry['type'] ) : ?>
                                <?php if ( $entry['message'] ) : ?>
                                    <div><?php echo esc_html( $entry['message'] ); ?></div>
                                <?php endif; ?>
                                <?php if ( ! empty( $entry['detail_lines'] ) ) : ?>
                                    <a class="socialsync-dev-toggle" data-target="dev-detail-<?php echo esc_attr( $entry['log_id'] ); ?>" href="#"><?php esc_html_e( 'Show details', 'social-sync' ); ?></a>
                                    <pre id="dev-detail-<?php echo esc_attr( $entry['log_id'] ); ?>" class="socialsync-dev-details"><?php echo esc_html( implode( "\n\n---\n\n", $entry['detail_lines'] ) ); ?></pre>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo esc_html( $entry['message'] ?: '—' ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-content column-primary"><?php esc_html_e( 'Content', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'social-sync' ); ?></th>
                <th scope="col" class="manage-column column-message"><?php esc_html_e( 'Message', 'social-sync' ); ?></th>
            </tr>
        </tfoot>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html( sprintf( __( '%d items', 'social-sync' ), $total_items ) ); ?></span>
            <span class="pagination-links">
                <?php if ( $paged > 1 ) : ?>
                    <a class="first-page button" href="<?php echo esc_url( remove_query_arg( 'paged', $page_url ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'First page', 'social-sync' ); ?></span><span aria-hidden="true">&laquo;</span></a>
                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'social-sync' ); ?></span><span aria-hidden="true">&lsaquo;</span></a>
                <?php else : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                <?php endif; ?>
                <span class="paging-input">
                    <span class="tablenav-paging-text"><?php
                        printf(
                            esc_html_x( '%1$s of %2$s', 'paging', 'social-sync' ),
                            esc_html( $paged ),
                            '<span class="total-pages">' . esc_html( $total_pages ) . '</span>'
                        );
                    ?></span>
                </span>
                <?php if ( $paged < $total_pages ) : ?>
                    <a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Next page', 'social-sync' ); ?></span><span aria-hidden="true">&rsaquo;</span></a>
                    <a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages ) ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Last page', 'social-sync' ); ?></span><span aria-hidden="true">&raquo;</span></a>
                <?php else : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>
    <?php endif; ?>

    <script>
    (function() {
        // Dev log detail toggle.
        document.querySelectorAll('.socialsync-dev-toggle').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var target = document.getElementById(this.getAttribute('data-target'));
                if (target) {
                    if (target.style.display === 'block') {
                        target.style.display = 'none';
                        this.textContent = '<?php echo esc_js( __( 'Show details', 'social-sync' ) ); ?>';
                    } else {
                        target.style.display = 'block';
                        this.textContent = '<?php echo esc_js( __( 'Hide details', 'social-sync' ) ); ?>';
                    }
                }
            });
        });

        // Pagination page input: navigate on Enter.
        var pageInput = document.getElementById('socialsync-current-page-selector');
        if (pageInput) {
            pageInput.addEventListener('keydown', function(e) {
                if (13 === e.keyCode) {
                    e.preventDefault();
                    var page = parseInt(this.value, 10);
                    if (page > 0 && page <= <?php echo esc_js( $total_pages ); ?>) {
                        window.location.href = <?php echo wp_json_encode( esc_url_raw( add_query_arg( 'paged', '%PAGE%' ) ) ); ?>.replace('%PAGE%', page);
                    }
                }
            });
        }
    })();
    </script>

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
