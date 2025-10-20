<?php
/**
 * Admin UI for Silent Oak Ranch Booking.
 */

namespace SOR\Booking;

class Admin {
    const MENU_SLUG = 'sor-booking';
    const SETTINGS_GROUP = 'sor_booking_options';

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
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        \add_action( 'admin_post_sor_booking_export', array( $this, 'handle_export' ) );
    }

    /**
     * Register menu structure.
     */
    public function register_menu() {
        \add_menu_page(
            \__( 'Ranch Buchungen', 'sor-booking' ),
            \__( 'Ranch Buchungen', 'sor-booking' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_bookings_page' ),
            'dashicons-calendar-alt',
            26
        );

        \add_submenu_page(
            self::MENU_SLUG,
            \__( 'Alle Buchungen', 'sor-booking' ),
            \__( 'Alle Buchungen', 'sor-booking' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_bookings_page' )
        );

        \add_submenu_page(
            self::MENU_SLUG,
            \__( 'Einstellungen', 'sor-booking' ),
            \__( 'Einstellungen', 'sor-booking' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array( $this, 'render_settings_page' )
        );

        \add_submenu_page(
            self::MENU_SLUG,
            \__( 'CSV-Export', 'sor-booking' ),
            \__( 'CSV-Export', 'sor-booking' ),
            'manage_options',
            self::MENU_SLUG . '-export',
            array( $this, 'render_export_page' )
        );
    }

    /**
     * Register settings using the WP Settings API.
     */
    public function register_settings() {
        \register_setting(
            self::SETTINGS_GROUP,
            'sor_booking_options',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_options' ),
                'default'           => array(),
            )
        );

        \add_settings_section(
            'sor_booking_prices',
            \__( 'Preise & Ressourcen', 'sor-booking' ),
            array( $this, 'render_settings_section' ),
            self::SETTINGS_GROUP
        );

        \add_settings_field(
            'price_solekammer',
            \__( 'Preis Solekammer (€)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_prices',
            array(
                'key'         => 'price_solekammer',
                'min'         => '0',
                'step'        => '0.01',
                'description' => \__( 'Preis für Solekammer pro Termin.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'duration_solekammer',
            \__( 'Dauer Solekammer (Minuten)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_prices',
            array(
                'key'         => 'duration_solekammer',
                'min'         => '0',
                'step'        => '1',
                'class'       => 'small-text',
                'description' => \__( 'Standarddauer für Solekammer-Termine in Minuten.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'price_waage',
            \__( 'Preis Waage (€)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_prices',
            array(
                'key'         => 'price_waage',
                'min'         => '0',
                'step'        => '0.01',
                'description' => \__( 'Preis für die Pferdewaage pro Termin.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'duration_waage',
            \__( 'Dauer Waage (Minuten)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_prices',
            array(
                'key'         => 'duration_waage',
                'min'         => '0',
                'step'        => '1',
                'class'       => 'small-text',
                'description' => \__( 'Standarddauer für Wiegetermine in Minuten.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'duration_schmied',
            \__( 'Dauer Schmied (Minuten)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_prices',
            array(
                'key'         => 'duration_schmied',
                'min'         => '0',
                'step'        => '1',
                'class'       => 'small-text',
                'description' => \__( 'Geplante Dauer für Schmiedetermine in Minuten (optional).', 'sor-booking' ),
            )
        );

        \add_settings_section(
            'sor_booking_paypal',
            \__( 'PayPal Einstellungen', 'sor-booking' ),
            '__return_false',
            self::SETTINGS_GROUP
        );

        \add_settings_field(
            'paypal_mode',
            \__( 'PayPal Modus', 'sor-booking' ),
            array( $this, 'render_paypal_mode_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_paypal',
            array(
                'key'         => 'paypal_mode',
                'description' => \__( 'Sandbox für Tests, Live für produktive Zahlungen.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'paypal_client_id',
            \__( 'PayPal Client ID', 'sor-booking' ),
            array( $this, 'render_text_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_paypal',
            array(
                'key'         => 'paypal_client_id',
                'description' => \__( 'REST-Client-ID aus dem PayPal-Dashboard.', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'paypal_secret',
            \__( 'PayPal Secret', 'sor-booking' ),
            array( $this, 'render_password_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_paypal',
            array(
                'key'         => 'paypal_secret',
                'description' => \__( 'REST-Secret aus dem PayPal-Dashboard.', 'sor-booking' ),
            )
        );

        \add_settings_section(
            'sor_booking_qr',
            \__( 'QR-Code Signatur', 'sor-booking' ),
            '__return_false',
            self::SETTINGS_GROUP
        );

        \add_settings_field(
            'qr_secret',
            \__( 'QR Secret', 'sor-booking' ),
            array( $this, 'render_text_field' ),
            self::SETTINGS_GROUP,
            'sor_booking_qr',
            array(
                'key'         => 'qr_secret',
                'description' => \__( 'Geheimer Schlüssel zur Signierung der QR-Codes.', 'sor-booking' ),
                'class'       => 'regular-text code',
            )
        );
    }

    /**
     * Enqueue assets for admin screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( ! $this->is_plugin_screen( $hook ) ) {
            return;
        }

        \wp_enqueue_style( 'sor-booking-admin', SOR_BOOKING_URL . 'assets/css/sor-booking.css', array(), SOR_BOOKING_VERSION );
        \wp_enqueue_script( 'sor-booking-admin', SOR_BOOKING_URL . 'assets/js/sor-booking-admin.js', array( 'jquery' ), SOR_BOOKING_VERSION, true );

        \wp_localize_script(
            'sor-booking-admin',
            'SORBookingAdmin',
            array(
                'restUrl'      => \esc_url_raw( \rest_url( 'sor/v1/' ) ),
                'nonce'        => \wp_create_nonce( 'wp_rest' ),
                'qrEndpoint'   => \esc_url_raw( \rest_url( 'sor/v1/qr?ref=' ) ),
                'statusLabels' => $this->get_status_labels(),
                'i18n'         => array(
                    'detailsTitle'       => \__( 'Booking details', 'sor-booking' ),
                    'close'              => \__( 'Close', 'sor-booking' ),
                    'statusUpdated'      => \__( 'Booking updated successfully.', 'sor-booking' ),
                    'statusUpdateFailed' => \__( 'Could not update the booking status.', 'sor-booking' ),
                    'confirmCancel'      => \__( 'Mark this booking as cancelled?', 'sor-booking' ),
                    'statusLabel'        => \__( 'Status', 'sor-booking' ),
                    'updatedLabel'       => \__( 'Updated', 'sor-booking' ),
                    'fieldResource'      => \__( 'Resource', 'sor-booking' ),
                    'fieldName'          => \__( 'Name', 'sor-booking' ),
                    'fieldEmail'         => \__( 'Email', 'sor-booking' ),
                    'fieldPhone'         => \__( 'Phone', 'sor-booking' ),
                    'fieldHorse'         => \__( 'Horse', 'sor-booking' ),
                    'fieldStatus'        => \__( 'Status', 'sor-booking' ),
                    'fieldSlot'          => \__( 'Date/Time', 'sor-booking' ),
                    'fieldPrice'         => \__( 'Price (€)', 'sor-booking' ),
                    'fieldPaymentRef'    => \__( 'Payment Ref', 'sor-booking' ),
                    'fieldCreated'       => \__( 'Created', 'sor-booking' ),
                    'fieldUpdated'       => \__( 'Updated', 'sor-booking' ),
                    'fieldUuid'          => \__( 'Booking reference', 'sor-booking' ),
                ),
            )
        );
    }

    /**
     * Render bookings list view.
     */
    public function render_bookings_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $filters   = $this->get_filters();
        $query     = array_merge( $filters, array(
            'limit'    => $filters['per_page'],
            'per_page' => $filters['per_page'],
        ) );
        $bookings  = $this->db->get_all_bookings( $query );
        $total     = max( 0, (int) $this->db->count_bookings( $filters ) );
        $total_pag = max( 1, (int) ceil( $total / max( 1, $filters['per_page'] ) ) );
        $statuses  = $this->get_status_labels();
        $csv_url   = \add_query_arg(
            array_merge( $this->build_filter_query( $filters ), array( 'page' => self::MENU_SLUG . '-export' ) ),
            \admin_url( 'admin.php' )
        );

        $base_url = \add_query_arg( $this->build_filter_query( $filters ), \admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
        $pagination = \paginate_links(
            array(
                'base'      => $base_url . '%_%',
                'format'    => '&paged=%#%',
                'total'     => $total_pag,
                'current'   => $filters['paged'],
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            )
        );
        ?>
        <div class="wrap sor-booking-admin">
            <h1 class="wp-heading-inline"><?php \esc_html_e( 'Ranch Buchungen', 'sor-booking' ); ?></h1>
            <a class="page-title-action" href="<?php echo \esc_url( $csv_url ); ?>"><?php \esc_html_e( 'CSV-Export', 'sor-booking' ); ?></a>
            <hr class="wp-header-end" />

            <form method="get" class="sor-booking-filter-form">
                <input type="hidden" name="page" value="<?php echo \esc_attr( self::MENU_SLUG ); ?>" />
                <?php $this->render_filter_inputs( $filters, 'list' ); ?>
                <div class="sor-booking-filter-actions">
                    <?php \submit_button( \__( 'Filter anwenden', 'sor-booking' ), 'primary', '', false ); ?>
                    <a class="button" href="<?php echo \esc_url( \admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>"><?php \esc_html_e( 'Zurücksetzen', 'sor-booking' ); ?></a>
                </div>
            </form>

            <div id="sor-booking-admin-notices"></div>

            <div class="sor-booking-summary">
                <p><?php printf( \esc_html__( '%d bookings found.', 'sor-booking' ), $total ); ?></p>
            </div>

            <div class="sor-booking-table-wrapper">
                <table class="wp-list-table widefat fixed striped table-view-list sor-booking-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php \esc_html_e( 'ID', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Resource', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Name', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Phone', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Email', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Horse', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Date/Time', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Price (€)', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Status', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Payment Ref', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Created', 'sor-booking' ); ?></th>
                            <th scope="col"><?php \esc_html_e( 'Updated', 'sor-booking' ); ?></th>
                            <th scope="col" class="column-actions"><?php \esc_html_e( 'Actions', 'sor-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $bookings ) : ?>
                        <?php foreach ( $bookings as $booking ) : ?>
                            <?php $data = $this->prepare_booking_data( $booking ); ?>
                            <tr class="sor-booking-row" data-booking="<?php echo \esc_attr( wp_json_encode( $data ) ); ?>" data-uuid="<?php echo \esc_attr( $data['uuid'] ); ?>">
                                <td data-label="<?php \esc_attr_e( 'ID', 'sor-booking' ); ?>"><?php echo \esc_html( $data['id'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Resource', 'sor-booking' ); ?>"><?php echo \esc_html( $data['resource_label'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Name', 'sor-booking' ); ?>"><?php echo \esc_html( $data['name'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Phone', 'sor-booking' ); ?>"><?php echo \esc_html( $data['phone'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Email', 'sor-booking' ); ?>"><?php echo \esc_html( $data['email'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Horse', 'sor-booking' ); ?>"><?php echo \esc_html( $data['horse_name'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Date/Time', 'sor-booking' ); ?>"><?php echo \esc_html( $data['slot_display'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Price (€)', 'sor-booking' ); ?>"><?php echo \esc_html( $data['price_display'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Status', 'sor-booking' ); ?>">
                                    <span class="sor-booking-status-badge status-<?php echo \esc_attr( $data['status'] ); ?>"><?php echo \esc_html( $data['status_label'] ); ?></span>
                                </td>
                                <td data-label="<?php \esc_attr_e( 'Payment Ref', 'sor-booking' ); ?>"><?php echo \esc_html( $data['payment_ref'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Created', 'sor-booking' ); ?>"><?php echo \esc_html( $data['created_at'] ); ?></td>
                                <td data-label="<?php \esc_attr_e( 'Updated', 'sor-booking' ); ?>"><?php echo \esc_html( $data['updated_at'] ); ?></td>
                                <td class="sor-booking-actions" data-label="<?php \esc_attr_e( 'Actions', 'sor-booking' ); ?>">
                                    <label class="screen-reader-text" for="sor-booking-status-<?php echo \esc_attr( $data['id'] ); ?>"><?php \esc_html_e( 'Set status', 'sor-booking' ); ?></label>
                                    <select id="sor-booking-status-<?php echo \esc_attr( $data['id'] ); ?>" class="sor-booking-status-select" data-uuid="<?php echo \esc_attr( $data['uuid'] ); ?>" data-original="<?php echo \esc_attr( $data['status'] ); ?>">
                                        <?php foreach ( $statuses as $key => $label ) : ?>
                                            <option value="<?php echo \esc_attr( $key ); ?>" <?php selected( $key, $data['status'] ); ?>><?php echo \esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button button-secondary sor-booking-qr-button" data-uuid="<?php echo \esc_attr( $data['uuid'] ); ?>"><?php \esc_html_e( 'QR anzeigen', 'sor-booking' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="13"><?php \esc_html_e( 'No bookings found for the selected filters.', 'sor-booking' ); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $pagination ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages"><?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="sor-booking-modal" id="sor-booking-details-modal" hidden>
            <div class="sor-booking-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sor-booking-details-title">
                <button type="button" class="sor-booking-modal__close" aria-label="<?php \esc_attr_e( 'Close', 'sor-booking' ); ?>">&times;</button>
                <h2 id="sor-booking-details-title"><?php \esc_html_e( 'Booking details', 'sor-booking' ); ?></h2>
                <div class="sor-booking-modal__content"></div>
            </div>
        </div>

        <div class="sor-booking-modal" id="sor-booking-qr-modal" hidden>
            <div class="sor-booking-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sor-booking-qr-title">
                <button type="button" class="sor-booking-modal__close" aria-label="<?php \esc_attr_e( 'Close', 'sor-booking' ); ?>">&times;</button>
                <h2 id="sor-booking-qr-title"><?php \esc_html_e( 'QR-Code anzeigen', 'sor-booking' ); ?></h2>
                <div class="sor-booking-modal__content sor-booking-modal__content--center"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render plugin settings page.
     */
    public function render_settings_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap sor-booking-admin">
            <h1><?php \esc_html_e( 'Einstellungen', 'sor-booking' ); ?></h1>
            <form method="post" action="options.php" class="sor-booking-settings-form">
                <?php
                \settings_fields( self::SETTINGS_GROUP );
                \wp_nonce_field( 'sor_booking_settings', 'sor_booking_settings_nonce' );
                \do_settings_sections( self::SETTINGS_GROUP );
                \submit_button( \__( 'Einstellungen speichern', 'sor-booking' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render CSV export page.
     */
    public function render_export_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $filters = $this->get_filters();
        ?>
        <div class="wrap sor-booking-admin">
            <h1><?php \esc_html_e( 'CSV-Export', 'sor-booking' ); ?></h1>
            <p class="description"><?php \esc_html_e( 'Exportiert alle Buchungen mit den gewählten Filtern als CSV-Datei mit Semikolon als Trennzeichen.', 'sor-booking' ); ?></p>
            <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="sor-booking-filter-form">
                <?php \wp_nonce_field( 'sor_booking_export', 'sor_booking_export_nonce' ); ?>
                <input type="hidden" name="action" value="sor_booking_export" />
                <?php $this->render_filter_inputs( $filters, 'export' ); ?>
                <div class="sor-booking-filter-actions">
                    <?php \submit_button( \__( 'Als CSV exportieren', 'sor-booking' ), 'primary', '', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Handle CSV export submission.
     */
    public function handle_export() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'Permission denied.', 'sor-booking' ) );
        }

        $nonce = isset( $_POST['sor_booking_export_nonce'] ) ? $_POST['sor_booking_export_nonce'] : '';
        if ( ! \wp_verify_nonce( $nonce, 'sor_booking_export' ) ) {
            \wp_die( \esc_html__( 'Invalid nonce.', 'sor-booking' ) );
        }

        $resources = \sor_booking_get_resources();
        $resource  = isset( $_POST['resource'] ) ? \sanitize_key( \wp_unslash( $_POST['resource'] ) ) : '';
        if ( $resource && ! isset( $resources[ $resource ] ) ) {
            $resource = '';
        }

        $status = isset( $_POST['status'] ) ? \sanitize_key( \wp_unslash( $_POST['status'] ) ) : '';
        if ( $status && ! array_key_exists( $status, $this->get_status_labels() ) ) {
            $status = '';
        }

        $date_from = isset( $_POST['date_from'] ) ? \sanitize_text_field( \wp_unslash( $_POST['date_from'] ) ) : '';
        $date_to   = isset( $_POST['date_to'] ) ? \sanitize_text_field( \wp_unslash( $_POST['date_to'] ) ) : '';

        if ( $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $date_from = '';
        }

        if ( $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $date_to = '';
        }

        $filters = array(
            'resource'  => $resource,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'limit'     => 0,
        );

        $bookings = $this->db->get_all_bookings( $filters );

        \nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ranch-bookings-' . gmdate( 'Ymd' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv(
            $output,
            array( 'ID', 'Resource', 'Name', 'Phone', 'Email', 'Horse', 'Date/Time', 'Price', 'Status', 'Payment Ref', 'Created', 'Updated' ),
            ';'
        );

        if ( $bookings ) {
            foreach ( $bookings as $booking ) {
                $data = $this->prepare_booking_data( $booking );
                fputcsv(
                    $output,
                    array(
                        $data['id'],
                        $data['resource_label'],
                        $data['name'],
                        $data['phone'],
                        $data['email'],
                        $data['horse_name'],
                        $data['slot_display'],
                        $data['price_display'],
                        $data['status_label'],
                        $data['payment_ref'],
                        $data['created_at'],
                        $data['updated_at'],
                    ),
                    ';'
                );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Render description for settings section.
     */
    public function render_settings_section() {
        echo '<p>' . \esc_html__( 'Definieren Sie Preise, Dauer und Zahlungsparameter für die Ressourcen.', 'sor-booking' ) . '</p>';
    }

    /**
     * Render numeric field.
     *
     * @param array $args Arguments.
     */
    public function render_number_field( $args ) {
        $options = \sor_booking_get_settings();
        $key     = $args['key'];
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
        $class   = isset( $args['class'] ) ? $args['class'] : 'small-text';
        ?>
        <input type="number" class="<?php echo \esc_attr( $class ); ?>" name="sor_booking_options[<?php echo \esc_attr( $key ); ?>]" value="<?php echo \esc_attr( $value ); ?>" min="<?php echo \esc_attr( $args['min'] ); ?>" step="<?php echo \esc_attr( $args['step'] ); ?>" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo \esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render PayPal mode select.
     *
     * @param array $args Arguments.
     */
    public function render_paypal_mode_field( $args ) {
        $options = \sor_booking_get_settings();
        $key     = $args['key'];
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : 'sandbox';
        ?>
        <select name="sor_booking_options[<?php echo \esc_attr( $key ); ?>]">
            <option value="sandbox" <?php selected( 'sandbox', $value ); ?>><?php \esc_html_e( 'Sandbox (Test)', 'sor-booking' ); ?></option>
            <option value="live" <?php selected( 'live', $value ); ?>><?php \esc_html_e( 'Live', 'sor-booking' ); ?></option>
        </select>
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo \esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render plain text field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $options = \sor_booking_get_settings();
        $key     = $args['key'];
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
        $class   = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        ?>
        <input type="text" class="<?php echo \esc_attr( $class ); ?>" name="sor_booking_options[<?php echo \esc_attr( $key ); ?>]" value="<?php echo \esc_attr( $value ); ?>" autocomplete="off" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo \esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render password field (masked output).
     *
     * @param array $args Field arguments.
     */
    public function render_password_field( $args ) {
        $options = \sor_booking_get_settings();
        $key     = $args['key'];
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
        ?>
        <input type="password" class="regular-text" name="sor_booking_options[<?php echo \esc_attr( $key ); ?>]" value="<?php echo \esc_attr( $value ); ?>" autocomplete="new-password" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo \esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw input.
     *
     * @return array
     */
    public function sanitize_options( $input ) {
        if ( ! isset( $_POST['sor_booking_settings_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['sor_booking_settings_nonce'] ) ), 'sor_booking_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return \sor_booking_get_settings();
        }

        $current = \sor_booking_get_settings();
        $clean   = is_array( $current ) ? $current : array();

        $clean['price_solekammer'] = isset( $input['price_solekammer'] ) ? floatval( $input['price_solekammer'] ) : $current['price_solekammer'];
        $clean['price_waage']      = isset( $input['price_waage'] ) ? floatval( $input['price_waage'] ) : $current['price_waage'];
        $clean['duration_solekammer'] = isset( $input['duration_solekammer'] ) ? absint( $input['duration_solekammer'] ) : $current['duration_solekammer'];
        $clean['duration_waage']      = isset( $input['duration_waage'] ) ? absint( $input['duration_waage'] ) : $current['duration_waage'];
        $clean['duration_schmied']    = isset( $input['duration_schmied'] ) ? absint( $input['duration_schmied'] ) : $current['duration_schmied'];

        $mode = isset( $input['paypal_mode'] ) ? strtolower( \sanitize_key( $input['paypal_mode'] ) ) : $current['paypal_mode'];
        $clean['paypal_mode'] = in_array( $mode, array( 'sandbox', 'live' ), true ) ? $mode : 'sandbox';

        $clean['paypal_client_id'] = isset( $input['paypal_client_id'] ) ? \sanitize_text_field( $input['paypal_client_id'] ) : $current['paypal_client_id'];
        $clean['paypal_secret']    = isset( $input['paypal_secret'] ) ? \sanitize_text_field( $input['paypal_secret'] ) : $current['paypal_secret'];
        $clean['qr_secret']        = isset( $input['qr_secret'] ) ? \sanitize_text_field( $input['qr_secret'] ) : $current['qr_secret'];

        \update_option( \SOR_BOOKING_TESTMODE_OPTION, 'live' !== $clean['paypal_mode'] ? 1 : 0 );

        return $clean;
    }

    /**
     * Determine if hook belongs to plugin screens.
     *
     * @param string $hook Current hook.
     *
     * @return bool
     */
    protected function is_plugin_screen( $hook ) {
        $hooks = array(
            'toplevel_page_' . self::MENU_SLUG,
            self::MENU_SLUG . '_page_' . self::MENU_SLUG . '-settings',
            self::MENU_SLUG . '_page_' . self::MENU_SLUG . '-export',
        );

        return in_array( $hook, $hooks, true );
    }

    /**
     * Retrieve sanitized filters from request.
     *
     * @return array
     */
    protected function get_filters() {
        $resources = \sor_booking_get_resources();

        $resource = isset( $_GET['resource'] ) ? \sanitize_key( \wp_unslash( $_GET['resource'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $resource && ! isset( $resources[ $resource ] ) ) {
            $resource = '';
        }

        $status = isset( $_GET['status'] ) ? \sanitize_key( \wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $status && ! array_key_exists( $status, $this->get_status_labels() ) ) {
            $status = '';
        }

        $date_from = isset( $_GET['date_from'] ) ? \sanitize_text_field( \wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_to   = isset( $_GET['date_to'] ) ? \sanitize_text_field( \wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $date_from = '';
        }

        if ( $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $date_to = '';
        }

        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged = $paged > 0 ? $paged : 1;

        return array(
            'resource'  => $resource,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'paged'     => $paged,
            'per_page'  => 20,
        );
    }

    /**
     * Build query arguments for filter persistence.
     *
     * @param array $filters Filters.
     *
     * @return array
     */
    protected function build_filter_query( array $filters ) {
        $query = array();

        foreach ( array( 'resource', 'status', 'date_from', 'date_to' ) as $key ) {
            if ( ! empty( $filters[ $key ] ) ) {
                $query[ $key ] = $filters[ $key ];
            }
        }

        return $query;
    }

    /**
     * Render reusable filter inputs.
     *
     * @param array  $filters Filter values.
     * @param string $prefix  Field prefix for element IDs.
     */
    protected function render_filter_inputs( array $filters, $prefix = 'filter' ) {
        $resources = \sor_booking_get_resources();
        $statuses  = $this->get_status_labels();
        $id_prefix = $prefix ? $prefix . '-' : '';
        ?>
        <div class="sor-booking-filters-grid">
            <div class="sor-booking-filter">
                <label for="<?php echo \esc_attr( $id_prefix . 'resource' ); ?>"><?php \esc_html_e( 'Resource', 'sor-booking' ); ?></label>
                <select id="<?php echo \esc_attr( $id_prefix . 'resource' ); ?>" name="resource">
                    <option value=""><?php \esc_html_e( 'Alle Ressourcen', 'sor-booking' ); ?></option>
                    <?php foreach ( $resources as $key => $resource ) : ?>
                        <option value="<?php echo \esc_attr( $key ); ?>" <?php selected( $key, $filters['resource'] ); ?>><?php echo \esc_html( $resource['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sor-booking-filter">
                <label for="<?php echo \esc_attr( $id_prefix . 'status' ); ?>"><?php \esc_html_e( 'Status', 'sor-booking' ); ?></label>
                <select id="<?php echo \esc_attr( $id_prefix . 'status' ); ?>" name="status">
                    <option value=""><?php \esc_html_e( 'Alle Stati', 'sor-booking' ); ?></option>
                    <?php foreach ( $statuses as $key => $label ) : ?>
                        <option value="<?php echo \esc_attr( $key ); ?>" <?php selected( $key, $filters['status'] ); ?>><?php echo \esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sor-booking-filter">
                <label for="<?php echo \esc_attr( $id_prefix . 'date_from' ); ?>"><?php \esc_html_e( 'Startdatum', 'sor-booking' ); ?></label>
                <input type="date" id="<?php echo \esc_attr( $id_prefix . 'date_from' ); ?>" name="date_from" value="<?php echo \esc_attr( $filters['date_from'] ); ?>" />
            </div>
            <div class="sor-booking-filter">
                <label for="<?php echo \esc_attr( $id_prefix . 'date_to' ); ?>"><?php \esc_html_e( 'Enddatum', 'sor-booking' ); ?></label>
                <input type="date" id="<?php echo \esc_attr( $id_prefix . 'date_to' ); ?>" name="date_to" value="<?php echo \esc_attr( $filters['date_to'] ); ?>" />
            </div>
        </div>
        <?php
    }

    /**
     * Prepare booking data for UI output and JSON consumption.
     *
     * @param object $booking Booking record.
     *
     * @return array
     */
    protected function prepare_booking_data( $booking ) {
        $resources      = \sor_booking_get_resources();
        $resource_label = isset( $resources[ $booking->resource ] ) ? $resources[ $booking->resource ]['label'] : $booking->resource;
        $status_labels  = $this->get_status_labels();
        $status_label   = isset( $status_labels[ $booking->status ] ) ? $status_labels[ $booking->status ] : ucfirst( $booking->status );
        $slot_start     = $this->format_datetime( $booking->slot_start, true );
        $slot_end       = $this->format_datetime( $booking->slot_end, true );
        $created        = $this->format_datetime( $booking->created_at, false );
        $updated        = $this->format_datetime( $booking->updated_at, false );
        $slot_display   = $slot_start;

        if ( $slot_end && $slot_end !== $slot_start ) {
            $slot_display .= ' – ' . $slot_end;
        }

        return array(
            'id'            => (int) $booking->id,
            'uuid'          => $booking->uuid,
            'resource'      => $booking->resource,
            'resource_label'=> $resource_label,
            'name'          => $booking->name,
            'email'         => $booking->email,
            'phone'         => $booking->phone,
            'horse_name'    => $booking->horse_name,
            'slot_start'    => $slot_start,
            'slot_end'      => $slot_end,
            'slot_display'  => $slot_display,
            'price'         => (float) $booking->price,
            'price_display' => \number_format_i18n( $booking->price, 2 ),
            'status'        => $booking->status,
            'status_label'  => $status_label,
            'payment_ref'   => $booking->payment_ref,
            'created_at'    => $created,
            'updated_at'    => $updated,
            'qr_url'        => $booking->uuid ? \rest_url( 'sor/v1/qr?ref=' . rawurlencode( $booking->uuid ) ) : '',
        );
    }

    /**
     * Format datetime in site timezone.
     *
     * @param string $value Date value.
     * @param bool   $gmt   Whether value is stored in GMT.
     *
     * @return string
     */
    protected function format_datetime( $value, $gmt = true ) {
        if ( empty( $value ) ) {
            return '';
        }

        $format = trim( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ) );

        return $gmt ? \get_date_from_gmt( $value, $format ) : \mysql2date( $format, $value );
    }

    /**
     * Retrieve localized status labels.
     *
     * @return array
     */
    protected function get_status_labels() {
        return array(
            'pending'   => \__( 'Pending', 'sor-booking' ),
            'paid'      => \__( 'Paid', 'sor-booking' ),
            'confirmed' => \__( 'Confirmed', 'sor-booking' ),
            'completed' => \__( 'Completed', 'sor-booking' ),
            'cancelled' => \__( 'Cancelled', 'sor-booking' ),
        );
    }
}
