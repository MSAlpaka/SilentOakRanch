<?php
/**
 * QR helper for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

use WP_Error;

class QR {
    const SCHEME  = 'SOR';
    const VERSION = 'v1';
    const TTL     = \DAY_IN_SECONDS;

    /**
     * Generate QR payload string.
     *
     * @param string $uuid Booking UUID.
     *
     * @return string
     */
    public function generate_payload( $uuid ) {
        $uuid = \sanitize_text_field( $uuid );
        $ts   = time();
        $sig  = $this->sign( $uuid, $ts );

        return sprintf( '%s|%s|%s|%d|%s', self::SCHEME, self::VERSION, $uuid, $ts, $sig );
    }

    /**
     * Sign payload.
     *
     * @param string $uuid UUID.
     * @param int    $ts   Timestamp.
     *
     * @return string
     */
    public function sign( $uuid, $ts ) {
        $data = $uuid . '|' . intval( $ts );

        return hash_hmac( 'sha256', $data, \SOR_QR_SECRET );
    }

    /**
     * Render QR image URL using Google Charts as fallback.
     *
     * @param string  $payload Payload string.
     * @param integer $size    Image size.
     *
     * @return string
     */
    public function render_img( $payload, $size = 256 ) {
        $payload = rawurlencode( $payload );
        $size    = absint( $size );
        $size    = $size > 0 ? $size : 256;

        return sprintf( 'https://chart.googleapis.com/chart?chs=%1$dx%1$d&cht=qr&chl=%2$s', $size, $payload );
    }

    /**
     * Verify payload integrity and freshness.
     *
     * @param string $payload Payload string.
     *
     * @return array|WP_Error
     */
    public function verify_payload( $payload ) {
        $parts = explode( '|', $payload );
        if ( count( $parts ) !== 5 ) {
            return new WP_Error( 'invalid_qr', \__( 'Malformed QR payload.', 'sor-booking' ) );
        }

        list( $scheme, $version, $uuid, $ts, $sig ) = $parts;

        if ( self::SCHEME !== $scheme || self::VERSION !== $version ) {
            return new WP_Error( 'invalid_qr', \__( 'Unsupported QR payload.', 'sor-booking' ) );
        }

        if ( ! $uuid || ! $ts || ! $sig ) {
            return new WP_Error( 'invalid_qr', \__( 'Incomplete QR data.', 'sor-booking' ) );
        }

        $expected_sig = $this->sign( $uuid, $ts );
        if ( ! hash_equals( $expected_sig, $sig ) ) {
            return new WP_Error( 'invalid_signature', \__( 'Invalid QR signature.', 'sor-booking' ) );
        }

        $timestamp = intval( $ts );
        if ( time() - $timestamp > self::TTL ) {
            return new WP_Error( 'qr_expired', \__( 'QR code expired.', 'sor-booking' ) );
        }

        return array(
            'uuid' => \sanitize_text_field( $uuid ),
            'ts'   => $timestamp,
        );
    }
}

