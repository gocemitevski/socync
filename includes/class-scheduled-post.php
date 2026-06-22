<?php
/**
 * SocialSync Scheduled Post Model
 *
 * @package SocialSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialSync_Scheduled_Post {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'socialsync_scheduled_posts';
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title TEXT DEFAULT '',
            content TEXT NOT NULL,
            platforms TEXT NOT NULL,
            scheduled_date DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'scheduled',
            error_message TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status (status),
            KEY scheduled_date (scheduled_date)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function insert( array $data ): int {
        global $wpdb;
        $wpdb->insert( self::table_name(), $data, array( '%s', '%s', '%s', '%s', '%s' ) );
        return $wpdb->insert_id;
    }

    public static function update( int $id, array $data ) {
        global $wpdb;
        return $wpdb->update( self::table_name(), $data, array( 'id' => intval( $id ) ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
    }

    public static function delete( int $id ) {
        global $wpdb;
        return $wpdb->delete( self::table_name(), array( 'id' => intval( $id ) ), array( '%d' ) );
    }

    public static function get( int $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table_name() . " WHERE id = %d", intval( $id ) )
        );
    }

    public static function get_all( string $status = '', int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = self::table_name();

        if ( ! empty( $status ) ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY scheduled_date DESC LIMIT %d OFFSET %d",
                    $status,
                    intval( $limit ),
                    intval( $offset )
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY scheduled_date DESC LIMIT %d OFFSET %d",
                intval( $limit ),
                intval( $offset )
            )
        );
    }

    public static function get_due(): array {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = 'scheduled' AND scheduled_date <= %s ORDER BY scheduled_date ASC LIMIT 10",
                current_time( 'mysql' )
            )
        );
    }

    public static function clear_all(): void {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
    }

    public static function count( string $status = '' ): int {
        global $wpdb;
        $table = self::table_name();

        if ( ! empty( $status ) ) {
            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                    $status
                )
            );
        }

        return $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }
}
