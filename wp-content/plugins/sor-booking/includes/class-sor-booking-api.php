<?php
/**
 * REST API integration for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class API {
    /**
     * Database handler.
     *
     * @var DB
     */
    protected $db;

    /**
     * QR handler.
     *
     * @var QR
     */
    protected $qr;

    /**
     * PayPal handler.
     *
     * @var PayPal
     */
    protected $paypal;

    /**
     * Constructor.
     *
     * @param DB     $db     Database.
     * @param QR     $qr     QR helper.
     * @param PayPal $paypal PayPal helper.
     */
    public function __construct( DB $db, QR $qr, PayPal $paypal ) {
        $this->db     = $db;
        $this->qr     = $qr;
        $this->paypal = $paypal;

        \add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        \register_rest_route(
            'sor/v1',
            '/book',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_book' ),
                'permission_callback' => array( $this, 'validate_public_request' ),
                'args'                => array(
                    'resource'   => array( 'required' => true ),
                    'name'       => array( 'required' => true ),
                    'email'      => array( 'required' => true ),
                    'phone'      => array(),
                    'horse_name' => array(),
                    'slot_start' => array(),
                    'slot_end'   => array(),
                ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/paypal/webhook',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_paypal_webhook' ),
                'permission_callback' => array( $this, 'validate_private_request' ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/checkin',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_checkin' ),
                'permission_callback' => array( $this, 'validate_private_request' ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/qr',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_qr' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'ref' => array( 'required' => true ),
                ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/admin/update',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_admin_update' ),
                'permission_callback' => array( $this, 'validate_admin_request' ),
                'args'                => array(
                    'uuid'   => array( 'required' => true ),
                    'status' => array( 'required' => true ),
                ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/admin/list',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_admin_list' ),
                'permission_callback' => array( $this, 'validate_admin_request' ),
            )
        );
    }

    /**
     * Validate public REST requests (front-end forms).
     *
     * @param WP_REST_Request $request Request.
     *
     * @return bool
     */
    public function validate_public_request( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( $nonce && \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        return $this->validate_private_request( $request );
    }

    /**
     * Validate private requests using API key header.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return bool
     */
    public function validate_private_request( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( $nonce && \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        $provided = $request->get_header( 'X-SOR-API-Key' );
        if ( $provided && defined( 'SOR_API_KEY' ) && \SOR_API_KEY && hash_equals( \SOR_API_KEY, $provided ) ) {
            return true;
        }

        return \current_user_can( 'manage_options' );
    }

    /**
     * Validate admin requests.
     *
     * @return bool
     */
    public function validate_admin_request() {
        return \current_user_can( 'manage_options' );
    }

    /**
     * Handle booking creation.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_book( WP_REST_Request $request ) {
        $resource   = \sanitize_key( $request->get_param( 'resource' ) );
        $resources  = \sor_booking_get_resources();
        $definition = $resources[ $resource ] ?? null;

        if ( ! $definition ) {
            return $this->error_response( 'unknown_resource', \__( 'Unknown resource.', 'sor-booking' ), 400 );
        }

        $data = array(
            'resource'   => $resource,
            'name'       => $request->get_param( 'name' ),
            'phone'      => $request->get_param( 'phone' ),
            'email'      => $request->get_param( 'email' ),
            'horse_name' => $request->get_param( 'horse_name' ),
            'slot_start' => $request->get_param( 'slot_start' ),
            'slot_end'   => $request->get_param( 'slot_end' ),
            'price'      => $definition['price'],
        );

        if ( 'schmied' === $resource ) {
            $data['slot_start'] = null;
            $data['slot_end']   = null;
        }

        $result = $this->db->insert_booking( $data );

        if ( $result instanceof WP_Error ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message(), 400 );
        }

        $booking = $this->db->get_booking( $result['id'] );

        if ( $booking && floatval( $booking->price ) <= 0 ) {
            $this->db->update_status( $booking->id, 'confirmed' );
            $booking->status = 'confirmed';
        }

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'booking_id' => (int) $result['id'],
                'uuid'       => $result['uuid'],
                'status'     => $booking ? $booking->status : 'pending',
                'price'      => $definition['price'],
                'resource'   => $resource,
            ),
            201
        );
    }

    /**
     * Handle PayPal webhook updates.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_paypal_webhook( WP_REST_Request $request ) {
        $booking_id   = \absint( $request->get_param( 'booking_id' ) );
        $paypal_order = \sanitize_text_field( $request->get_param( 'order_id' ) );
        $amount       = floatval( $request->get_param( 'amount' ) );

        if ( ! $booking_id || empty( $paypal_order ) ) {
            return $this->error_response( 'invalid_payload', \__( 'Missing payment details.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $booking_id );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        $resources  = \sor_booking_get_resources();
        $definition = $resources[ $booking->resource ] ?? null;
        if ( ! $definition ) {
            return $this->error_response( 'unknown_resource', \__( 'Unknown resource configuration.', 'sor-booking' ), 400 );
        }

        if ( ! $this->paypal->validate_amount( $booking->resource, $amount ) ) {
            return $this->error_response( 'amount_mismatch', \__( 'Payment amount mismatch.', 'sor-booking' ), 400 );
        }

        $verification = $this->paypal->verify_order( $paypal_order, $booking );
        if ( \is_wp_error( $verification ) ) {
            return $this->error_response( $verification->get_error_code(), $verification->get_error_message(), 400 );
        }

        $payload = $this->qr->generate_payload( $booking->uuid );
        $qr_url  = $this->qr->render_img( $payload );

        $updated = $this->db->update_status(
            $booking->id,
            'paid',
            array(
                'payment_ref' => $paypal_order,
                'qr_code'     => $payload,
            )
        );

        if ( ! $updated ) {
            return $this->error_response( 'update_failed', \__( 'Could not update booking.', 'sor-booking' ), 500 );
        }

        $booking = $this->db->get_booking( $booking->id );
        $this->send_confirmation_emails( $booking, $qr_url );

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'booking_id' => (int) $booking->id,
                'status'     => 'paid',
                'qr'         => $qr_url,
            ),
            200
        );
    }

    /**
     * Handle QR payload retrieval.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response
     */
    public function handle_qr( WP_REST_Request $request ) {
        $ref = \sanitize_text_field( $request->get_param( 'ref' ) );
        if ( empty( $ref ) ) {
            return $this->error_response( 'invalid_ref', \__( 'Missing reference.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $ref );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        $payload = ! empty( $booking->qr_code ) ? $booking->qr_code : $this->qr->generate_payload( $booking->uuid );
        $qr_url  = $this->qr->render_img( $payload );

        return new WP_REST_Response(
            array(
                'ok'      => true,
                'payload' => $payload,
                'url'     => $qr_url,
            ),
            200
        );
    }

    /**
     * Handle check-in request.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_checkin( WP_REST_Request $request ) {
        $payload = \sanitize_text_field( $request->get_param( 'payload' ) );
        if ( empty( $payload ) ) {
            return $this->error_response( 'invalid_payload', \__( 'Missing QR payload.', 'sor-booking' ), 400 );
        }

        $result = $this->qr->verify_payload( $payload );
        if ( $result instanceof WP_Error ) {
            return $this->error_response( $result->get_error_code(), $result->get_error_message(), 400 );
        }

        $booking = $this->db->get_booking( $result['uuid'] );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        if ( $this->qr->is_payload_expired_for_booking( $booking ) ) {
            return $this->error_response( 'qr_expired', \__( 'QR code expired.', 'sor-booking' ), 400 );
        }

        $this->db->update_status( $booking->id, 'completed' );

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'booking_id' => (int) $booking->id,
                'status'     => 'completed',
            ),
            200
        );
    }

    /**
     * Handle admin booking status updates.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_admin_update( WP_REST_Request $request ) {
        $uuid   = \sanitize_text_field( $request->get_param( 'uuid' ) );
        $status = \sanitize_key( $request->get_param( 'status' ) );

        if ( empty( $uuid ) ) {
            return $this->error_response( 'missing_uuid', \__( 'Booking identifier missing.', 'sor-booking' ), 400 );
        }

        $allowed = array_keys( $this->get_status_labels() );
        if ( ! in_array( $status, $allowed, true ) ) {
            return $this->error_response( 'invalid_status', \__( 'Invalid booking status.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $uuid );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        $previous_status = $booking->status;
        $fields          = array();

        if ( 'confirmed' === $status && empty( $booking->qr_code ) ) {
            $fields['qr_code'] = $this->qr->generate_payload( $booking->uuid );
        }

        $updated = $this->db->update_status( $uuid, $status, $fields );
        if ( ! $updated ) {
            return $this->error_response( 'update_failed', \__( 'Could not update booking.', 'sor-booking' ), 500 );
        }

        $booking = $this->db->get_booking( $uuid );

        if ( in_array( $status, array( 'confirmed', 'cancelled' ), true ) && $previous_status !== $status ) {
            $this->send_status_update_notice( $booking );
        }

        return new WP_REST_Response(
            array(
                'ok'      => true,
                'booking' => $this->prepare_booking_for_response( $booking ),
            ),
            200
        );
    }

    /**
     * Handle admin booking listings.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_admin_list( WP_REST_Request $request ) {
        $resources = \sor_booking_get_resources();

        $resource = \sanitize_key( $request->get_param( 'resource' ) );
        if ( $resource && ! isset( $resources[ $resource ] ) ) {
            $resource = '';
        }

        $status = \sanitize_key( $request->get_param( 'status' ) );
        if ( $status && ! array_key_exists( $status, $this->get_status_labels() ) ) {
            $status = '';
        }

        $date_from = \sanitize_text_field( $request->get_param( 'date_from' ) );
        $date_to   = \sanitize_text_field( $request->get_param( 'date_to' ) );

        $per_page = absint( $request->get_param( 'per_page' ) );
        $per_page = $per_page > 0 ? min( 200, $per_page ) : 20;
        $page     = absint( $request->get_param( 'page' ) );
        $page     = $page > 0 ? $page : 1;

        $filters = array(
            'resource'  => $resource,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        );

        $args = array_merge(
            $filters,
            array(
                'limit'    => $per_page,
                'per_page' => $per_page,
                'paged'    => $page,
            )
        );

        $bookings = $this->db->get_all_bookings( $args );
        $total    = $this->db->count_bookings( $filters );

        $response = array(
            'ok'          => true,
            'bookings'    => array_map( array( $this, 'prepare_booking_for_response' ), $bookings ),
            'total'       => (int) $total,
            'total_pages' => (int) max( 1, ceil( $total / max( 1, $per_page ) ) ),
            'page'        => $page,
        );

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Send confirmation emails after payment.
     *
     * @param object $booking Booking record.
     * @param string $qr_url  QR code image URL.
     */
    protected function send_confirmation_emails( $booking, $qr_url ) {
        if ( empty( $booking ) ) {
            return;
        }

        $to        = $booking->email;
        $admin     = \get_option( 'admin_email' );
        $subject   = sprintf( \__( 'Your booking for %s', 'sor-booking' ), $booking->resource );
        $headers   = array( 'Content-Type: text/html; charset=UTF-8' );
        $resources = \sor_booking_get_resources();
        $details   = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ] : array();

        $body  = '<p>' . \esc_html__( 'Thank you for your booking at Silent Oak Ranch.', 'sor-booking' ) . '</p>';
        $body .= '<p>' . sprintf( \esc_html__( 'Resource: %s', 'sor-booking' ), \esc_html( $details['label'] ?? $booking->resource ) ) . '</p>';
        $body .= '<p>' . sprintf( \esc_html__( 'Date: %s', 'sor-booking' ), \esc_html( $booking->slot_start ) ) . '</p>';
        $body .= '<p>' . sprintf( \esc_html__( 'QR Code: %s', 'sor-booking' ), '<br><img src="' . \esc_url( $qr_url ) . '" alt="QR" />' ) . '</p>';

        $attachments = array();
        $ics         = $this->build_ics( $booking );
        if ( $ics ) {
            $tmp = \wp_tempnam( 'sor-booking' );
            if ( $tmp ) {
                file_put_contents( $tmp, $ics );
                $attachments[] = $tmp;
            }
        }

        \wp_mail( $to, $subject, $body, $headers, $attachments );

        if ( $admin && strtolower( $admin ) !== strtolower( $to ) ) {
            \wp_mail( $admin, '[Admin] ' . $subject, $body, $headers, $attachments );
        }

        foreach ( $attachments as $file ) {
            @unlink( $file );
        }
    }

    /**
     * Prepare booking object for REST responses.
     *
     * @param object $booking Booking record.
     *
     * @return array
     */
    protected function prepare_booking_for_response( $booking ) {
        if ( empty( $booking ) ) {
            return array();
        }

        $resources      = \sor_booking_get_resources();
        $resource_label = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ]['label'] : $booking->resource;
        $status_labels  = $this->get_status_labels();
        $status_label   = isset( $status_labels[ $booking->status ] ) ? $status_labels[ $booking->status ] : ucfirst( $booking->status );
        $date_format    = \get_option( 'date_format' );
        $time_format    = \get_option( 'time_format' );
        $datetime       = trim( $date_format . ' ' . $time_format );

        $slot_start   = $booking->slot_start ? \get_date_from_gmt( $booking->slot_start, $datetime ) : '';
        $slot_end     = $booking->slot_end ? \get_date_from_gmt( $booking->slot_end, $datetime ) : '';
        $created      = $booking->created_at ? \mysql2date( $datetime, $booking->created_at ) : '';
        $updated      = $booking->updated_at ? \mysql2date( $datetime, $booking->updated_at ) : '';
        $price_format = \number_format_i18n( $booking->price, 2 );
        $slot_display = $slot_start;

        if ( $slot_end && $slot_end !== $slot_start ) {
            $slot_display .= ' – ' . $slot_end;
        }

        return array(
            'id'               => (int) $booking->id,
            'uuid'             => $booking->uuid,
            'resource'         => $booking->resource,
            'resource_label'   => $resource_label,
            'name'             => $booking->name,
            'email'            => $booking->email,
            'phone'            => $booking->phone,
            'horse_name'       => $booking->horse_name,
            'slot_start'       => $slot_start,
            'slot_end'         => $slot_end,
            'slot_start_raw'   => $booking->slot_start,
            'slot_end_raw'     => $booking->slot_end,
            'price'            => (float) $booking->price,
            'price_formatted'  => $price_format,
            'price_display'    => $price_format,
            'slot_display'     => $slot_display,
            'status'           => $booking->status,
            'status_label'     => $status_label,
            'payment_ref'      => $booking->payment_ref,
            'created_at'       => $created,
            'updated_at'       => $updated,
            'created_at_raw'   => $booking->created_at,
            'updated_at_raw'   => $booking->updated_at,
            'qr_url'           => $booking->uuid ? \rest_url( 'sor/v1/qr?ref=' . rawurlencode( $booking->uuid ) ) : '',
        );
    }

    /**
     * Retrieve translated status labels.
     *
     * @return array
     */
    protected function get_status_labels() {
        return array(
            'pending'   => \__( 'Pending', 'sor-booking' ),
            'paid'      => \__( 'Paid', 'sor-booking' ),
            'confirmed' => \__( 'Confirmed', 'sor-booking' ),
            'completed' => \__( 'Completed', 'sor-booking' ),
            'cancelled' => \__( 'Cancelled', 'sor-booking' ),
        );
    }

    /**
     * Send notification email for manual status changes.
     *
     * @param object $booking          Booking record.
     * @param string $previous_status  Previous booking status.
     */
    protected function send_status_update_notice( $booking ) {
        if ( empty( $booking ) || empty( $booking->email ) ) {
            return;
        }

        $resources      = \sor_booking_get_resources();
        $resource_label = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ]['label'] : $booking->resource;
        $status_labels  = $this->get_status_labels();
        $status_label   = isset( $status_labels[ $booking->status ] ) ? $status_labels[ $booking->status ] : ucfirst( $booking->status );
        $headers        = array( 'Content-Type: text/html; charset=UTF-8' );
        $date_format    = \get_option( 'date_format' );
        $time_format    = \get_option( 'time_format' );
        $datetime       = trim( $date_format . ' ' . $time_format );
        $slot_start     = $booking->slot_start ? \get_date_from_gmt( $booking->slot_start, $datetime ) : '';
        $slot_end       = $booking->slot_end ? \get_date_from_gmt( $booking->slot_end, $datetime ) : '';
        $price_value    = floatval( $booking->price );
        $price_formated = $price_value > 0 ? \number_format_i18n( $price_value, 2 ) : '';
        $subject        = sprintf( \__( 'Booking status update: %s', 'sor-booking' ), $resource_label );

        $body  = '<p>' . \esc_html__( 'Hello from Silent Oak Ranch!', 'sor-booking' ) . '</p>';
        $body .= '<p>' . \wp_kses_post( sprintf( \__( 'The status of your booking is now: %s', 'sor-booking' ), '<strong>' . \esc_html( $status_label ) . '</strong>' ) ) . '</p>';
        $body .= '<p>' . sprintf( \esc_html__( 'Booking reference: %s', 'sor-booking' ), \esc_html( $booking->uuid ) ) . '</p>';
        $body .= '<p>' . sprintf( \esc_html__( 'Resource: %s', 'sor-booking' ), \esc_html( $resource_label ) ) . '</p>';

        if ( $slot_start ) {
            $body .= '<p>' . sprintf( \esc_html__( 'Start: %s', 'sor-booking' ), \esc_html( $slot_start ) ) . '</p>';
        }

        if ( $slot_end && $slot_end !== $slot_start ) {
            $body .= '<p>' . sprintf( \esc_html__( 'End: %s', 'sor-booking' ), \esc_html( $slot_end ) ) . '</p>';
        }

        if ( ! empty( $booking->horse_name ) ) {
            $body .= '<p>' . sprintf( \esc_html__( 'Horse: %s', 'sor-booking' ), \esc_html( $booking->horse_name ) ) . '</p>';
        }

        if ( $price_formated ) {
            $body .= '<p>' . sprintf( \esc_html__( 'Price: %s €', 'sor-booking' ), \esc_html( $price_formated ) ) . '</p>';
        }

        $attachments = array();

        if ( 'confirmed' === $booking->status ) {
            $payload = ! empty( $booking->qr_code ) ? $booking->qr_code : $this->qr->generate_payload( $booking->uuid );

            if ( empty( $booking->qr_code ) && $payload ) {
                $this->db->update_status( $booking->uuid, 'confirmed', array( 'qr_code' => $payload ) );
                $booking->qr_code = $payload;
            }

            $qr_url = $this->qr->render_img( $booking->qr_code );
            $body  .= '<p>' . \wp_kses_post( sprintf( \__( 'QR code for check-in: %s', 'sor-booking' ), '<br><img src="' . \esc_url( $qr_url ) . '" alt="QR" />' ) ) . '</p>';

            $ics = $this->build_ics( $booking );
            if ( $ics ) {
                $tmp = \wp_tempnam( 'sor-booking' );
                if ( $tmp ) {
                    file_put_contents( $tmp, $ics );
                    $attachments[] = $tmp;
                }
            }
        }

        \wp_mail( $booking->email, $subject, $body, $headers, $attachments );

        $admin_email = \get_option( 'admin_email' );
        if ( $admin_email && strtolower( $admin_email ) !== strtolower( $booking->email ) ) {
            \wp_mail( $admin_email, '[Admin] ' . $subject, $body, $headers, $attachments );
        }

        foreach ( $attachments as $file ) {
            @unlink( $file );
        }
    }

    /**
     * Create ICS content.
     *
     * @param object $booking Booking record.
     *
     * @return string|null
     */
    protected function build_ics( $booking ) {
        if ( empty( $booking->slot_start ) ) {
            return null;
        }

        $start = gmdate( 'Ymd\THis\Z', strtotime( $booking->slot_start ) );
        $end   = gmdate( 'Ymd\THis\Z', strtotime( $booking->slot_end ?: $booking->slot_start ) );
        $uid   = $booking->uuid . '@silentoakranch.local';
        $desc  = sprintf( 'Booking for %s', $booking->resource );

        $ics  = "BEGIN:VCALENDAR\n";
        $ics .= "VERSION:2.0\n";
        $ics .= "PRODID:-//Silent Oak Ranch//Booking//EN\n";
        $ics .= "BEGIN:VEVENT\n";
        $ics .= 'UID:' . $uid . "\n";
        $ics .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\n";
        $ics .= 'DTSTART:' . $start . "\n";
        $ics .= 'DTEND:' . $end . "\n";
        $ics .= 'SUMMARY:' . $desc . "\n";
        $ics .= 'DESCRIPTION:' . $desc . "\n";
        $ics .= 'LOCATION:Silent Oak Ranch' . "\n";
        $ics .= "END:VEVENT\n";
        $ics .= "END:VCALENDAR\n";

        return $ics;
    }

    /**
     * Create error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status.
     *
     * @return WP_REST_Response
     */
    protected function error_response( $code, $message, $status = 400 ) {
        return new WP_REST_Response(
            array(
                'ok'      => false,
                'code'    => $code,
                'message' => $message,
            ),
            $status
        );
    }
}

