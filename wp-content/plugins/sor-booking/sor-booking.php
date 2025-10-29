<?php
/**
 * Plugin Name:       Silent Oak Ranch Booking
 * Description:       Booking, payment, QR, and check-in flows for Silent Oak Ranch resources.
 * Version:           0.1.0
 * Author:            Silent Oak Ranch
 * Text Domain:       sor-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SOR_BOOKING_VERSION', '0.1.0' );
define( 'SOR_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOR_BOOKING_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'SOR_BOOKING_TESTMODE_OPTION' ) ) {
    define( 'SOR_BOOKING_TESTMODE_OPTION', 'sor_booking_testmode' );
}

/**
 * Retrieve the value of a legacy configuration constant.
 *
 * @param string $constant Constant name.
 *
 * @return string
 */
function sor_booking_get_legacy_constant_value( $constant ) {
    if ( defined( $constant ) ) {
        $value = constant( $constant );

        if ( is_string( $value ) ) {
            $value = trim( $value );
        }

        if ( '' !== $value && null !== $value ) {
            return (string) $value;
        }
    }

    return '';
}

/**
 * Retrieve default plugin options.
 *
 * @return array
 */
function sor_booking_get_option_defaults() {
    return array(
        'price_solekammer' => 45.0,
        'price_waage'      => 25.0,
        'paypal_mode'      => 'sandbox',
        'paypal_client_id' => sor_booking_get_legacy_constant_value( 'SOR_PAYPAL_CLIENT_ID' ),
        'paypal_secret'    => sor_booking_get_legacy_constant_value( 'SOR_PAYPAL_SECRET' ),
        'qr_secret'        => sor_booking_get_legacy_constant_value( 'SOR_QR_SECRET' ),
        'api_enabled'      => false,
        'api_base_url'     => 'https://app.silent-oak-ranch.de/api',
        'api_key'          => sor_booking_get_legacy_constant_value( 'SOR_API_KEY' ),
        'api_secret'       => '',
    );
}

/**
 * Retrieve merged plugin options.
 *
 * @return array
 */
function sor_booking_get_options() {
    $options  = get_option( 'sor_booking_options', array() );
    $defaults = sor_booking_get_option_defaults();

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    $options = array_map(
        static function ( $value ) {
            return is_string( $value ) ? trim( $value ) : $value;
        },
        $options
    );

    return wp_parse_args( $options, $defaults );
}

/**
 * Retrieve a single option value.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default fallback.
 *
 * @return mixed
 */
function sor_booking_get_option( $key, $default = null ) {
    $options = sor_booking_get_options();

    if ( array_key_exists( $key, $options ) && '' !== $options[ $key ] ) {
        return $options[ $key ];
    }

    if ( null !== $default ) {
        return $default;
    }

    $defaults = sor_booking_get_option_defaults();

    return $defaults[ $key ] ?? null;
}

/**
 * Determine if sandbox mode is enabled.
 *
 * @return bool
 */
function sor_booking_is_sandbox() {
    return 'live' !== sor_booking_get_option( 'paypal_mode', 'sandbox' );
}

/**
 * Retrieve PayPal client ID.
 *
 * @return string
 */
function sor_booking_get_paypal_client_id() {
    return (string) sor_booking_get_option( 'paypal_client_id', '' );
}

/**
 * Retrieve PayPal secret.
 *
 * @return string
 */
function sor_booking_get_paypal_secret() {
    return (string) sor_booking_get_option( 'paypal_secret', '' );
}

/**
 * Retrieve QR secret.
 *
 * @return string
 */
function sor_booking_get_qr_secret() {
    return (string) sor_booking_get_option( 'qr_secret', '' );
}

/**
 * Retrieve API key used for remote requests.
 *
 * @return string
 */
function sor_booking_get_api_key() {
    return (string) sor_booking_get_option( 'api_key', '' );
}

/**
 * Retrieve API secret used for HMAC validation.
 *
 * @return string
 */
function sor_booking_get_api_secret() {
    return (string) sor_booking_get_option( 'api_secret', '' );
}

