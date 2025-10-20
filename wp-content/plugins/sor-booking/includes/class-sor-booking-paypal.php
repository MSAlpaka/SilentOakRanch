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
        if ( ! \SOR_PAYPAL_CLIENT_ID ) {
            return;
        }

        $params = array(
            'client-id' => \SOR_PAYPAL_CLIENT_ID,
            'currency'  => \apply_filters( 'sor_booking_paypal_currency', 'EUR' ),
            'intent'    => 'CAPTURE',
            'components'=> 'buttons',
        );

        if ( \get_option( \SOR_BOOKING_TESTMODE_OPTION, false ) ) {
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
}

