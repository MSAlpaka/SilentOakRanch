<?php
/**
 * REST API integration for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SorBookingSyncService {
    const CRON_HOOK = 'sor_booking_sync_retry';

    /**
     * Database handler.
     *
     * @var DB
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param DB $db Database handler.
     */
    public function __construct( DB $db ) {
        $this->db = $db;
        $this->db->create_tables();

        \add_action( self::CRON_HOOK, array( $this, 'run_cron' ) );
        \add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
        \add_action( 'admin_init', array( $this, 'maybe_schedule_cron' ) );
    }

    /**
     * Determine whether remote sync is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        $enabled  = (bool) \sor_booking_get_option( 'api_enabled', false );
        $base_url = $this->get_base_url();
        $api_key  = $this->get_api_key();

        if ( ! $enabled ) {
            return false;
        }

        if ( empty( $base_url ) || 0 !== strpos( $base_url, 'https://' ) ) {
            return false;
        }

        if ( empty( $api_key ) ) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve base API URL.
     *
     * @return string
     */
    protected function get_base_url() {
        $url = trim( (string) \sor_booking_get_option( 'api_base_url', 'https://app.silent-oak-ranch.de/api' ) );

        return \untrailingslashit( $url );
    }

    /**
     * Retrieve API key.
     *
     * @return string
     */
    protected function get_api_key() {
        return (string) \sor_booking_get_option( 'api_key', '' );
    }

    /**
     * Handle newly created bookings.
     *
     * @param object $booking Booking object.
     */
    public function handle_booking_created( $booking ) {
        if ( ! $booking ) {
            return;
        }

        if ( ! $this->is_enabled() ) {
            $this->mark_disabled( $booking );
            return;
        }

        $this->set_pending_action( $booking, 'create' );
        $this->attempt_sync( $booking, 'create' );
    }

    /**
     * Handle booking status transitions that require remote sync.
     *
     * @param object $booking Booking object.
     * @param string $status  New status.
     */
    public function handle_status_transition( $booking, $status ) {
        if ( ! $booking ) {
            return;
        }

        $status = \sanitize_key( $status );

        if ( ! in_array( $status, array( 'paid', 'completed', 'cancelled' ), true ) ) {
            $this->mark_synced( $booking, 'synced' );
            return;
        }

        if ( ! $this->is_enabled() ) {
            $this->mark_disabled( $booking );
            return;
        }

        $action = 'status_' . $status;
        $this->set_pending_action( $booking, $action );
        $this->attempt_sync( $booking, $action );
    }

    /**
     * Attempt to sync a booking with the remote API.
     *
     * @param object      $booking Booking record or UUID.
     * @param string|null $action  Action to perform.
     *
     * @return bool|WP_Error
     */
    public function attempt_sync( $booking, $action = null ) {
        $record = is_object( $booking ) ? $booking : $this->db->get_booking( $booking );
        if ( ! $record ) {
            return new WP_Error( 'sor_sync_missing_booking', \__( 'Booking not found for sync.', 'sor-booking' ) );
        }

        $action = $action ? \sanitize_key( $action ) : ( $record->sync_action ?? 'create' );
        $now    = \current_time( 'mysql' );

        $this->db->update_booking_fields(
            $record->uuid,
            array(
                'sync_attempted_at' => $now,
            )
        );
        $record->sync_attempted_at = $now;

        if ( 'create' === $action ) {
            $result = $this->send_create_request( $record );
        } elseif ( 0 === strpos( $action, 'status_' ) ) {
            $status = substr( $action, 7 );
            $result = $this->send_status_request( $record, $status );
        } else {
            $result = true;
        }

        if ( is_wp_error( $result ) ) {
            $this->handle_sync_error( $record, $action, $result );

            return $result;
        }

        $this->mark_synced( $record, 'synced' );

        return true;
    }

    /**
     * Retry sync for a specific booking.
     *
     * @param string $uuid Booking UUID.
     *
     * @return bool|WP_Error
     */
    public function retry_booking_by_uuid( $uuid ) {
        $booking = $this->db->get_booking( $uuid );

        if ( ! $booking ) {
            return new WP_Error( 'sor_sync_missing_booking', \__( 'Booking not found.', 'sor-booking' ), array( 'status' => 404 ) );
        }

        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'sor_sync_disabled', \__( 'API sync is disabled.', 'sor-booking' ), array( 'status' => 400 ) );
        }

        $action = ! empty( $booking->sync_action ) ? $booking->sync_action : 'create';
        $this->set_pending_action( $booking, $action );

        return $this->attempt_sync( $booking, $action );
    }

    /**
     * Mark a booking as synced manually.
     *
     * @param string $uuid Booking UUID.
     *
     * @return bool
     */
    public function mark_booking_manually_synced( $uuid ) {
        $booking = $this->db->get_booking( $uuid );

        if ( ! $booking ) {
            return false;
        }

        $this->db->update_booking_fields(
            $booking->uuid,
            array(
                'synced'          => 1,
                'sync_status'     => 'manual',
                'sync_action'     => '',
                'sync_synced_at'  => \current_time( 'mysql' ),
                'sync_message'    => '',
            )
        );
        $this->db->clear_sync_logs( $booking->uuid );

        return true;
    }

    /**
     * Fetch unsynced bookings with the latest log entry.
     *
     * @param int $limit Maximum number of entries.
     *
     * @return array
     */
    public function get_unsynced_items( $limit = 50 ) {
        $items    = array();
        $bookings = $this->db->get_unsynced_bookings( $limit );

        foreach ( $bookings as $booking ) {
            $items[] = array(
                'booking' => $booking,
                'log'     => $this->db->get_last_sync_log( $booking->uuid ),
            );
        }

        return $items;
    }

    /**
     * Count unsynced bookings.
     *
     * @return int
     */
    public function get_unsynced_count() {
        return $this->db->count_unsynced_bookings();
    }

    /**
     * Process hourly cron job.
     */
    public function run_cron() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $bookings = $this->db->get_unsynced_bookings( 20 );

        foreach ( $bookings as $booking ) {
            $this->attempt_sync( $booking, $booking->sync_action ?? 'create' );
        }
    }

    /**
     * Schedule cron event when missing.
     */
    public function maybe_schedule_cron() {
        if ( \wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        \wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
    }

    /**
     * Prepare booking for sync attempt.
     *
     * @param object $booking Booking object.
     * @param string $action  Action identifier.
     */
    protected function set_pending_action( $booking, $action ) {
        $action = \sanitize_key( $action );

        $this->db->update_booking_fields(
            $booking->uuid,
            array(
                'synced'           => 0,
                'sync_status'      => 'pending',
                'sync_action'      => $action,
                'sync_attempted_at'=> null,
                'sync_message'     => '',
            )
        );

        $booking->synced            = 0;
        $booking->sync_status       = 'pending';
        $booking->sync_action       = $action;
        $booking->sync_attempted_at = null;
        $booking->sync_message      = '';
    }

    /**
     * Send booking creation payload.
     *
     * @param object $booking Booking object.
     *
     * @return array|WP_Error
     */
    protected function send_create_request( $booking ) {
        $payload = array(
            'uuid'       => $booking->uuid,
            'resource'   => $booking->resource,
            'name'       => $booking->name,
            'phone'      => $booking->phone,
            'email'      => $booking->email,
            'horse_name' => $booking->horse_name,
            'slot_start' => $this->format_datetime( $booking->slot_start ),
            'slot_end'   => $this->format_datetime( $booking->slot_end ),
            'price'      => (float) $booking->price,
            'status'     => $booking->status,
            'source'     => 'website',
        );

        return $this->request( '/bookings', 'POST', $payload );
    }

    /**
     * Send booking status update.
     *
     * @param object $booking Booking object.
     * @param string $status  Status value.
     *
     * @return array|bool|WP_Error
     */
    protected function send_status_request( $booking, $status ) {
        $status = \sanitize_key( $status );

        if ( ! in_array( $status, array( 'paid', 'completed', 'cancelled' ), true ) ) {
            return true;
        }

        $payload = array(
            'status' => $status,
        );

        $path = sprintf( '/bookings/%s/status', $booking->uuid );

        return $this->request( $path, 'PATCH', $payload );
    }

    /**
     * Perform remote HTTP request.
     *
     * @param string $path    Endpoint path.
     * @param string $method  HTTP method.
     * @param array  $payload Request payload.
     *
     * @return array|WP_Error
     */
    protected function request( $path, $method, array $payload = array() ) {
        $base = $this->get_base_url();

        if ( empty( $base ) ) {
            return new WP_Error( 'sor_sync_missing_base', \__( 'API base URL not configured.', 'sor-booking' ), array( 'status' => 400 ) );
        }

        $url = \untrailingslashit( $base ) . '/' . ltrim( $path, '/' );

        $args = array(
            'method'      => strtoupper( $method ),
            'timeout'     => 15,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body'        => ! empty( $payload ) ? \wp_json_encode( $payload ) : '{}',
            'data_format' => 'body',
        );

        $response = \wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'sor_sync_request_failed',
                $response->get_error_message(),
                array(
                    'status'      => 0,
                    'status_code' => 0,
                )
            );
        }

        $code = (int) \wp_remote_retrieve_response_code( $response );
        $body = \wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'sor_sync_http_error',
                sprintf( /* translators: %d: HTTP status code */ \__( 'Remote API error (HTTP %d).', 'sor-booking' ), $code ),
                array(
                    'status'      => $code,
                    'status_code' => $code,
                    'body'        => $body,
                )
            );
        }

        $decoded = json_decode( $body, true );

        if ( is_array( $decoded ) && array_key_exists( 'ok', $decoded ) && true !== $decoded['ok'] ) {
            return new WP_Error(
                'sor_sync_remote_error',
                \__( 'Remote API rejected the request.', 'sor-booking' ),
                array(
                    'status'      => $code,
                    'status_code' => $code,
                    'body'        => $body,
                )
            );
        }

        return array(
            'code' => $code,
            'body' => $decoded,
        );
    }

    /**
     * Mark booking as synced.
     *
     * @param object $booking Booking record.
     * @param string $status  Sync status label.
     */
    protected function mark_synced( $booking, $status ) {
        $fields = array(
            'synced'         => 1,
            'sync_status'    => $status,
            'sync_action'    => '',
            'sync_synced_at' => \current_time( 'mysql' ),
            'sync_message'   => '',
        );

        $this->db->update_booking_fields( $booking->uuid, $fields );
        $this->db->clear_sync_logs( $booking->uuid );

        $booking->synced         = 1;
        $booking->sync_status    = $status;
        $booking->sync_action    = '';
        $booking->sync_synced_at = $fields['sync_synced_at'];
        $booking->sync_message   = '';
    }

    /**
     * Handle sync error bookkeeping.
     *
     * @param object   $booking Booking record.
     * @param string   $action  Sync action.
     * @param WP_Error $error   Error instance.
     */
    protected function handle_sync_error( $booking, $action, WP_Error $error ) {
        $data        = $error->get_error_data();
        $status_code = is_array( $data ) && isset( $data['status_code'] ) ? (int) $data['status_code'] : 0;
        $message     = $error->get_error_message();
        $message     = $message ? $message : \__( 'Unknown sync error.', 'sor-booking' );
        $message     = \wp_trim_words( \wp_strip_all_tags( (string) $message ), 30, '…' );

        $this->db->update_booking_fields(
            $booking->uuid,
            array(
                'synced'           => 0,
                'sync_status'      => 'error',
                'sync_message'     => $message,
            )
        );

        $this->db->log_sync_error( $booking->uuid, $action, $status_code, $message );
    }

    /**
     * Format datetime to ISO8601 in site timezone.
     *
     * @param string $datetime Datetime string.
     *
     * @return string|null
     */
    protected function format_datetime( $datetime ) {
        if ( empty( $datetime ) ) {
            return null;
        }

        $formatted = \get_date_from_gmt( $datetime, DATE_ATOM );

        return $formatted ?: null;
    }

    /**
     * Mark booking as not synced when integration disabled.
     *
     * @param object $booking Booking record.
     */
    protected function mark_disabled( $booking ) {
        $this->db->update_booking_fields(
            $booking->uuid,
            array(
                'synced'         => 1,
                'sync_status'    => 'disabled',
                'sync_action'    => '',
                'sync_synced_at' => \current_time( 'mysql' ),
                'sync_message'   => '',
            )
        );
        $this->db->clear_sync_logs( $booking->uuid );
    }
}

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
     * Sync service.
     *
     * @var SorBookingSyncService|null
     */
    protected $sync;

    /**
     * Constructor.
     *
     * @param DB                      $db     Database.
     * @param QR                      $qr     QR helper.
     * @param PayPal                  $paypal PayPal helper.
     * @param SorBookingSyncService   $sync   Sync service.
     */
    public function __construct( DB $db, QR $qr, PayPal $paypal, ?SorBookingSyncService $sync = null ) {
        $this->db     = $db;
        $this->qr     = $qr;
        $this->paypal = $paypal;
        $this->sync   = $sync;

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
                'args'                => array(
                    'uuid'      => array(),
                    'resource'  => array(),
                    'status'    => array(),
                    'date_from' => array(),
                    'date_to'   => array(),
                    'page'      => array(),
                    'per_page'  => array(),
                ),
            )
        );

        \register_rest_route(
            'sor/v1',
            '/admin/sync-retry',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_admin_sync_retry' ),
                'permission_callback' => array( $this, 'validate_admin_request' ),
                'args'                => array(
                    'uuid' => array( 'required' => true ),
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
     * Validate admin requests.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return bool
     */
    public function validate_admin_request( WP_REST_Request $request ) {
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

        if ( $booking && $this->sync ) {
            $this->sync->handle_booking_created( $booking );
            $booking = $this->db->get_booking( $booking->id );
        }

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'booking_id' => (int) $result['id'],
                'uuid'       => $result['uuid'],
                'status'     => $booking ? $booking->status : 'pending',
                'price'      => $definition['price'],
                'resource'   => $resource,
                'synced'     => $booking && isset( $booking->synced ) ? (int) $booking->synced : 0,
                'sync_status'=> $booking->sync_status ?? 'pending',
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
        if ( $this->sync && $booking ) {
            $this->sync->handle_status_transition( $booking, 'paid' );
            $booking = $this->db->get_booking( $booking->id );
        }
        $this->send_confirmation_emails( $booking, $qr_url );

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'uuid'       => $booking->uuid,
                'status'     => 'paid',
                'qr'         => $qr_url,
                'synced'     => isset( $booking->synced ) ? (int) $booking->synced : 0,
                'sync_status'=> $booking->sync_status ?? '',
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
     * Handle admin booking status updates.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_admin_update( WP_REST_Request $request ) {
        $uuid   = \sanitize_text_field( $request->get_param( 'uuid' ) );
        $status = \sanitize_key( $request->get_param( 'status' ) );

        if ( empty( $uuid ) || empty( $status ) ) {
            return $this->error_response( 'invalid_request', \__( 'Missing booking or status.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $uuid );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        $allowed = array( 'pending', 'paid', 'confirmed', 'completed', 'cancelled' );
        if ( ! in_array( $status, $allowed, true ) ) {
            return $this->error_response( 'invalid_status', \__( 'Invalid status.', 'sor-booking' ), 400 );
        }

        $previous_status = $booking->status;

        if ( $status !== $previous_status ) {
            $updated = $this->db->update_status( $booking->uuid, $status );
            if ( ! $updated ) {
                return $this->error_response( 'update_failed', \__( 'Could not update booking.', 'sor-booking' ), 500 );
            }
            $booking = $this->db->get_booking( $booking->uuid );
        }

        if ( in_array( $status, array( 'confirmed', 'cancelled' ), true ) && $previous_status !== $status ) {
            $this->send_status_notification( $booking, $status );
        }

        if ( $this->sync && in_array( $status, array( 'paid', 'completed', 'cancelled' ), true ) ) {
            $this->sync->handle_status_transition( $booking, $status );
            $booking = $this->db->get_booking( $booking->uuid );
        }

        return new WP_REST_Response(
            array(
                'ok'      => true,
                'booking' => $this->format_booking_for_response( $booking ),
            ),
            200
        );
    }

    /**
     * Handle admin booking list retrieval.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_admin_list( WP_REST_Request $request ) {
        $uuid      = \sanitize_text_field( $request->get_param( 'uuid' ) );
        if ( ! empty( $uuid ) ) {
            $booking = $this->db->get_booking( $uuid );
            if ( ! $booking ) {
                return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
            }

            return new WP_REST_Response(
                array(
                    'ok'          => true,
                    'items'       => array( $this->format_booking_for_response( $booking ) ),
                    'total'       => 1,
                    'page'        => 1,
                    'total_pages' => 1,
                    'per_page'    => 1,
                ),
                200
            );
        }

        $resource  = \sanitize_key( $request->get_param( 'resource' ) );
        $status    = \sanitize_key( $request->get_param( 'status' ) );
        $date_from = \sanitize_text_field( $request->get_param( 'date_from' ) );
        $date_to   = \sanitize_text_field( $request->get_param( 'date_to' ) );

        $allowed_resources = array( 'solekammer', 'waage', 'schmied' );
        if ( $resource && ! in_array( $resource, $allowed_resources, true ) ) {
            $resource = '';
        }

        $allowed_statuses = array( 'pending', 'paid', 'confirmed', 'completed', 'cancelled' );
        if ( $status && ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }

        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = (int) $request->get_param( 'per_page' );
        $per_page = $per_page > 0 ? $per_page : 20;
        $per_page = min( 100, max( 1, $per_page ) );

        $filters = array(
            'resource'  => $resource,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        );

        $args = array_merge(
            $filters,
            array(
                'limit'  => $per_page,
                'offset' => ( $page - 1 ) * $per_page,
                'order'  => 'DESC',
            )
        );

        $bookings    = $this->db->get_all_bookings( $args );
        $total       = $this->db->count_bookings( $filters );
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );

        $items = array();
        foreach ( $bookings as $booking ) {
            $items[] = $this->format_booking_for_response( $booking );
        }

        return new WP_REST_Response(
            array(
                'ok'          => true,
                'items'       => $items,
                'total'       => (int) $total,
                'page'        => (int) $page,
                'total_pages' => $total_pages,
                'per_page'    => $per_page,
            ),
            200
        );
    }

    /**
     * Retry syncing a booking via REST.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public function handle_admin_sync_retry( WP_REST_Request $request ) {
        $uuid = \sanitize_text_field( $request->get_param( 'uuid' ) );

        if ( empty( $uuid ) ) {
            return $this->error_response( 'invalid_request', \__( 'Missing booking identifier.', 'sor-booking' ), 400 );
        }

        $booking = $this->db->get_booking( $uuid );
        if ( ! $booking ) {
            return $this->error_response( 'booking_not_found', \__( 'Booking not found.', 'sor-booking' ), 404 );
        }

        if ( ! $this->sync ) {
            return $this->error_response( 'sync_unavailable', \__( 'Sync service is not available.', 'sor-booking' ), 500 );
        }

        $result = $this->sync->retry_booking_by_uuid( $uuid );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

            return $this->error_response( $result->get_error_code(), $result->get_error_message(), $status ?: 400 );
        }

        $booking = $this->db->get_booking( $uuid );

        return new WP_REST_Response(
            array(
                'ok'      => true,
                'booking' => $this->format_booking_for_response( $booking ),
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
        $booking = $this->db->get_booking( $booking->id );

        if ( $this->sync && $booking ) {
            $this->sync->handle_status_transition( $booking, 'completed' );
            $booking = $this->db->get_booking( $booking->id );
        }

        return new WP_REST_Response(
            array(
                'ok'         => true,
                'booking_id' => (int) $booking->id,
                'status'     => 'completed',
                'synced'     => isset( $booking->synced ) ? (int) $booking->synced : 0,
                'sync_status'=> $booking->sync_status ?? '',
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

        $email = $this->build_email_body(
            $booking,
            \esc_html__( 'Vielen Dank für deine Buchung!', 'sor-booking' ),
            true,
            $qr_url,
            true
        );

        if ( empty( $email['body'] ) ) {
            return;
        }

        $subject = \__( 'Silent Oak Ranch – Buchungsbestätigung', 'sor-booking' );
        $this->deliver_booking_email( $booking, $subject, $email['body'], $email['attachments'] );
    }

    /**
     * Send notifications for manual status updates.
     *
     * @param object $booking Booking record.
     * @param string $status  Target status.
     */
    public function send_status_notification( $booking, $status ) {
        if ( empty( $booking ) ) {
            return;
        }

        $status = \sanitize_key( $status );

        if ( 'confirmed' === $status ) {
            $email = $this->build_email_body(
                $booking,
                \esc_html__( 'Deine Buchung wurde bestätigt. Wir freuen uns auf dich!', 'sor-booking' ),
                true,
                '',
                true
            );
            $subject = \__( 'Silent Oak Ranch – Buchung bestätigt', 'sor-booking' );
            $this->deliver_booking_email( $booking, $subject, $email['body'], $email['attachments'] );
        } elseif ( 'cancelled' === $status ) {
            $email = $this->build_email_body(
                $booking,
                \esc_html__( 'Deine Buchung wurde storniert. Wenn du Fragen hast, melde dich gerne bei uns.', 'sor-booking' ),
                false,
                '',
                false
            );
            $subject = \__( 'Silent Oak Ranch – Buchung storniert', 'sor-booking' );
            $this->deliver_booking_email( $booking, $subject, $email['body'], $email['attachments'] );
        }
    }

    /**
     * Build booking email content.
     *
     * @param object $booking      Booking record.
     * @param string $intro        Introductory message.
     * @param bool   $include_qr   Whether to include QR code.
     * @param string $qr_url       Optional QR image URL.
     * @param bool   $include_ics  Whether to attach ICS calendar file.
     *
     * @return array
     */
    protected function build_email_body( $booking, $intro, $include_qr = true, $qr_url = '', $include_ics = true ) {
        $resources = \sor_booking_get_resources();
        $details   = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ] : array();
        $resource  = $details['label'] ?? $booking->resource;

        $date_string = '';
        if ( ! empty( $booking->slot_start ) ) {
            $date_string = $this->format_datetime_for_response( $booking->slot_start );
        }

        $price = '';
        if ( isset( $booking->price ) ) {
            $price = \number_format_i18n( floatval( $booking->price ), 2 ) . ' €';
        }

        $status_label = $this->get_status_label( $booking->status );
        $qr_endpoint  = $qr_url ? $qr_url : \home_url( '/wp-json/sor/v1/qr?ref=' . \rawurlencode( $booking->uuid ) );

        $body  = '<div style="font-family:Helvetica,Arial,sans-serif;color:#2f2a24;">';
        if ( $intro ) {
            $body .= '<h2 style="color:#385a3f;">' . \esc_html( $intro ) . '</h2>';
        }
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
        if ( $status_label ) {
            $body .= '<li><strong>' . \esc_html__( 'Status:', 'sor-booking' ) . '</strong> ' . \esc_html( $status_label ) . '</li>';
        }
        $body .= '</ul>';

        if ( $include_qr ) {
            $body .= '<p>' . \esc_html__( 'Dein QR-Ticket findest du hier:', 'sor-booking' ) . '</p>';
            $body .= '<p><img src="' . \esc_url( $qr_endpoint ) . '" alt="QR" style="max-width:240px;border:6px solid rgba(56,90,63,0.3);border-radius:12px;" /></p>';
            $body .= '<p><a href="' . \esc_url( $qr_endpoint ) . '">' . \esc_html__( 'QR-Ticket online öffnen', 'sor-booking' ) . '</a></p>';
            $body .= '<p>' . \esc_html__( 'Bitte bring das QR-Ticket zu deinem Termin mit.', 'sor-booking' ) . '</p>';
        } else {
            $body .= '<p>' . \esc_html__( 'Bei Fragen melde dich bitte direkt bei uns.', 'sor-booking' ) . '</p>';
        }

        $body .= '</div>';

        $attachments = array();
        if ( $include_ics ) {
            $ics = $this->build_ics( $booking );
            if ( $ics ) {
                $tmp = \wp_tempnam( 'sor-booking' );
                if ( $tmp ) {
                    file_put_contents( $tmp, $ics );
                    $attachments[] = $tmp;
                }
            }
        }

        return array(
            'body'        => $body,
            'attachments' => $attachments,
        );
    }

    /**
     * Deliver booking email and clean attachments.
     *
     * @param object $booking      Booking record.
     * @param string $subject      Email subject.
     * @param string $body         Email body HTML.
     * @param array  $attachments  File attachments.
     */
    protected function deliver_booking_email( $booking, $subject, $body, array $attachments = array() ) {
        $to = \sanitize_email( $booking->email );
        if ( empty( $to ) ) {
            foreach ( $attachments as $file ) {
                @unlink( $file );
            }
            return;
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        \wp_mail( $to, $subject, $body, $headers, $attachments );
        \wp_mail( 'info@silent-oak-ranch.de', $subject, $body, $headers, $attachments );

        foreach ( $attachments as $file ) {
            @unlink( $file );
        }
    }

    /**
     * Format booking for REST responses.
     *
     * @param object $booking Booking record.
     *
     * @return array
     */
    protected function format_booking_for_response( $booking ) {
        $resources      = \sor_booking_get_resources();
        $resource_label = isset( $resources[ $booking->resource ]['label'] ) ? $resources[ $booking->resource ]['label'] : $booking->resource;

        return array(
            'id'               => (int) $booking->id,
            'uuid'             => $booking->uuid,
            'resource'         => $booking->resource,
            'resource_label'   => $resource_label,
            'name'             => $booking->name,
            'phone'            => $booking->phone,
            'email'            => $booking->email,
            'horse_name'       => $booking->horse_name,
            'slot_start'       => $booking->slot_start,
            'slot_end'         => $booking->slot_end,
            'slot_human'       => $this->format_slot_for_response( $booking ),
            'price'            => (float) $booking->price,
            'price_formatted'  => \number_format_i18n( (float) $booking->price, 2 ),
            'status'           => $booking->status,
            'status_label'     => $this->get_status_label( $booking->status ),
            'payment_ref'      => $booking->payment_ref,
            'created_at'       => $booking->created_at,
            'updated_at'       => $booking->updated_at,
            'created_human'    => $this->format_datetime_for_response( $booking->created_at ),
            'updated_human'    => $this->format_datetime_for_response( $booking->updated_at ),
            'synced'           => isset( $booking->synced ) ? (int) $booking->synced : 0,
            'sync_status'      => $booking->sync_status ?? '',
            'sync_action'      => $booking->sync_action ?? '',
            'sync_message'     => $booking->sync_message ?? '',
            'sync_attempted_at'=> $booking->sync_attempted_at ?? '',
            'sync_attempted_human' => isset( $booking->sync_attempted_at ) ? $this->format_datetime_for_response( $booking->sync_attempted_at ) : '',
            'sync_synced_at'   => $booking->sync_synced_at ?? '',
            'sync_synced_human'=> isset( $booking->sync_synced_at ) ? $this->format_datetime_for_response( $booking->sync_synced_at ) : '',
        );
    }

    /**
     * Format slot for responses.
     *
     * @param object $booking Booking record.
     *
     * @return string
     */
    protected function format_slot_for_response( $booking ) {
        if ( empty( $booking->slot_start ) ) {
            return '';
        }

        $start = $this->format_datetime_for_response( $booking->slot_start );
        $end   = $booking->slot_end ? $this->format_datetime_for_response( $booking->slot_end ) : '';

        if ( $end && $end !== $start ) {
            return $start . ' – ' . $end;
        }

        return $start;
    }

    /**
     * Format datetime strings for responses.
     *
     * @param string $datetime Datetime string.
     *
     * @return string
     */
    protected function format_datetime_for_response( $datetime ) {
        if ( empty( $datetime ) ) {
            return '';
        }

        $timestamp = strtotime( $datetime );
        if ( ! $timestamp ) {
            return '';
        }

        $format = \get_option( 'date_format', 'd.m.Y' ) . ' ' . \get_option( 'time_format', 'H:i' );

        return \date_i18n( $format, $timestamp );
    }

    /**
     * Retrieve human readable status label.
     *
     * @param string $status Status key.
     *
     * @return string
     */
    protected function get_status_label( $status ) {
        $statuses = array(
            'pending'   => \__( 'Ausstehend', 'sor-booking' ),
            'paid'      => \__( 'Bezahlt', 'sor-booking' ),
            'confirmed' => \__( 'Bestätigt', 'sor-booking' ),
            'completed' => \__( 'Abgeschlossen', 'sor-booking' ),
            'cancelled' => \__( 'Storniert', 'sor-booking' ),
        );

        return $statuses[ $status ] ?? $status;
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

