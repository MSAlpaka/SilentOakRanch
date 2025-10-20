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
     * Stored admin page hooks.
     *
     * @var array
     */
    protected $page_hooks = array();

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
     * Register plugin settings.
     */
    public function register_settings() {
        \register_setting(
            'sor_booking_options_group',
            'sor_booking_options',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_options' ),
                'default'           => \sor_booking_get_option_defaults(),
            )
        );

        $page = 'sor_booking_settings';

        \add_settings_section(
            'sor_booking_section_pricing',
            \__( 'Preise', 'sor-booking' ),
            array( $this, 'render_pricing_section' ),
            $page
        );

        \add_settings_field(
            'price_solekammer',
            \__( 'Preis Solekammer (€)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            $page,
            'sor_booking_section_pricing',
            array(
                'name'  => 'price_solekammer',
                'step'  => '0.01',
                'min'   => '0',
                'class' => 'small-text',
            )
        );

        \add_settings_field(
            'price_waage',
            \__( 'Preis Waage (€)', 'sor-booking' ),
            array( $this, 'render_number_field' ),
            $page,
            'sor_booking_section_pricing',
            array(
                'name'  => 'price_waage',
                'step'  => '0.01',
                'min'   => '0',
                'class' => 'small-text',
            )
        );

        \add_settings_section(
            'sor_booking_section_paypal',
            \__( 'PayPal', 'sor-booking' ),
            array( $this, 'render_paypal_section' ),
            $page
        );

        \add_settings_field(
            'paypal_mode',
            \__( 'PayPal Modus', 'sor-booking' ),
            array( $this, 'render_select_field' ),
            $page,
            'sor_booking_section_paypal',
            array(
                'name'    => 'paypal_mode',
                'options' => array(
                    'sandbox' => \__( 'Sandbox (Test)', 'sor-booking' ),
                    'live'    => \__( 'Live', 'sor-booking' ),
                ),
            )
        );

        \add_settings_field(
            'paypal_client_id',
            \__( 'PayPal Client ID', 'sor-booking' ),
            array( $this, 'render_text_field' ),
            $page,
            'sor_booking_section_paypal',
            array(
                'name'  => 'paypal_client_id',
                'class' => 'regular-text',
            )
        );

        \add_settings_field(
            'paypal_secret',
            \__( 'PayPal Secret', 'sor-booking' ),
            array( $this, 'render_password_field' ),
            $page,
            'sor_booking_section_paypal',
            array(
                'name'  => 'paypal_secret',
                'class' => 'regular-text',
            )
        );

        \add_settings_section(
            'sor_booking_section_qr',
            \__( 'QR-Codes', 'sor-booking' ),
            '__return_false',
            $page
        );

        \add_settings_field(
            'qr_secret',
            \__( 'QR Geheimnis', 'sor-booking' ),
            array( $this, 'render_text_field' ),
            $page,
            'sor_booking_section_qr',
            array(
                'name'  => 'qr_secret',
                'class' => 'regular-text',
            )
        );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menu() {
        $this->page_hooks['bookings'] = \add_menu_page(
            \__( 'Ranch Buchungen', 'sor-booking' ),
            \__( 'Ranch Buchungen', 'sor-booking' ),
            'manage_options',
            'sor-booking',
            array( $this, 'render_bookings_page' ),
            'dashicons-calendar-alt',
            26
        );

        $this->page_hooks['bookings_sub'] = \add_submenu_page(
            'sor-booking',
            \__( 'Alle Buchungen', 'sor-booking' ),
            \__( 'Alle Buchungen', 'sor-booking' ),
            'manage_options',
            'sor-booking',
            array( $this, 'render_bookings_page' )
        );

        $this->page_hooks['settings'] = \add_submenu_page(
            'sor-booking',
            \__( 'Einstellungen', 'sor-booking' ),
            \__( 'Einstellungen', 'sor-booking' ),
            'manage_options',
            'sor-booking-settings',
            array( $this, 'render_settings_page' )
        );

        $this->page_hooks['export'] = \add_submenu_page(
            'sor-booking',
            \__( 'CSV-Export', 'sor-booking' ),
            \__( 'CSV-Export', 'sor-booking' ),
            'manage_options',
            'sor-booking-export',
            array( $this, 'render_export_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, $this->page_hooks, true ) ) {
            return;
        }

        if ( ! \wp_style_is( 'sor-booking', 'registered' ) ) {
            \wp_register_style( 'sor-booking', \SOR_BOOKING_URL . 'assets/css/sor-booking.css', array(), \SOR_BOOKING_VERSION );
        }

        \wp_enqueue_style( 'sor-booking' );

        \wp_register_script(
            'sor-booking-admin',
            \SOR_BOOKING_URL . 'assets/js/sor-booking-admin.js',
            array( 'jquery' ),
            \SOR_BOOKING_VERSION,
            true
        );

        $filters = $this->get_filters();

        \wp_localize_script(
            'sor-booking-admin',
            'SORBookingAdmin',
            array(
                'restUrl'   => \esc_url_raw( \rest_url( 'sor/v1/' ) ),
                'nonce'     => \wp_create_nonce( 'wp_rest' ),
                'statuses'  => $this->get_status_options(),
                'perPage'   => $this->get_per_page(),
                'listUrl'   => \esc_url( \admin_url( 'admin.php?page=sor-booking' ) ),
                'filters'   => $filters,
                'strings'   => array(
                    'detailsTitle'    => \__( 'Buchungsdetails', 'sor-booking' ),
                    'qrTitle'         => \__( 'QR-Code', 'sor-booking' ),
                    'statusUpdated'   => \__( 'Status erfolgreich aktualisiert.', 'sor-booking' ),
                    'statusFailed'    => \__( 'Status konnte nicht aktualisiert werden.', 'sor-booking' ),
                    'loading'         => \__( 'Lade Buchungen…', 'sor-booking' ),
                    'noResults'       => \__( 'Keine Buchungen gefunden.', 'sor-booking' ),
                    'confirmCancel'   => \__( 'Möchtest du diese Buchung wirklich stornieren?', 'sor-booking' ),
                    'updatedAt'       => \__( 'Aktualisiert', 'sor-booking' ),
                    'createdAt'       => \__( 'Erstellt', 'sor-booking' ),
                    'price'           => \__( 'Preis', 'sor-booking' ),
                    'date'            => \__( 'Datum/Zeit', 'sor-booking' ),
                    'close'           => \__( 'Schließen', 'sor-booking' ),
                    'totalLabel'      => \__( '%d Buchungen', 'sor-booking' ),
                    'pageLabel'       => \__( 'Seite %1$d von %2$d', 'sor-booking' ),
                    'statusLabel'     => \__( 'Status ändern', 'sor-booking' ),
                    'statusTitle'     => \__( 'Status', 'sor-booking' ),
                    'horseLabel'      => \__( 'Pferd', 'sor-booking' ),
                ),
            )
        );

        \wp_enqueue_script( 'sor-booking-admin' );
    }

    /**
     * Render bookings page.
     */
    public function render_bookings_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $filters   = $this->get_filters();
        $page      = $this->get_current_page();
        $per_page  = $this->get_per_page();
        $offset    = ( $page - 1 ) * $per_page;
        $query     = array_merge( $filters, array(
            'limit'  => $per_page,
            'offset' => $offset,
            'order'  => 'DESC',
        ) );
        $bookings  = $this->db->get_all_bookings( $query );
        $total     = $this->db->count_bookings( $filters );
        $max_pages = max( 1, (int) ceil( $total / $per_page ) );
        $resources = \sor_booking_get_resources();

        $export_url = \add_query_arg(
            array_merge( array_filter( $filters ), array( 'page' => 'sor-booking-export' ) ),
            \admin_url( 'admin.php' )
        );

        $pagination = $this->get_pagination_links( $page, $max_pages, $filters );
        ?>
        <div class="wrap" id="sor-booking-admin">
            <h1><?php \esc_html_e( 'Ranch Buchungen', 'sor-booking' ); ?></h1>

            <form id="sor-booking-filters" class="sor-booking-filters" method="get">
                <input type="hidden" name="page" value="sor-booking" />
                <div class="sor-booking-filters__row">
                    <label>
                        <span class="screen-reader-text"><?php \esc_html_e( 'Ressource filtern', 'sor-booking' ); ?></span>
                        <select name="resource">
                            <option value=""><?php \esc_html_e( 'Alle Ressourcen', 'sor-booking' ); ?></option>
                            <option value="solekammer" <?php \selected( $filters['resource'], 'solekammer' ); ?>><?php \esc_html_e( 'Solekammer', 'sor-booking' ); ?></option>
                            <option value="waage" <?php \selected( $filters['resource'], 'waage' ); ?>><?php \esc_html_e( 'Waage', 'sor-booking' ); ?></option>
                            <option value="schmied" <?php \selected( $filters['resource'], 'schmied' ); ?>><?php \esc_html_e( 'Schmied', 'sor-booking' ); ?></option>
                        </select>
                    </label>

                    <label>
                        <span class="screen-reader-text"><?php \esc_html_e( 'Status filtern', 'sor-booking' ); ?></span>
                        <select name="status">
                            <option value=""><?php \esc_html_e( 'Alle Stati', 'sor-booking' ); ?></option>
                            <?php foreach ( $this->get_status_options() as $key => $label ) : ?>
                                <option value="<?php echo \esc_attr( $key ); ?>" <?php \selected( $filters['status'], $key ); ?>><?php echo \esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span><?php \esc_html_e( 'Von', 'sor-booking' ); ?></span>
                        <input type="date" name="date_from" value="<?php echo \esc_attr( $filters['date_from'] ); ?>" />
                    </label>

                    <label>
                        <span><?php \esc_html_e( 'Bis', 'sor-booking' ); ?></span>
                        <input type="date" name="date_to" value="<?php echo \esc_attr( $filters['date_to'] ); ?>" />
                    </label>

                    <div class="sor-booking-filters__actions">
                        <button type="submit" class="button button-primary"><?php \esc_html_e( 'Filter anwenden', 'sor-booking' ); ?></button>
                        <a class="button" href="<?php echo \esc_url( \admin_url( 'admin.php?page=sor-booking' ) ); ?>"><?php \esc_html_e( 'Zurücksetzen', 'sor-booking' ); ?></a>
                        <a class="button button-secondary" href="<?php echo \esc_url( $export_url ); ?>"><?php \esc_html_e( 'Als CSV exportieren', 'sor-booking' ); ?></a>
                    </div>
                </div>
            </form>

            <div class="sor-booking-summary">
                <span class="sor-booking-summary__count"><?php printf( \esc_html__( '%d Buchungen', 'sor-booking' ), (int) $total ); ?></span>
                <span class="sor-booking-summary__page"><?php printf( \esc_html__( 'Seite %1$d von %2$d', 'sor-booking' ), (int) $page, (int) $max_pages ); ?></span>
            </div>

            <div class="sor-booking-table-wrapper">
                <table class="wp-list-table widefat fixed striped sor-booking-table" data-total-pages="<?php echo \esc_attr( $max_pages ); ?>">
                    <thead>
                        <tr>
                            <th><?php \esc_html_e( 'ID', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Ressource', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Name', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Telefon', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'E-Mail', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Pferd', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Datum/Zeit', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Preis (€)', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Status', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Payment Ref', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Erstellt', 'sor-booking' ); ?></th>
                            <th><?php \esc_html_e( 'Aktualisiert', 'sor-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $bookings ) : ?>
                            <?php foreach ( $bookings as $booking ) :
                                $display = $this->prepare_display_values( $booking, $resources );
                                $row     = $this->prepare_booking_for_js( $booking, $resources );
                                ?>
                                <tr class="sor-booking-row" data-uuid="<?php echo \esc_attr( $booking->uuid ); ?>" data-booking="<?php echo \esc_attr( \wp_json_encode( $row ) ); ?>">
                                    <td><?php echo \esc_html( $booking->id ); ?></td>
                                    <td><?php echo \esc_html( $display['resource'] ); ?></td>
                                    <td><?php echo \esc_html( $booking->name ); ?></td>
                                    <td><?php echo \esc_html( $booking->phone ); ?></td>
                                    <td><?php echo \esc_html( $booking->email ); ?></td>
                                    <td><?php echo \esc_html( $booking->horse_name ); ?></td>
                                    <td><?php echo \esc_html( $display['slot'] ); ?></td>
                                    <td><?php echo \esc_html( $display['price'] ); ?></td>
                                    <td>
                                        <span class="sor-booking-status sor-booking-status--<?php echo \esc_attr( $booking->status ); ?>"><?php echo \esc_html( $display['status_label'] ); ?></span>
                                        <label class="screen-reader-text" for="sor-booking-status-<?php echo \esc_attr( $booking->id ); ?>"><?php \esc_html_e( 'Status ändern', 'sor-booking' ); ?></label>
                                        <select id="sor-booking-status-<?php echo \esc_attr( $booking->id ); ?>" class="sor-booking-status-select" aria-label="<?php \esc_attr_e( 'Status ändern', 'sor-booking' ); ?>" data-uuid="<?php echo \esc_attr( $booking->uuid ); ?>" data-current="<?php echo \esc_attr( $booking->status ); ?>">
                                            <?php foreach ( $this->get_status_options() as $key => $label ) : ?>
                                                <option value="<?php echo \esc_attr( $key ); ?>" <?php \selected( $booking->status, $key ); ?>><?php echo \esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="button button-small sor-booking-show-qr" data-uuid="<?php echo \esc_attr( $booking->uuid ); ?>"><?php \esc_html_e( 'QR anzeigen', 'sor-booking' ); ?></button>
                                    </td>
                                    <td><?php echo \esc_html( $booking->payment_ref ); ?></td>
                                    <td><?php echo \esc_html( $display['created'] ); ?></td>
                                    <td><?php echo \esc_html( $display['updated'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="12" class="sor-booking-empty"><?php \esc_html_e( 'Keine Buchungen vorhanden.', 'sor-booking' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $pagination ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages sor-booking-pagination" data-total-pages="<?php echo \esc_attr( $max_pages ); ?>">
                        <?php foreach ( $pagination as $link ) : ?>
                            <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sor-booking-toast" role="status" aria-live="polite" hidden></div>

            <div class="sor-modal" id="sor-booking-details-modal" hidden>
                <div class="sor-modal__backdrop" data-close="modal"></div>
                <div class="sor-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sor-booking-details-title">
                    <button type="button" class="sor-modal__close" data-close="modal" aria-label="<?php \esc_attr_e( 'Schließen', 'sor-booking' ); ?>">&times;</button>
                    <h2 id="sor-booking-details-title"></h2>
                    <div class="sor-modal__content"></div>
                </div>
            </div>

            <div class="sor-modal" id="sor-booking-qr-modal" hidden>
                <div class="sor-modal__backdrop" data-close="modal"></div>
                <div class="sor-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sor-booking-qr-title">
                    <button type="button" class="sor-modal__close" data-close="modal" aria-label="<?php \esc_attr_e( 'Schließen', 'sor-booking' ); ?>">&times;</button>
                    <h2 id="sor-booking-qr-title"></h2>
                    <div class="sor-modal__content sor-modal__content--center"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = \sor_booking_get_options();
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'Buchungseinstellungen', 'sor-booking' ); ?></h1>
            <form method="post" action="options.php" class="sor-booking-settings">
                <?php \settings_fields( 'sor_booking_options_group' ); ?>
                <?php \do_settings_sections( 'sor_booking_settings' ); ?>
                <?php \submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render export page.
     */
    public function render_export_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $filters = $this->get_filters();
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'CSV-Export', 'sor-booking' ); ?></h1>
            <p><?php \esc_html_e( 'Exportiere die aktuell gefilterten Buchungen als CSV-Datei (Semikolon getrennt).', 'sor-booking' ); ?></p>
            <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="sor-booking-export-form">
                <?php \wp_nonce_field( 'sor_booking_export', 'sor_booking_export_nonce' ); ?>
                <input type="hidden" name="action" value="sor_booking_export" />
                <input type="hidden" name="resource" value="<?php echo \esc_attr( $filters['resource'] ); ?>" />
                <input type="hidden" name="status" value="<?php echo \esc_attr( $filters['status'] ); ?>" />
                <input type="hidden" name="date_from" value="<?php echo \esc_attr( $filters['date_from'] ); ?>" />
                <input type="hidden" name="date_to" value="<?php echo \esc_attr( $filters['date_to'] ); ?>" />
                <?php \submit_button( \__( 'Als CSV exportieren', 'sor-booking' ), 'primary', 'submit', false ); ?>
            </form>
        </div>
        <?php
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

        $filters  = $this->parse_filters( $_POST );
        $bookings = $this->db->get_all_bookings(
            array_merge(
                $filters,
                array(
                    'limit' => 0,
                    'order' => 'DESC',
                )
            )
        );

        $filename = 'ranch-bookings-' . gmdate( 'Ymd' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Resource', 'Name', 'Phone', 'Email', 'Horse', 'Date/Time', 'Price', 'Status', 'Payment Ref', 'Created', 'Updated' ), ';' );

        if ( $bookings ) {
            foreach ( $bookings as $booking ) {
                $slot = $this->format_slot( $booking );
                fputcsv(
                    $output,
                    array(
                        $booking->id,
                        $booking->resource,
                        $booking->name,
                        $booking->phone,
                        $booking->email,
                        $booking->horse_name,
                        $slot,
                        number_format( (float) $booking->price, 2, ',', '' ),
                        $booking->status,
                        $booking->payment_ref,
                        $booking->created_at,
                        $booking->updated_at,
                    ),
                    ';'
                );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw settings.
     *
     * @return array
     */
    public function sanitize_options( $input ) {
        $input    = is_array( $input ) ? $input : array();
        $defaults = \sor_booking_get_option_defaults();

        $output = array();

        $output['price_solekammer'] = isset( $input['price_solekammer'] ) ? floatval( $input['price_solekammer'] ) : $defaults['price_solekammer'];
        $output['price_waage']      = isset( $input['price_waage'] ) ? floatval( $input['price_waage'] ) : $defaults['price_waage'];

        $mode = isset( $input['paypal_mode'] ) ? sanitize_key( $input['paypal_mode'] ) : 'sandbox';
        if ( ! in_array( $mode, array( 'sandbox', 'live' ), true ) ) {
            $mode = 'sandbox';
        }
        $output['paypal_mode']      = $mode;
        $output['paypal_client_id'] = isset( $input['paypal_client_id'] ) ? sanitize_text_field( $input['paypal_client_id'] ) : '';
        $output['paypal_secret']    = isset( $input['paypal_secret'] ) ? sanitize_text_field( $input['paypal_secret'] ) : '';
        $output['qr_secret']        = isset( $input['qr_secret'] ) ? sanitize_text_field( $input['qr_secret'] ) : $defaults['qr_secret'];

        \update_option( \SOR_BOOKING_TESTMODE_OPTION, 'sandbox' === $mode );

        return $output;
    }

    /**
     * Render pricing section description.
     */
    public function render_pricing_section() {
        echo '<p>' . \esc_html__( 'Passe die Preise für Solekammer und Waage an.', 'sor-booking' ) . '</p>';
    }

    /**
     * Render PayPal section description.
     */
    public function render_paypal_section() {
        echo '<p>' . \esc_html__( 'Konfiguriere PayPal Sandbox oder Live Zugangsdaten.', 'sor-booking' ) . '</p>';
    }

    /**
     * Render number field.
     *
     * @param array $args Field arguments.
     */
    public function render_number_field( $args ) {
        $options = \sor_booking_get_options();
        $name    = $args['name'];
        $value   = isset( $options[ $name ] ) ? $options[ $name ] : '';
        ?>
        <input type="number" step="<?php echo \esc_attr( $args['step'] ); ?>" min="<?php echo \esc_attr( $args['min'] ); ?>" name="sor_booking_options[<?php echo \esc_attr( $name ); ?>]" value="<?php echo \esc_attr( $value ); ?>" class="<?php echo \esc_attr( $args['class'] ); ?>" />
        <?php
    }

    /**
     * Render select field.
     *
     * @param array $args Field arguments.
     */
    public function render_select_field( $args ) {
        $options = \sor_booking_get_options();
        $name    = $args['name'];
        $value   = isset( $options[ $name ] ) ? $options[ $name ] : '';
        ?>
        <select name="sor_booking_options[<?php echo \esc_attr( $name ); ?>]">
            <?php foreach ( $args['options'] as $key => $label ) : ?>
                <option value="<?php echo \esc_attr( $key ); ?>" <?php \selected( $value, $key ); ?>><?php echo \esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render text field.
     *
     * @param array $args Field arguments.
     */
    public function render_text_field( $args ) {
        $options = \sor_booking_get_options();
        $name    = $args['name'];
        $value   = isset( $options[ $name ] ) ? $options[ $name ] : '';
        ?>
        <input type="text" name="sor_booking_options[<?php echo \esc_attr( $name ); ?>]" value="<?php echo \esc_attr( $value ); ?>" class="<?php echo \esc_attr( $args['class'] ); ?>" />
        <?php
    }

    /**
     * Render password field.
     *
     * @param array $args Field arguments.
     */
    public function render_password_field( $args ) {
        $options = \sor_booking_get_options();
        $name    = $args['name'];
        $value   = isset( $options[ $name ] ) ? $options[ $name ] : '';
        ?>
        <input type="password" name="sor_booking_options[<?php echo \esc_attr( $name ); ?>]" value="<?php echo \esc_attr( $value ); ?>" class="<?php echo \esc_attr( $args['class'] ); ?>" autocomplete="new-password" />
        <?php
    }

    /**
     * Prepare status labels.
     *
     * @return array
     */
    protected function get_status_options() {
        return array(
            'pending'   => \__( 'Ausstehend', 'sor-booking' ),
            'paid'      => \__( 'Bezahlt', 'sor-booking' ),
            'confirmed' => \__( 'Bestätigt', 'sor-booking' ),
            'completed' => \__( 'Abgeschlossen', 'sor-booking' ),
            'cancelled' => \__( 'Storniert', 'sor-booking' ),
        );
    }

    /**
     * Retrieve query filters.
     *
     * @return array
     */
    protected function get_filters() {
        return $this->parse_filters( $_GET );
    }

    /**
     * Parse filters from provided source.
     *
     * @param array $source Raw source array.
     *
     * @return array
     */
    protected function parse_filters( $source ) {
        $resource  = isset( $source['resource'] ) ? \sanitize_key( \wp_unslash( $source['resource'] ) ) : '';
        $status    = isset( $source['status'] ) ? \sanitize_key( \wp_unslash( $source['status'] ) ) : '';
        $date_from = isset( $source['date_from'] ) ? \sanitize_text_field( \wp_unslash( $source['date_from'] ) ) : '';
        $date_to   = isset( $source['date_to'] ) ? \sanitize_text_field( \wp_unslash( $source['date_to'] ) ) : '';

        $valid_statuses = array_keys( $this->get_status_options() );
        if ( $status && ! in_array( $status, $valid_statuses, true ) ) {
            $status = '';
        }

        $valid_resources = array( 'solekammer', 'waage', 'schmied' );
        if ( $resource && ! in_array( $resource, $valid_resources, true ) ) {
            $resource = '';
        }

        return array(
            'resource'  => $resource,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        );
    }

    /**
     * Get current page number.
     *
     * @return int
     */
    protected function get_current_page() {
        $page = isset( $_GET['paged'] ) ? \absint( $_GET['paged'] ) : 1;

        return max( 1, $page );
    }

    /**
     * Retrieve per-page setting.
     *
     * @return int
     */
    protected function get_per_page() {
        return (int) \apply_filters( 'sor_booking_admin_per_page', 20 );
    }

    /**
     * Build pagination links.
     *
     * @param int   $current Current page.
     * @param int   $total   Total pages.
     * @param array $filters Active filters.
     *
     * @return array|null
     */
    protected function get_pagination_links( $current, $total, array $filters ) {
        if ( $total <= 1 ) {
            return null;
        }

        $base = \add_query_arg( array_merge( array_filter( $filters ), array( 'paged' => '%#%', 'page' => 'sor-booking' ) ), \admin_url( 'admin.php' ) );

        return \paginate_links(
            array(
                'base'      => $base,
                'format'    => '',
                'current'   => $current,
                'total'     => $total,
                'prev_text' => \__( '&laquo;', 'sor-booking' ),
                'next_text' => \__( '&raquo;', 'sor-booking' ),
                'type'      => 'array',
            )
        );
    }

    /**
     * Prepare booking details for modal display.
     *
     * @param object $booking   Booking record.
     * @param array  $resources Resource definitions.
     *
     * @return array
     */
    protected function prepare_booking_for_js( $booking, array $resources ) {
        $resource_label = $booking->resource;
        if ( isset( $resources[ $booking->resource ]['label'] ) ) {
            $resource_label = $resources[ $booking->resource ]['label'];
        }

        return array(
            'id'          => (int) $booking->id,
            'uuid'        => $booking->uuid,
            'resource'    => $resource_label,
            'name'        => $booking->name,
            'phone'       => $booking->phone,
            'email'       => $booking->email,
            'horse'       => $booking->horse_name,
            'slot'        => $this->format_slot( $booking ),
            'price'       => \number_format_i18n( (float) $booking->price, 2 ) . ' €',
            'price_display' => \number_format_i18n( (float) $booking->price, 2 ),
            'status'      => $booking->status,
            'status_label'=> $this->get_status_options()[ $booking->status ] ?? $booking->status,
            'payment_ref' => $booking->payment_ref,
            'created_at'  => $this->format_datetime( $booking->created_at ),
            'updated_at'  => $this->format_datetime( $booking->updated_at ),
        );
    }

    /**
     * Prepare booking display values.
     *
     * @param object $booking   Booking record.
     * @param array  $resources Resource definitions.
     *
     * @return array
     */
    protected function prepare_display_values( $booking, array $resources ) {
        $status_labels = $this->get_status_options();
        $resource      = $booking->resource;

        if ( isset( $resources[ $resource ]['label'] ) ) {
            $resource = $resources[ $resource ]['label'];
        }

        return array(
            'resource'     => $resource,
            'slot'         => $this->format_slot( $booking ),
            'price'        => \number_format_i18n( (float) $booking->price, 2 ),
            'status_label' => $status_labels[ $booking->status ] ?? $booking->status,
            'created'      => $this->format_datetime( $booking->created_at ),
            'updated'      => $this->format_datetime( $booking->updated_at ),
        );
    }

    /**
     * Format slot info.
     *
     * @param object $booking Booking record.
     *
     * @return string
     */
    protected function format_slot( $booking ) {
        if ( empty( $booking->slot_start ) ) {
            return '';
        }

        $format = \get_option( 'date_format', 'd.m.Y' ) . ' ' . \get_option( 'time_format', 'H:i' );
        $start  = $this->format_datetime( $booking->slot_start, $format );
        $end    = $booking->slot_end ? $this->format_datetime( $booking->slot_end, $format ) : '';

        if ( $end && $end !== $start ) {
            return $start . ' – ' . $end;
        }

        return $start;
    }

    /**
     * Format MySQL datetime.
     *
     * @param string $datetime Datetime string.
     * @param string $format   Format override.
     *
     * @return string
     */
    protected function format_datetime( $datetime, $format = '' ) {
        if ( empty( $datetime ) ) {
            return '';
        }

        $timestamp = strtotime( $datetime );
        if ( ! $timestamp ) {
            return '';
        }

        $format = $format ? $format : \get_option( 'date_format', 'd.m.Y' ) . ' ' . \get_option( 'time_format', 'H:i' );

        return \date_i18n( $format, $timestamp );
    }
}
