<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SocialSync_Dev_Logger {

    const LOG_KEY = 'socialsync_dev_logs';
    const MAX_LOG = 500;

    public static function is_active(): bool {
        return (bool) get_option( 'socialsync_dev_mode', false );
    }

    public static function is_dry_run(): bool {
        return self::is_active() && (bool) get_option( 'socialsync_dry_run', false );
    }

    public static function log( string $event, array $data = array() ): void {
        if ( ! self::is_active() ) {
            return;
        }

        $logs   = get_option( self::LOG_KEY, array() );
        $entry  = array_merge( array(
            'time'  => current_time( 'mysql' ),
            'event' => $event,
        ), $data );

        // Strip HTML tags from string values as defense-in-depth.
        array_walk( $entry, function ( &$value ) {
            if ( is_string( $value ) ) {
                $value = wp_strip_all_tags( $value );
            }
        } );

        $logs[] = $entry;

        if ( count( $logs ) > self::MAX_LOG ) {
            $logs = array_slice( $logs, -self::MAX_LOG );
        }

        update_option( self::LOG_KEY, $logs, false );
    }

    public static function get_logs( int $limit = 100 ): array {
        $logs = get_option( self::LOG_KEY, array() );
        return array_slice( $logs, -$limit );
    }

    public static function clear(): void {
        delete_option( self::LOG_KEY );
    }
}
