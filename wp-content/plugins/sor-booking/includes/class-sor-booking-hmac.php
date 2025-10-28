<?php
/**
 * HMAC helper for Silent Oak Ranch Booking integration.
 */

namespace SOR\Booking;

defined( 'ABSPATH' ) || exit;

class HMAC {
    /**
     * API key used for signing.
     *
     * @var string
     */
    protected $key;

    /**
     * API secret used for signing.
     *
     * @var string
     */
    protected $secret;

    /**
     * Constructor.
     *
     * @param string $key    API key.
     * @param string $secret API secret.
     */
    public function __construct( $key, $secret ) {
        $this->key    = (string) $key;
        $this->secret = (string) $secret;
    }

    /**
     * Build request headers for a signed request.
     *
     * @param string $method HTTP method.
     * @param string $path   Request path.
     * @param string $body   Request body.
     *
     * @return array
     */
    public function build_headers( $method, $path, $body ) {
        $timestamp = gmdate( 'c' );
        $method    = strtoupper( (string) $method );
        $path      = '/' . ltrim( (string) $path, '/' );
        $body      = (string) $body;

        $payload   = $method . "\n" . $path . "\n" . $timestamp . "\n" . $body;
        $signature = hash_hmac( 'sha256', $payload, $this->secret );

        return array(
            'X-SOR-Key'       => $this->key,
            'X-SOR-Date'      => $timestamp,
            'X-SOR-Signature' => $signature,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        );
    }
}
