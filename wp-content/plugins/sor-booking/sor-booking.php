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

if ( ! defined( 'SOR_QR_SECRET' ) ) {
    define( 'SOR_QR_SECRET', 'f7d80f14f052d2a122b5d8790c93bc186c8a2ad2d276f1d8cfafbf55bc96d14e' );
}

if ( ! defined( 'SOR_PAYPAL_CLIENT_ID' ) ) {
    define( 'SOR_PAYPAL_CLIENT_ID', 'sb' );
}

if ( ! defined( 'SOR_PAYPAL_SECRET' ) ) {
    define( 'SOR_PAYPAL_SECRET', 'sandbox-secret' );
}

if ( ! defined( 'SOR_API_KEY' ) ) {
    define( 'SOR_API_KEY', 'change-me-api-key' );
}

if ( ! defined( 'SOR_BOOKING_TESTMODE_OPTION' ) ) {
    define( 'SOR_BOOKING_TESTMODE_OPTION', 'sor_booking_testmode' );
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
    $resources = array(
        'solekammer' => array(
            'label'       => __( 'Salt Therapy Room', 'sor-booking' ),
            'price'       => 45.00,
            'description' => __( 'Relaxing salt therapy for horses.', 'sor-booking' ),
        ),
        'waage'      => array(
            'label'       => __( 'Horse Scale', 'sor-booking' ),
            'price'       => 25.00,
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
    $api    = new \SOR\Booking\API( $db, $qr, $paypal );

    $GLOBALS['sor_booking_db']     = $db;
    $GLOBALS['sor_booking_qr']     = $qr;
    $GLOBALS['sor_booking_paypal'] = $paypal;
    $GLOBALS['sor_booking_api']    = $api;

    if ( is_admin() ) {
        new \SOR\Booking\Admin( $db );
    }
}
add_action( 'plugins_loaded', 'sor_booking_init' );

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
            'paypalClient'  => SOR_PAYPAL_CLIENT_ID,
            'testmode'      => (bool) get_option( SOR_BOOKING_TESTMODE_OPTION, false ),
            'i18n'          => array(
                'bookingCreated'  => __( 'Booking created. Please complete payment to confirm.', 'sor-booking' ),
                'paymentComplete' => __( 'Payment completed successfully.', 'sor-booking' ),
                'qrReady'         => __( 'Your QR code is ready for check-in.', 'sor-booking' ),
                'error'           => __( 'An unexpected error occurred. Please try again.', 'sor-booking' ),
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

