<?php
/**
 * PayPal helper for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

class PayPal {
    /**
     * Database handler.
     *
     * @var DB
     */
    protected $db;

    /**
     * Cached access token.
     *
     * @var string|null
     */
    protected $access_token = null;

    /**
     * Access token expiration.
     *
     * @var int|null
     */
    protected $access_token_expires = null;

    /**
     * Constructor.
     *
     * @param DB $db Database handler.
     */
    public function __construct( DB $db ) {
        $this->db = $db;

        \add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_sdk' ) );
    }

    /**
     * Enqueue PayPal SDK when client ID is configured.
     */
    public function enqueue_sdk() {
        $client_id = \sor_booking_get_paypal_client_id();

        if ( ! $client_id ) {
            return;
        }

        $params = array(
            'client-id' => $client_id,
            'currency'  => \apply_filters( 'sor_booking_paypal_currency', 'EUR' ),
            'intent'    => 'CAPTURE',
            'components'=> 'buttons',
        );

        if ( \sor_booking_is_sandbox() ) {
            $params['debug'] = 'true';
        }

        $query = http_build_query( $params );
        $url   = 'https://www.paypal.com/sdk/js?' . $query;

        \wp_enqueue_script( 'paypal-sdk', $url, array(), null, true );
    }

    /**
     * Validate captured amount for a resource.
     *
     * @param string $resource Resource key.
     * @param float  $amount   Captured amount.
     *
     * @return bool
     */
    public function validate_amount( $resource, $amount ) {
        $resources = \sor_booking_get_resources();
        if ( ! isset( $resources[ $resource ] ) ) {
            return false;
        }

        $expected = floatval( $resources[ $resource ]['price'] );
        $amount   = floatval( $amount );

        if ( $expected <= 0 ) {
            return true;
        }

        return abs( $expected - $amount ) < 0.01;
    }

    /**
     * Verify captured PayPal order belongs to the booking and is completed.
     *
     * @param string $order_id PayPal order ID.
     * @param object $booking  Booking record.
     *
     * @return array|\WP_Error
     */
    public function verify_order( $order_id, $booking ) {
        $order_id = \sanitize_text_field( $order_id );
        if ( empty( $order_id ) ) {
            return new \WP_Error( 'invalid_order', \__( 'Missing PayPal order ID.', 'sor-booking' ) );
        }

        $token = $this->get_access_token();
        if ( \is_wp_error( $token ) ) {
            return $token;
        }

        $response = \wp_remote_get(
            \trailingslashit( $this->get_api_base() ) . 'v2/checkout/orders/' . rawurlencode( $order_id ),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 15,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return new \WP_Error( 'paypal_request_failed', $response->get_error_message() );
        }

        $code = \wp_remote_retrieve_response_code( $response );
        $body = json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body ) ) {
            return new \WP_Error( 'paypal_invalid_response', \__( 'Unable to verify PayPal order.', 'sor-booking' ) );
        }

        if ( empty( $body['status'] ) || 'COMPLETED' !== strtoupper( $body['status'] ) ) {
            return new \WP_Error( 'paypal_incomplete', \__( 'PayPal order is not completed.', 'sor-booking' ) );
        }

        $unit        = $body['purchase_units'][0] ?? array();
        $custom_id   = $unit['custom_id'] ?? '';
        $capture_set = $unit['payments']['captures'] ?? array();
        $capture     = $capture_set[0] ?? array();
        $amount      = $capture['amount']['value'] ?? ( $unit['amount']['value'] ?? null );

        if ( ! empty( $custom_id ) && strtolower( $custom_id ) !== strtolower( $booking->uuid ) ) {
            return new \WP_Error( 'paypal_booking_mismatch', \__( 'PayPal order does not match this booking.', 'sor-booking' ) );
        }

        if ( null === $amount ) {
            return new \WP_Error( 'paypal_missing_amount', \__( 'PayPal order amount missing.', 'sor-booking' ) );
        }

        if ( ! $this->validate_amount( $booking->resource, $amount ) ) {
            return new \WP_Error( 'amount_mismatch', \__( 'Payment amount mismatch.', 'sor-booking' ) );
        }

        return array(
            'amount' => floatval( $amount ),
        );
    }

    /**
     * Retrieve access token for API calls.
     *
     * @return string|\WP_Error
     */
    protected function get_access_token() {
        if ( $this->access_token && $this->access_token_expires && time() < $this->access_token_expires ) {
            return $this->access_token;
        }

        $client = \sor_booking_get_paypal_client_id();
        $secret = \sor_booking_get_paypal_secret();

        if ( empty( $client ) || empty( $secret ) ) {
            return new \WP_Error( 'paypal_credentials_missing', \__( 'PayPal credentials not configured.', 'sor-booking' ) );
        }

        $response = \wp_remote_post(
            \trailingslashit( $this->get_api_base() ) . 'v1/oauth2/token',
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $client . ':' . $secret ),
                ),
                'body'    => array(
                    'grant_type' => 'client_credentials',
                ),
                'timeout' => 15,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return new \WP_Error( 'paypal_auth_failed', $response->get_error_message() );
        }

        $code = \wp_remote_retrieve_response_code( $response );
        $body = json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body['access_token'] ) ) {
            return new \WP_Error( 'paypal_auth_failed', \__( 'Unable to authenticate with PayPal.', 'sor-booking' ) );
        }

        $this->access_token         = \sanitize_text_field( $body['access_token'] );
        $this->access_token_expires = time() + \absint( $body['expires_in'] ?? 0 ) - 30;

        return $this->access_token;
    }

    /**
     * Determine PayPal API base URL.
     *
     * @return string
     */
    protected function get_api_base() {
        $sandbox = \apply_filters( 'sor_booking_paypal_sandbox', \sor_booking_is_sandbox() );

        return $sandbox ? 'https://api-m.sandbox.paypal.com/' : 'https://api-m.paypal.com/';
    }
}

