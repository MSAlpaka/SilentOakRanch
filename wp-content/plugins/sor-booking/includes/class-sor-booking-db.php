<?php
/**
 * Database helper for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

use WP_Error;

class DB {
    const TABLE          = 'sor_bookings';
    const SYNC_LOG_TABLE = 'sor_sync_log';

    /**
     * Create or update database tables.
     */
    public function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            resource varchar(64) NOT NULL,
            name varchar(191) NOT NULL,
            phone varchar(64) NOT NULL,
            email varchar(191) NOT NULL,
            horse_name varchar(191) DEFAULT '' NOT NULL,
            slot_start datetime NULL,
            slot_end datetime NULL,
            price decimal(10,2) DEFAULT 0.00 NOT NULL,
            status enum('pending','paid','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
            payment_ref varchar(191) DEFAULT '' NOT NULL,
            qr_code text NULL,
            synced tinyint(1) NOT NULL DEFAULT 0,
            sync_status varchar(32) NOT NULL DEFAULT 'pending',
            sync_action varchar(32) NOT NULL DEFAULT 'create',
            sync_attempted_at datetime NULL,
            sync_synced_at datetime NULL,
            sync_message text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY resource (resource),
            KEY status (status),
            KEY synced (synced)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta( $sql );

        $log_table = $wpdb->prefix . self::SYNC_LOG_TABLE;
        $log_sql   = "CREATE TABLE {$log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_uuid char(36) NOT NULL,
            action varchar(32) NOT NULL,
            status_code int DEFAULT 0,
            message text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY booking_uuid (booking_uuid)
        ) {$charset_collate};";

        \dbDelta( $log_sql );
    }

    /**
     * Insert a booking record.
     *
     * @param array $data Booking data.
     *
     * @return array|WP_Error
     */
    public function insert_booking( array $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE;
        $uuid       = \wp_generate_uuid4();
        $now        = \current_time( 'mysql' );

        $prepared = array(
            'uuid'       => $uuid,
            'resource'   => \sanitize_key( $data['resource'] ?? '' ),
            'name'       => \sanitize_text_field( $data['name'] ?? '' ),
            'phone'      => \sanitize_text_field( $data['phone'] ?? '' ),
            'email'      => \sanitize_email( $data['email'] ?? '' ),
            'horse_name' => \sanitize_text_field( $data['horse_name'] ?? '' ),
            'slot_start' => $this->sanitize_datetime( $data['slot_start'] ?? '' ),
            'slot_end'   => $this->sanitize_datetime( $data['slot_end'] ?? '' ),
            'price'      => isset( $data['price'] ) ? floatval( $data['price'] ) : 0.00,
            'status'     => 'pending',
            'payment_ref'=> '',
            'qr_code'    => '',
            'synced'     => 0,
            'sync_status'=> 'pending',
            'sync_action'=> 'create',
            'sync_attempted_at' => null,
            'sync_synced_at'    => null,
            'sync_message'      => '',
            'created_at' => $now,
            'updated_at' => $now,
        );

        if ( empty( $prepared['resource'] ) || empty( $prepared['name'] ) || empty( $prepared['email'] ) ) {
            return new WP_Error( 'sor_booking_invalid', \__( 'Missing required booking data.', 'sor-booking' ) );
        }

        $inserted = $wpdb->insert( $table_name, $prepared, array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ) );

        if ( false === $inserted ) {
            return new WP_Error( 'sor_booking_insert_failed', \__( 'Could not create booking.', 'sor-booking' ) );
        }

        return array(
            'id'   => (int) $wpdb->insert_id,
            'uuid' => $uuid,
        );
    }

    /**
     * Determine whether a booking conflicts with an existing slot.
     *
     * @param string      $resource   Resource key.
     * @param string|null $slot_start Slot start datetime.
     * @param string|null $slot_end   Slot end datetime.
     *
     * @return bool
     */
    public function has_slot_conflict( $resource, $slot_start, $slot_end = null ) {
        global $wpdb;

        $resource   = \sanitize_key( $resource );
        $slot_start = $this->sanitize_datetime( $slot_start );
        $slot_end   = $this->sanitize_datetime( $slot_end );

        if ( empty( $resource ) || empty( $slot_start ) ) {
            return false;
        }

        if ( empty( $slot_end ) ) {
            $slot_end = $slot_start;
        }

        if ( $slot_start > $slot_end ) {
            $tmp        = $slot_start;
            $slot_start = $slot_end;
            $slot_end   = $tmp;
        }

        $table = $wpdb->prefix . self::TABLE;
        $sql   = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE resource = %s
               AND status IN ('pending','paid','confirmed','completed')
               AND slot_start IS NOT NULL
               AND slot_start <= %s
               AND COALESCE(slot_end, slot_start) >= %s",
            $resource,
            $slot_end,
            $slot_start
        );

        $count = (int) $wpdb->get_var( $sql );

        return $count > 0;
    }

    /**
     * Update booking status and optional fields.
     *
     * @param int|string $id_or_uuid Booking identifier.
     * @param string     $status     New status.
     * @param array      $fields     Additional fields.
     *
     * @return bool
     */
    public function update_status( $id_or_uuid, $status, array $fields = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE;
        $where      = is_numeric( $id_or_uuid ) ? array( 'id' => (int) $id_or_uuid ) : array( 'uuid' => \sanitize_text_field( $id_or_uuid ) );

        $allowed_statuses = array( 'pending', 'paid', 'confirmed', 'completed', 'cancelled' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return false;
        }

        $data = array_merge(
            array(
                'status'     => $status,
                'updated_at' => \current_time( 'mysql' ),
            ),
            $this->sanitize_fields( $fields )
        );

        $formats = array();
        foreach ( $data as $key => $value ) {
            switch ( $key ) {
                case 'price':
                    $formats[] = '%f';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $where_format = isset( $where['id'] ) ? array( '%d' ) : array( '%s' );

        $updated = $wpdb->update( $table_name, $data, $where, $formats, $where_format );

        return false !== $updated;
    }

    /**
     * Retrieve booking by ID or UUID.
     *
     * @param int|string $id_or_uuid Identifier.
     *
     * @return object|null
     */
    public function get_booking( $id_or_uuid ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE;

        if ( is_numeric( $id_or_uuid ) ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", (int) $id_or_uuid );
        } else {
            $sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE uuid = %s", \sanitize_text_field( $id_or_uuid ) );
        }

        return $wpdb->get_row( $sql );
    }

    /**
     * Retrieve all bookings.
     *
     * @param array $args Optional query arguments.
     *
     * @return array
     */
    public function get_all_bookings( array $args = array() ) {
        global $wpdb;

        $defaults = array(
            'order'     => 'DESC',
            'limit'     => 200,
            'offset'    => 0,
            'resource'  => '',
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
        );

        $args  = \wp_parse_args( $args, $defaults );
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $table = $wpdb->prefix . self::TABLE;

        list( $where, $values ) = $this->build_where_clause( $args );

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $sql .= " ORDER BY created_at {$order}";

        $limit  = isset( $args['limit'] ) ? intval( $args['limit'] ) : 0;
        $offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

        if ( $limit > 0 ) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = max( 0, $offset );
        }

        if ( $values ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Count bookings for filters.
     *
     * @param array $args Filter arguments.
     *
     * @return int
     */
    public function count_bookings( array $args = array() ) {
        global $wpdb;

        $defaults = array(
            'resource'  => '',
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
        );

        $args = \wp_parse_args( $args, $defaults );

        list( $where, $values ) = $this->build_where_clause( $args );

        $table = $wpdb->prefix . self::TABLE;
        $sql   = "SELECT COUNT(*) FROM {$table}";

        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        if ( $values ) {
            $sql = $wpdb->prepare( $sql, $values );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Sanitize datetime values for storage.
     *
     * @param string $value Value to sanitize.
     *
     * @return string|null
     */
    private function sanitize_datetime( $value ) {
        if ( empty( $value ) ) {
            return null;
        }

        $timestamp = strtotime( $value );
        if ( false === $timestamp ) {
            return null;
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Sanitize arbitrary fields.
     *
     * @param array $fields Fields to sanitize.
     *
     * @return array
     */
    private function sanitize_fields( array $fields ) {
        $sanitized = array();

        foreach ( $fields as $key => $value ) {
            switch ( $key ) {
                case 'payment_ref':
                case 'qr_code':
                case 'status':
                    $sanitized[ $key ] = \sanitize_text_field( $value );
                    break;
                case 'price':
                    $sanitized[ $key ] = floatval( $value );
                    break;
                case 'slot_start':
                case 'slot_end':
                case 'sync_attempted_at':
                case 'sync_synced_at':
                    $sanitized[ $key ] = $this->sanitize_datetime( $value );
                    break;
                case 'synced':
                    $sanitized[ $key ] = (int) (bool) $value;
                    break;
                case 'sync_status':
                case 'sync_action':
                    $sanitized[ $key ] = \sanitize_key( $value );
                    break;
                case 'sync_message':
                    $sanitized[ $key ] = \sanitize_textarea_field( $value );
                    break;
                default:
                    $sanitized[ $key ] = \sanitize_text_field( $value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Update arbitrary booking fields.
     *
     * @param int|string $id_or_uuid Booking identifier.
     * @param array      $fields     Fields to update.
     *
     * @return bool
     */
    public function update_booking_fields( $id_or_uuid, array $fields ) {
        global $wpdb;

        if ( empty( $fields ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::TABLE;
        $where      = is_numeric( $id_or_uuid ) ? array( 'id' => (int) $id_or_uuid ) : array( 'uuid' => \sanitize_text_field( $id_or_uuid ) );

        $fields  = $this->sanitize_fields( $fields );
        $formats = array();

        foreach ( $fields as $key => $value ) {
            switch ( $key ) {
                case 'price':
                    $formats[] = '%f';
                    break;
                case 'synced':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $where_format = isset( $where['id'] ) ? array( '%d' ) : array( '%s' );

        $updated = $wpdb->update( $table_name, $fields, $where, $formats, $where_format );

        return false !== $updated;
    }

    /**
     * Retrieve unsynced bookings.
     *
     * @param int $limit Maximum number of records.
     *
     * @return array
     */
    public function get_unsynced_bookings( $limit = 50 ) {
        global $wpdb;

        $limit = max( 1, (int) $limit );
        $table = $wpdb->prefix . self::TABLE;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE synced = 0 ORDER BY COALESCE(sync_attempted_at, updated_at) DESC LIMIT %d",
            $limit
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Count unsynced bookings.
     *
     * @return int
     */
    public function count_unsynced_bookings() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE synced = 0";

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Store a sync error log entry.
     *
     * @param string $booking_uuid Booking UUID.
     * @param string $action       Sync action.
     * @param int    $status_code  HTTP status code.
     * @param string $message      Error message.
     */
    public function log_sync_error( $booking_uuid, $action, $status_code, $message ) {
        global $wpdb;

        $table = $wpdb->prefix . self::SYNC_LOG_TABLE;

        $data = array(
            'booking_uuid' => \sanitize_text_field( $booking_uuid ),
            'action'       => \sanitize_key( $action ),
            'status_code'  => (int) $status_code,
            'message'      => \sanitize_textarea_field( $message ),
            'created_at'   => \current_time( 'mysql' ),
        );

        $wpdb->insert(
            $table,
            $data,
            array( '%s', '%s', '%d', '%s', '%s' )
        );
    }

    /**
     * Fetch the latest sync log entry for a booking.
     *
     * @param string $booking_uuid Booking UUID.
     *
     * @return object|null
     */
    public function get_last_sync_log( $booking_uuid ) {
        global $wpdb;

        $table = $wpdb->prefix . self::SYNC_LOG_TABLE;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_uuid = %s ORDER BY id DESC LIMIT 1",
            \sanitize_text_field( $booking_uuid )
        );

        return $wpdb->get_row( $sql );
    }

    /**
     * Remove sync logs for a booking.
     *
     * @param string $booking_uuid Booking UUID.
     */
    public function clear_sync_logs( $booking_uuid ) {
        global $wpdb;

        $table = $wpdb->prefix . self::SYNC_LOG_TABLE;

        $wpdb->delete(
            $table,
            array( 'booking_uuid' => \sanitize_text_field( $booking_uuid ) ),
            array( '%s' )
        );
    }

    /**
     * Build WHERE clause parts for booking queries.
     *
     * @param array $args Query arguments.
     *
     * @return array
     */
    private function build_where_clause( array $args ) {
        $where  = array();
        $values = array();

        if ( ! empty( $args['resource'] ) ) {
            $where[]  = 'resource = %s';
            $values[] = \sanitize_key( $args['resource'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = \sanitize_key( $args['status'] );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $from = $this->sanitize_datetime( $args['date_from'] . ' 00:00:00' );
            if ( $from ) {
                $where[]  = 'COALESCE(slot_start, created_at) >= %s';
                $values[] = $from;
            }
        }

        if ( ! empty( $args['date_to'] ) ) {
            $to = $this->sanitize_datetime( $args['date_to'] . ' 23:59:59' );
            if ( $to ) {
                $where[]  = 'COALESCE(slot_start, created_at) <= %s';
                $values[] = $to;
            }
        }

        return array( $where, $values );
    }
}

