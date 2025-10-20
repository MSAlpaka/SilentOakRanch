<?php
/**
 * Database helper for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

use WP_Error;

class DB {
    const TABLE = 'sor_bookings';

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
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY resource (resource),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta( $sql );
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
            'per_page'  => 20,
            'paged'     => 1,
            'offset'    => null,
            'resource'  => '',
            'status'    => '',
            'date_from' => '',
            'date_to'   => '',
        );

        $args  = \wp_parse_args( $args, $defaults );
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $table = $wpdb->prefix . self::TABLE;

        $values = array();
        $where  = $this->build_where_clause( $args, $values );

        $sql = "SELECT * FROM {$table}{$where} ORDER BY created_at {$order}";

        $limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : intval( $args['per_page'] );
        $limit = 0 === $limit ? 0 : max( -1, $limit );

        if ( 0 !== $limit ) {
            if ( $limit < 0 ) {
                $limit = intval( $args['per_page'] );
            }

            $offset = null !== $args['offset'] ? intval( $args['offset'] ) : ( max( 1, intval( $args['paged'] ) ) - 1 ) * max( 1, intval( $args['per_page'] ) );
            $offset = max( 0, $offset );

            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
        }

        if ( ! empty( $values ) ) {
            array_unshift( $values, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $values );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Count bookings based on filters.
     *
     * @param array $args Query filters.
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

        $args   = \wp_parse_args( $args, $defaults );
        $values = array();
        $where  = $this->build_where_clause( $args, $values );
        $table  = $wpdb->prefix . self::TABLE;
        $sql    = "SELECT COUNT(*) FROM {$table}{$where}";

        if ( ! empty( $values ) ) {
            array_unshift( $values, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $values );
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
                    $sanitized[ $key ] = $this->sanitize_datetime( $value );
                    break;
                default:
                    $sanitized[ $key ] = \sanitize_text_field( $value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Build WHERE clause fragments for booking queries.
     *
     * @param array $args   Filter arguments.
     * @param array $values Prepared statement values (passed by reference).
     *
     * @return string
     */
    private function build_where_clause( array $args, array &$values ) {
        $where = array();

        if ( ! empty( $args['resource'] ) ) {
            $where[] = 'resource = %s';
            $values[] = \sanitize_key( $args['resource'] );
        }

        if ( ! empty( $args['status'] ) ) {
            $allowed_statuses = array( 'pending', 'paid', 'confirmed', 'completed', 'cancelled' );
            $status           = \sanitize_key( $args['status'] );
            if ( in_array( $status, $allowed_statuses, true ) ) {
                $where[] = 'status = %s';
                $values[] = $status;
            }
        }

        if ( ! empty( $args['date_from'] ) ) {
            $from = $this->normalize_date_boundary( $args['date_from'], false );
            if ( $from ) {
                $where[] = 'slot_start >= %s';
                $values[] = $from;
            }
        }

        if ( ! empty( $args['date_to'] ) ) {
            $to = $this->normalize_date_boundary( $args['date_to'], true );
            if ( $to ) {
                $where[] = 'slot_start <= %s';
                $values[] = $to;
            }
        }

        if ( empty( $where ) ) {
            return '';
        }

        return ' WHERE ' . implode( ' AND ', $where );
    }

    /**
     * Normalize date boundaries for queries.
     *
     * @param string $value Date string.
     * @param bool   $end   Whether value is an end boundary.
     *
     * @return string|null
     */
    private function normalize_date_boundary( $value, $end = false ) {
        $value = trim( (string) $value );

        if ( empty( $value ) ) {
            return null;
        }

        $timestamp = strtotime( $value );
        if ( false === $timestamp ) {
            return null;
        }

        $format = $end ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00';

        return gmdate( $format, $timestamp );
    }
}