spl_autoload_register(
    function ( $class ) {
        $prefix   = 'SOR\\Booking\\';
        $base_dir = trailingslashit( SOR_BOOKING_PATH ) . 'includes/';

        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $relative = strtolower( str_replace( '\\', '-', $relative ) );
        $file     = $base_dir . 'class-sor-booking-' . $relative . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
);

/**
 * Retrieve resource definitions.
 *
 * @return array
 */
function sor_booking_get_resources() {
    $options   = sor_booking_get_options();
    $resources = array(
        'solekammer' => array(
            'label'       => __( 'Salt Therapy Room', 'sor-booking' ),
            'price'       => isset( $options['price_solekammer'] ) ? floatval( $options['price_solekammer'] ) : 45.0,
            'description' => __( 'Relaxing salt therapy for horses.', 'sor-booking' ),
        ),
        'waage'      => array(
            'label'       => __( 'Horse Scale', 'sor-booking' ),
            'price'       => isset( $options['price_waage'] ) ? floatval( $options['price_waage'] ) : 25.0,
            'description' => __( 'Precise horse weight measurements.', 'sor-booking' ),
        ),
        'schmied'    => array(
            'label'       => __( 'Blacksmith', 'sor-booking' ),
            'price'       => 0.00,
            'description' => __( 'Blacksmith inquiry – we will contact you to schedule.', 'sor-booking' ),
        ),
    );

    return apply_filters( 'sor_booking_resources', $resources );
}

/**
 * Activate plugin.
 */
function sor_booking_activate() {
    $db = new \SOR\Booking\DB();
    $db->create_tables();
}

register_activation_hook( __FILE__, 'sor_booking_activate' );

/**
 * Bootstrap plugin services.
 */
function sor_booking_init() {
    $db     = new \SOR\Booking\DB();
    $qr     = new \SOR\Booking\QR();
    $paypal = new \SOR\Booking\PayPal( $db );
    require_once SOR_BOOKING_PATH . 'includes/class-sor-booking-api.php';
    require_once SOR_BOOKING_PATH . 'includes/class-sor-booking-contracts-api.php';
    $sync   = new \SOR\Booking\SorBookingSyncService( $db );
    $api    = new \SOR\Booking\API( $db, $qr, $paypal, $sync );
    $contracts_api = new \SOR\Booking\Contracts_API();

    $GLOBALS['sor_booking_db']     = $db;
    $GLOBALS['sor_booking_qr']     = $qr;
    $GLOBALS['sor_booking_paypal'] = $paypal;
    $GLOBALS['sor_booking_api']    = $api;
    $GLOBALS['sor_booking_sync']   = $sync;

    if ( is_admin() ) {
        $GLOBALS['sor_booking_admin'] = new \SOR\Booking\Admin( $db, $sync, $contracts_api );
    }
}
add_action( 'plugins_loaded', 'sor_booking_init' );

/**
 * Render the contracts administration page.
 */
function sor_booking_render_contracts_page() {
    if ( isset( $GLOBALS['sor_booking_admin'] ) && $GLOBALS['sor_booking_admin'] instanceof \SOR\Booking\Admin ) {
        $GLOBALS['sor_booking_admin']->render_contracts_page();
    }
}

/**
 * Register assets.
 */
function sor_booking_enqueue_assets() {
    wp_register_style( 'sor-booking', SOR_BOOKING_URL . 'assets/css/sor-booking.css', array(), SOR_BOOKING_VERSION );
    wp_register_script( 'sor-booking', SOR_BOOKING_URL . 'assets/js/sor-booking.js', array( 'jquery' ), SOR_BOOKING_VERSION, true );

    $nonce = wp_create_nonce( 'wp_rest' );

    wp_localize_script(
        'sor-booking',
        'SORBooking',
        array(
            'restUrl'       => esc_url_raw( rest_url( 'sor/v1/' ) ),
            'nonce'         => $nonce,
            'resources'     => sor_booking_get_resources(),
            'paypalClient'  => sor_booking_get_paypal_client_id(),
            'paypalMode'    => sor_booking_get_option( 'paypal_mode', 'sandbox' ),
            'testmode'      => sor_booking_is_sandbox(),
            'i18n'          => array(
                'bookingCreated'  => __( 'Booking created. Please complete payment to confirm.', 'sor-booking' ),
                'paymentComplete' => __( 'Payment completed successfully.', 'sor-booking' ),
                'qrReady'         => __( 'Your QR code is ready for check-in.', 'sor-booking' ),
                'error'           => __( 'An unexpected error occurred. Please try again.', 'sor-booking' ),
                'creatingBooking' => __( 'Creating your booking…', 'sor-booking' ),
                'requiredName'    => __( 'Please enter your full name.', 'sor-booking' ),
                'requiredEmail'   => __( 'Please provide a valid email address.', 'sor-booking' ),
                'requiredSlot'    => __( 'Please choose a date and time.', 'sor-booking' ),
                'processingPayment' => __( 'Processing your payment…', 'sor-booking' ),
                'paymentError'    => __( 'We could not process your payment. Please try again.', 'sor-booking' ),
            ),
        )
    );

    wp_enqueue_style( 'sor-booking' );
    wp_enqueue_script( 'sor-booking' );
}
add_action( 'wp_enqueue_scripts', 'sor_booking_enqueue_assets' );

/**
 * Render shortcode form.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function sor_booking_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'resource' => 'solekammer',
        ),
        $atts,
        'sor_booking'
    );

    $resource     = sanitize_key( $atts['resource'] );
    $resources    = sor_booking_get_resources();
    $template_map = array(
        'solekammer' => 'form-solekammer.php',
        'waage'      => 'form-waage.php',
        'schmied'    => 'form-schmied.php',
    );

    if ( ! isset( $resources[ $resource ] ) ) {
        return esc_html__( 'Unknown resource.', 'sor-booking' );
    }

    if ( ! isset( $template_map[ $resource ] ) ) {
        return esc_html__( 'Template missing.', 'sor-booking' );
    }

    $template = SOR_BOOKING_PATH . 'templates/' . $template_map[ $resource ];

    if ( ! file_exists( $template ) ) {
        return esc_html__( 'Template missing.', 'sor-booking' );
    }

    ob_start();
    wp_enqueue_style( 'sor-booking' );
    wp_enqueue_script( 'sor-booking' );
    include $template;
    return ob_get_clean();
}
add_shortcode( 'sor_booking', 'sor_booking_shortcode' );

