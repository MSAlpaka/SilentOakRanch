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

        $nonce = $request->get_param( 'sor_booking_nonce' );
        if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'sor_booking_form' ) ) {
            return $this->error_response( 'invalid_nonce', \__( 'Security check failed.', 'sor-booking' ), 400 );
        }

        $data = array(
            'resource'   => $resource,
            'name'       => \sanitize_text_field( $request->get_param( 'name' ) ),
            'phone'      => \sanitize_text_field( $request->get_param( 'phone' ) ),
            'email'      => \sanitize_email( $request->get_param( 'email' ) ),
            'horse_name' => \sanitize_text_field( $request->get_param( 'horse_name' ) ),
            'slot_start' => $request->get_param( 'slot_start' ),
            'slot_end'   => $request->get_param( 'slot_end' ),
            'price'      => $definition['price'],
        );

        if ( empty( $data['name'] ) || empty( $data['email'] ) || ! \is_email( $data['email'] ) ) {
            return $this->error_response( 'invalid_fields', \__( 'Please provide valid name and email.', 'sor-booking' ), 400 );
        }

        $requires_slot = in_array( $resource, array( 'solekammer', 'waage' ), true );
        if ( $requires_slot && empty( $data['slot_start'] ) ) {
            return $this->error_response( 'invalid_slot', \__( 'Please choose a valid time slot.', 'sor-booking' ), 400 );
        }

        if ( ! empty( $data['slot_start'] ) && $this->db->has_slot_conflict( $resource, $data['slot_start'], $data['slot_end'] ) ) {
            return $this->error_response( 'slot_unavailable', \__( 'This time slot is no longer available.', 'sor-booking' ), 409 );
        }

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
        $uuid         = \sanitize_text_field( $request->get_param( 'uuid' ) );
        $paypal_order = \sanitize_text_field( $request->get_param( 'orderId' ) );

        if ( empty( $uuid ) || empty( $paypal_order ) ) {
            return $this->error_response( 'invalid_payload', \__( 'Missing payment details.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $uuid );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        $resources  = \sor_booking_get_resources();
        $definition = $resources[ $booking->resource ] ?? null;
        if ( ! $definition ) {
            return $this->error_response( 'unknown_resource', \__( 'Unknown resource configuration.', 'sor-booking' ), 400 );
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
                'uuid'       => $booking->uuid,
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
     * Send confirmation emails after payment.
     *
     * @param object $booking Booking record.
     * @param string $qr_url  QR code image URL.
     */
    protected function send_confirmation_emails( $booking, $qr_url ) {
        if ( empty( $booking ) ) {
            return;
        }

        $to      = \sanitize_email( $booking->email );
        $subject = \__( 'Silent Oak Ranch – Buchungsbestätigung', 'sor-booking' );
        if ( empty( $to ) ) {
            return;
        }

        $resources   = \sor_booking_get_resources();
        $details     = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ] : array();
        $resource    = $details['label'] ?? $booking->resource;
        $date_string = '';

        if ( ! empty( $booking->slot_start ) ) {
            $timestamp   = strtotime( $booking->slot_start );
            $date_format = \get_option( 'date_format', 'd.m.Y' ) . ' ' . \get_option( 'time_format', 'H:i' );
            $date_string = \date_i18n( $date_format, $timestamp );
        }

        $price = '';
        if ( isset( $booking->price ) ) {
            $price = \number_format_i18n( floatval( $booking->price ), 2 ) . ' €';
        }

        $qr_email_url = 'https://silent-oak-ranch.de/wp-json/sor/v1/qr?ref=' . \rawurlencode( $booking->uuid );

        $body  = '<div style="font-family:Helvetica,Arial,sans-serif;color:#2f2a24;">';
        $body .= '<h2 style="color:#385a3f;">' . \esc_html__( 'Vielen Dank für deine Buchung!', 'sor-booking' ) . '</h2>';
        $body .= '<p>' . \esc_html__( 'Hier sind deine Buchungsdetails für Silent Oak Ranch:', 'sor-booking' ) . '</p>';
        $body .= '<ul style="list-style:none;padding:0;">';
        $body .= '<li><strong>' . \esc_html__( 'Angebot:', 'sor-booking' ) . '</strong> ' . \esc_html( $resource ) . '</li>';
        if ( $date_string ) {
            $body .= '<li><strong>' . \esc_html__( 'Termin:', 'sor-booking' ) . '</strong> ' . \esc_html( $date_string ) . '</li>';
        }
        if ( ! empty( $booking->horse_name ) ) {
            $body .= '<li><strong>' . \esc_html__( 'Pferd:', 'sor-booking' ) . '</strong> ' . \esc_html( $booking->horse_name ) . '</li>';
        }
        if ( $price ) {
            $body .= '<li><strong>' . \esc_html__( 'Preis:', 'sor-booking' ) . '</strong> ' . \esc_html( $price ) . '</li>';
        }
        $body .= '</ul>';
        $body .= '<p>' . \esc_html__( 'Dein QR-Ticket findest du hier:', 'sor-booking' ) . '</p>';
        $body .= '<p><img src="' . \esc_url( $qr_email_url ) . '" alt="QR" style="max-width:240px;border:6px solid rgba(56,90,63,0.3);border-radius:12px;" /></p>';
        $body .= '<p><a href="' . \esc_url( $qr_email_url ) . '">' . \esc_html__( 'QR-Ticket online öffnen', 'sor-booking' ) . '</a></p>';
        $body .= '<p>' . \esc_html__( 'Bitte bring das QR-Ticket zu deinem Termin mit.', 'sor-booking' ) . '</p>';
        $body .= '</div>';

        $headers      = array( 'Content-Type: text/html; charset=UTF-8' );
        $attachments  = array();
        $ics          = $this->build_ics( $booking );
        if ( $ics ) {
            $tmp = \wp_tempnam( 'sor-booking' );
            if ( $tmp ) {
                file_put_contents( $tmp, $ics );
                $attachments[] = $tmp;
            }
        }

        \wp_mail( $to, $subject, $body, $headers, $attachments );
        \wp_mail( 'info@silent-oak-ranch.de', $subject, $body, $headers, $attachments );

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

