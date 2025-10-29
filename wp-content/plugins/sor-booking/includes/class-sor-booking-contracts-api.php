<?php
/**
 * Contracts API client for Silent Oak Ranch Booking admin UI.
 */

namespace SOR\Booking;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class Contracts_API {
    /**
     * Determine whether remote API is configured.
     *
     * @return bool
     */
    public function is_available() {
        $enabled    = (bool) \sor_booking_get_option( 'api_enabled', false );
        $base_url   = $this->get_base_url();
        $api_key    = \sor_booking_get_api_key();
        $api_secret = \sor_booking_get_api_secret();

        if ( ! $enabled || empty( $base_url ) || empty( $api_key ) || empty( $api_secret ) ) {
            return false;
        }

        return true;
    }

    /**
     * Fetch contract dashboard data.
     *
     * @param array $args Optional query arguments.
     *
     * @return array|WP_Error
     */
    public function get_contracts( array $args = array() ) {
        $query = \wp_parse_args(
            $args,
            array(
                'limit' => 50,
            )
        );

        return $this->get( '/api/wp/contracts', $query );
    }

    /**
     * Trigger contract verification.
     *
     * @param string $uuid Contract UUID.
     *
     * @return array|WP_Error
     */
    public function verify_contract( $uuid ) {
        $contract_uuid = \sanitize_text_field( (string) $uuid );

        if ( '' === $contract_uuid ) {
            return new WP_Error( 'sor_contracts_missing_uuid', __( 'Ungültige Vertrags-ID.', 'sor-booking' ) );
        }

        return $this->get( sprintf( '/api/contracts/%s/verify', rawurlencode( $contract_uuid ) ) );
    }

    /**
     * Fetch audit trail for the given contract.
     *
     * @param string $uuid Contract UUID.
     *
     * @return array|WP_Error
     */
    public function get_audit( $uuid ) {
        $contract_uuid = \sanitize_text_field( (string) $uuid );

        if ( '' === $contract_uuid ) {
            return new WP_Error( 'sor_contracts_missing_uuid', __( 'Ungültige Vertrags-ID.', 'sor-booking' ) );
        }

        return $this->get( sprintf( '/api/audit/contract/%s', rawurlencode( $contract_uuid ) ) );
    }

    /**
     * Perform signed GET request.
     *
     * @param string $path  Relative path.
     * @param array  $query Optional query string parameters.
     *
     * @return array|WP_Error
     */
    protected function get( $path, array $query = array() ) {
        if ( ! $this->is_available() ) {
            return new WP_Error( 'sor_contracts_api_disabled', __( 'API-Synchronisierung ist deaktiviert.', 'sor-booking' ) );
        }

        $base = $this->get_base_url();
        $path = '/' . ltrim( $path, '/' );
        $url  = \untrailingslashit( $base ) . $path;

        if ( ! empty( $query ) ) {
            $url = \add_query_arg( $query, $url );
        }

        $sign_path = \wp_parse_url( $url, PHP_URL_PATH );
        if ( empty( $sign_path ) ) {
            $sign_path = $path;
        }

        $signer  = new HMAC( \sor_booking_get_api_key(), \sor_booking_get_api_secret() );
        $headers = $signer->build_headers( 'GET', $sign_path, '' );

        $response = \wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => $headers,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return new WP_Error(
                'sor_contracts_http_error',
                $response->get_error_message()
            );
        }

        $code = (int) \wp_remote_retrieve_response_code( $response );
        $body = \wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'sor_contracts_remote_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    \__( 'API-Abruf fehlgeschlagen (HTTP %d).', 'sor-booking' ),
                    $code
                ),
                array(
                    'code' => $code,
                    'body' => $body,
                )
            );
        }

        $decoded = json_decode( $body, true );
        if ( null === $decoded ) {
            return new WP_Error( 'sor_contracts_invalid_json', \__( 'Antwort der API konnte nicht gelesen werden.', 'sor-booking' ) );
        }

        return $decoded;
    }

    /**
     * Retrieve configured base URL.
     *
     * @return string
     */
    protected function get_base_url() {
        $url = trim( (string) \sor_booking_get_option( 'api_base_url', '' ) );

        return \untrailingslashit( $url );
    }
}
