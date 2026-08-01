<?php
/**
 * Socync Scheduled Post Model
 *
 * @package Socync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Socync_Scheduled_Post {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'socync_scheduled_posts'; // phpcs:ignore WordPress.DB.DatabaseValue
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED DEFAULT 0,
            title TEXT DEFAULT '',
            content TEXT NOT NULL,
            platforms TEXT NOT NULL,
            scheduled_date DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'scheduled',
            error_message TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status (status),
            KEY scheduled_date (scheduled_date),
            KEY post_id (post_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Ensure post_id column exists on existing installations.
        // dbDelta is unreliable for ALTER TABLE on pre-existing tables.
        $column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'post_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
        if ( ! $column ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN post_id BIGINT UNSIGNED DEFAULT 0 AFTER id, ADD KEY post_id (post_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
        }
    }

    public static function insert( array $data ): int {
        global $wpdb;
        $insert_data = array(
            'post_id'        => isset( $data['post_id'] ) ? intval( $data['post_id'] ) : 0,
            'title'          => $data['title'] ?? '',
            'content'        => $data['content'] ?? '',
            'platforms'      => $data['platforms'] ?? '[]',
            'scheduled_date' => $data['scheduled_date'] ?? current_time( 'mysql' ),
            'status'         => $data['status'] ?? 'scheduled',
        );
        $wpdb->insert( self::table_name(), $insert_data, array( '%d', '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->insert_id;
    }

    public static function update( int $id, array $data ) {
        global $wpdb;
        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( 'post_id' === $key || 'id' === $key ) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        return $wpdb->update( self::table_name(), $data, array( 'id' => intval( $id ) ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public static function delete( int $id ) {
        global $wpdb;
        return $wpdb->delete( self::table_name(), array( 'id' => intval( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public static function get( int $id ) {
        global $wpdb;
        $table = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", intval( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    public static function get_all( string $status = '', int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue

        if ( ! empty( $status ) ) {
            return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY scheduled_date DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $status,
                    intval( $limit ),
                    intval( $offset )
                )
            );
        }

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY scheduled_date DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                intval( $limit ),
                intval( $offset )
            )
        );
    }

    public static function get_due(): array {
        global $wpdb;
        $table = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = 'scheduled' AND scheduled_date <= %s ORDER BY scheduled_date ASC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                current_time( 'mysql' )
            )
        );
    }

    public static function clear_all(): void {
        global $wpdb;
        $table = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue
        $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public static function count( string $status = '' ): int {
        global $wpdb;
        $table = self::table_name(); // phpcs:ignore WordPress.DB.DatabaseValue

        if ( ! empty( $status ) ) {
            return $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $status
                )
            );
        }

        return $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
    }
}
