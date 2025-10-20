<?php
/**
 * Admin UI for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

class Admin {
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

        \add_action( 'admin_menu', array( $this, 'register_menu' ) );
        \add_action( 'admin_post_sor_booking_update_status', array( $this, 'handle_status_update' ) );
        \add_action( 'admin_post_sor_booking_export', array( $this, 'handle_export' ) );
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        \register_setting( 'sor_booking_settings', \SOR_BOOKING_TESTMODE_OPTION );
    }

    /**
     * Register admin menu.
     */
    public function register_menu() {
        \add_menu_page(
            \__( 'Solekammer Bookings', 'sor-booking' ),
            \__( 'Solekammer-Buchungen', 'sor-booking' ),
            'manage_options',
            'sor-booking',
            array( $this, 'render_admin_page' ),
            'dashicons-calendar-alt',
            26
        );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $bookings = $this->db->get_all_bookings();
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Silent Oak Ranch Bookings', 'sor-booking' ); ?></h1>

            <form method="post" action="options.php" style="margin-bottom:20px;">
                <?php
                \settings_fields( 'sor_booking_settings' );
                \do_settings_sections( 'sor_booking_settings' );
                ?>
                <label>
                    <input type="checkbox" name="<?php echo \esc_attr( \SOR_BOOKING_TESTMODE_OPTION ); ?>" value="1" <?php \checked( \get_option( \SOR_BOOKING_TESTMODE_OPTION, false ) ); ?> />
                    <?php \esc_html_e( 'Enable test (sandbox) mode for PayPal.', 'sor-booking' ); ?>
                </label>
                <?php \submit_button( \__( 'Save Settings', 'sor-booking' ) ); ?>
            </form>

            <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
                <?php \wp_nonce_field( 'sor_booking_export', 'sor_booking_export_nonce' ); ?>
                <input type="hidden" name="action" value="sor_booking_export" />
                <?php \submit_button( \__( 'Export CSV', 'sor-booking' ), 'secondary' ); ?>
            </form>

            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php \esc_html_e( 'ID', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'UUID', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Resource', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Name', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Contact', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Horse', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Slot', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Price', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Status', 'sor-booking' ); ?></th>
                    <th><?php \esc_html_e( 'Actions', 'sor-booking' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( $bookings ) : ?>
                    <?php foreach ( $bookings as $booking ) : ?>
                        <tr>
                            <td><?php echo \esc_html( $booking->id ); ?></td>
                            <td><?php echo \esc_html( $booking->uuid ); ?></td>
                            <td><?php echo \esc_html( $booking->resource ); ?></td>
                            <td><?php echo \esc_html( $booking->name ); ?></td>
                            <td>
                                <?php echo \esc_html( $booking->email ); ?><br />
                                <?php echo \esc_html( $booking->phone ); ?>
                            </td>
                            <td><?php echo \esc_html( $booking->horse_name ); ?></td>
                            <td>
                                <?php echo \esc_html( $booking->slot_start ); ?><br />
                                <?php echo \esc_html( $booking->slot_end ); ?>
                            </td>
                            <td><?php echo \esc_html( \number_format_i18n( $booking->price, 2 ) ); ?></td>
                            <td><?php echo \esc_html( ucfirst( $booking->status ) ); ?></td>
                            <td>
                                <?php $this->render_status_form( $booking ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="10"><?php \esc_html_e( 'No bookings yet.', 'sor-booking' ); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render inline status form.
     *
     * @param object $booking Booking record.
     */
    protected function render_status_form( $booking ) {
        $statuses = array( 'pending', 'paid', 'confirmed', 'completed', 'cancelled' );
        ?>
        <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
            <?php \wp_nonce_field( 'sor_booking_update_status_' . $booking->id, 'sor_booking_status_nonce' ); ?>
            <input type="hidden" name="action" value="sor_booking_update_status" />
            <input type="hidden" name="booking_id" value="<?php echo \esc_attr( $booking->id ); ?>" />
            <select name="status">
                <?php foreach ( $statuses as $status ) : ?>
                    <option value="<?php echo \esc_attr( $status ); ?>" <?php \selected( $status, $booking->status ); ?>><?php echo \esc_html( ucfirst( $status ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php \submit_button( \__( 'Update', 'sor-booking' ), 'small', 'submit', false ); ?>
        </form>
        <?php
    }

    /**
     * Handle status update submissions.
     */
    public function handle_status_update() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'Permission denied.', 'sor-booking' ) );
        }

        $booking_id = \absint( $_POST['booking_id'] ?? 0 );
        $status     = \sanitize_text_field( $_POST['status'] ?? '' );

        if ( ! \wp_verify_nonce( $_POST['sor_booking_status_nonce'] ?? '', 'sor_booking_update_status_' . $booking_id ) ) {
            \wp_die( \esc_html__( 'Invalid nonce.', 'sor-booking' ) );
        }

        if ( $booking_id && $status ) {
            $this->db->update_status( $booking_id, $status );
        }

        \wp_safe_redirect( \wp_get_referer() ? \wp_get_referer() : \admin_url( 'admin.php?page=sor-booking' ) );
        exit;
    }

    /**
     * Handle CSV export.
     */
    public function handle_export() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'Permission denied.', 'sor-booking' ) );
        }

        if ( ! \wp_verify_nonce( $_POST['sor_booking_export_nonce'] ?? '', 'sor_booking_export' ) ) {
            \wp_die( \esc_html__( 'Invalid nonce.', 'sor-booking' ) );
        }

        $bookings = $this->db->get_all_bookings( array( 'limit' => 0 ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="sor-bookings.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'UUID', 'Resource', 'Name', 'Email', 'Phone', 'Horse', 'Slot Start', 'Slot End', 'Price', 'Status', 'Payment Ref', 'Created', 'Updated' ) );

        if ( $bookings ) {
            foreach ( $bookings as $booking ) {
                fputcsv( $output, array(
                    $booking->id,
                    $booking->uuid,
                    $booking->resource,
                    $booking->name,
                    $booking->email,
                    $booking->phone,
                    $booking->horse_name,
                    $booking->slot_start,
                    $booking->slot_end,
                    $booking->price,
                    $booking->status,
                    $booking->payment_ref,
                    $booking->created_at,
                    $booking->updated_at,
                ) );
            }
        }

        fclose( $output );
        exit;
    }
}

