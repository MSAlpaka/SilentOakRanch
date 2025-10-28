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
     * Sync service handler.
     *
     * @var SorBookingSyncService|null
     */
    protected $sync;

    /**
     * Stored admin page hooks.
     *
     * @var array
     */
    protected $page_hooks = array();

    /**
     * Constructor.
     *
     * @param DB                    $db   Database handler.
     * @param SorBookingSyncService $sync Sync service.
     */
    public function __construct( DB $db, ?SorBookingSyncService $sync = null ) {
        $this->db   = $db;
        $this->sync = $sync;

        \add_action( 'admin_menu', array( $this, 'register_menu' ) );
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        \add_action( 'admin_post_sor_booking_export', array( $this, 'handle_export' ) );
        \add_action( 'admin_post_sor_booking_mark_synced', array( $this, 'handle_mark_synced' ) );
        \add_action( 'admin_notices', array( $this, 'render_sync_notice' ) );
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
            array( $this, 'render_password_field' ),
            $page,
            'sor_booking_section_qr',
            array(
                'name'  => 'qr_secret',
                'class' => 'regular-text',
            )
        );

        \add_settings_section(
            'sor_booking_section_api',
            \__( 'Backend Synchronisierung', 'sor-booking' ),
            array( $this, 'render_api_section' ),
            $page
        );

        \add_settings_field(
            'api_enabled',
            \__( 'API-Synchronisierung aktivieren', 'sor-booking' ),
            array( $this, 'render_checkbox_field' ),
            $page,
            'sor_booking_section_api',
            array(
                'name'  => 'api_enabled',
                'label' => \__( 'Automatische Synchronisierung einschalten', 'sor-booking' ),
            )
        );

        \add_settings_field(
            'api_base_url',
            \__( 'API Basis-URL', 'sor-booking' ),
            array( $this, 'render_text_field' ),
            $page,
            'sor_booking_section_api',
            array(
                'name'  => 'api_base_url',
                'class' => 'regular-text',
            )
        );

        \add_settings_field(
            'api_key',
            \__( 'API Schlüssel', 'sor-booking' ),
            array( $this, 'render_password_field' ),
            $page,
            'sor_booking_section_api',
            array(
                'name'  => 'api_key',
                'class' => 'regular-text',
            )
        );

        \add_settings_field(
            'api_secret',
            \__( 'API Secret', 'sor-booking' ),
            array( $this, 'render_password_field' ),
            $page,
            'sor_booking_section_api',
            array(
                'name'  => 'api_secret',
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

        $this->page_hooks['sync'] = \add_submenu_page(
            'sor-booking',
            \__( 'Sync-Status', 'sor-booking' ),
            \__( 'Sync-Status', 'sor-booking' ),
            'manage_options',
            'sor-booking-sync',
            array( $this, 'render_sync_page' )
        );

        $this->page_hooks['contracts'] = \add_submenu_page(
            'sor-booking',
            \__( 'Verträge', 'sor-booking' ),
            \__( 'Verträge', 'sor-booking' ),
            'manage_options',
            'sor-booking-contracts',
            array( $this, 'render_contracts_page' )
        );
    }

    /**
     * Render contracts overview page.
     */
    public function render_contracts_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $sign_request = isset( $_GET['sign'] ) ? \sanitize_text_field( \wp_unslash( $_GET['sign'] ) ) : '';

        if ( $sign_request && $this->sync && $this->sync->is_enabled() ) {
            \check_admin_referer( 'sor-booking-contract-sign' );

            $result = $this->sync->get_contract_link( $sign_request, array( 'signed' => true ) );
            if ( \is_wp_error( $result ) ) {
                \add_settings_error( 'sor-booking-contracts', 'contract-signature', $result->get_error_message(), 'error' );
            } else {
                \add_settings_error( 'sor-booking-contracts', 'contract-signature', \__( 'Signatur wurde ausgelöst. Bitte lade die Seite neu.', 'sor-booking' ), 'updated' );
            }
        }

        echo '<div class="wrap" id="sor-booking-contracts">';
        echo '<h1>' . \esc_html__( 'Verträge', 'sor-booking' ) . '</h1>';
        \settings_errors( 'sor-booking-contracts' );

        if ( ! $this->sync || ! $this->sync->is_enabled() ) {
            echo '<div class="notice notice-warning"><p>' . \esc_html__( 'API-Synchronisierung ist deaktiviert. Verträge können nicht abgerufen werden.', 'sor-booking' ) . '</p></div>';
            echo '</div>';

            return;
        }

        $bookings = $this->db->get_all_bookings(
            array(
                'limit' => 50,
                'order' => 'DESC',
            )
        );

        $eligible_statuses = array( 'paid', 'confirmed', 'completed' );
        $rows              = array();

        foreach ( $bookings as $booking ) {
            if ( empty( $booking->uuid ) || ! in_array( $booking->status, $eligible_statuses, true ) ) {
                continue;
            }

            $contract = $this->sync->get_contract_link( $booking->uuid );

            $row = array(
                'booking'     => $booking,
                'hash'        => '',
                'signed_hash' => '',
                'signed'      => false,
                'download'    => '',
                'signed_url'  => '',
                'error'       => '',
            );

            if ( \is_wp_error( $contract ) ) {
                $row['error'] = $contract->get_error_message();
            } elseif ( isset( $contract['ok'] ) && $contract['ok'] ) {
                $row['hash']        = isset( $contract['hash'] ) ? (string) $contract['hash'] : '';
                $row['signed_hash'] = isset( $contract['signed_hash'] ) ? (string) $contract['signed_hash'] : '';
                $row['signed']      = ! empty( $contract['signed'] );
                $row['download']    = isset( $contract['download_url'] ) ? (string) $contract['download_url'] : '';
                $row['signed_url']  = isset( $contract['signed_download_url'] ) ? (string) $contract['signed_download_url'] : '';
            } else {
                $row['error'] = \__( 'Kein Vertrag vorhanden.', 'sor-booking' );
            }

            $rows[] = $row;
        }

        if ( empty( $rows ) ) {
            echo '<div class="notice notice-info"><p>' . \esc_html__( 'Keine bezahlten Buchungen gefunden.', 'sor-booking' ) . '</p></div>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . \esc_html__( 'UUID', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Name', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Status', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Hash', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Signiert', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Aktionen', 'sor-booking' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $row ) {
            echo '<tr>';
            echo '<td>' . \esc_html( $row['booking']->uuid ) . '</td>';
            echo '<td>' . \esc_html( $row['booking']->name ) . '</td>';
            echo '<td>' . \esc_html( $row['booking']->status ) . '</td>';
            if ( $row['error'] ) {
                echo '<td colspan="2"><span class="description">' . \esc_html( $row['error'] ) . '</span></td>';
                echo '<td></td>';
            } else {
                echo '<td>' . \esc_html( $row['hash'] ) . '</td>';
                echo '<td>' . ( $row['signed'] ? \esc_html__( 'Ja', 'sor-booking' ) : \esc_html__( 'Nein', 'sor-booking' ) );
                if ( $row['signed'] && $row['signed_hash'] ) {
                    echo '<br /><span class="description">' . \esc_html( $row['signed_hash'] ) . '</span>';
                }
                echo '</td>';
                echo '<td>';
                if ( $row['download'] ) {
                    echo '<a class="button button-secondary" href="' . \esc_url( $row['download'] ) . '">' . \esc_html__( 'Download', 'sor-booking' ) . '</a> ';
                }
                if ( $row['signed_url'] ) {
                    echo '<a class="button" href="' . \esc_url( $row['signed_url'] ) . '">' . \esc_html__( 'Signierte Version', 'sor-booking' ) . '</a>';
                } elseif ( $row['download'] ) {
                    $sign_url = \wp_nonce_url(
                        \add_query_arg(
                            array(
                                'page' => 'sor-booking-contracts',
                                'sign' => $row['booking']->uuid,
                            ),
                            \admin_url( 'admin.php' )
                        ),
                        'sor-booking-contract-sign'
                    );
                    echo '<a class="button" href="' . \esc_url( $sign_url ) . '">' . \esc_html__( 'Signatur anfordern', 'sor-booking' ) . '</a>';
                }
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
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
     * Render sync warning notice when necessary.
     */
    public function render_sync_notice() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->render_configuration_notice();

        if ( ! $this->sync || ! $this->sync->is_enabled() ) {
            return;
        }

        $count = (int) $this->sync->get_unsynced_count();
        if ( $count <= 5 ) {
            return;
        }

        $url   = \admin_url( 'admin.php?page=sor-booking-sync' );
        $label = sprintf(
            \esc_html__( '%1$d Buchungen warten auf Synchronisierung.', 'sor-booking' ),
            $count
        );
        $link  = sprintf(
            '<a href="%s">%s</a>',
            \esc_url( $url ),
            \esc_html__( 'Zum Sync-Status', 'sor-booking' )
        );

        echo '<div class="notice notice-warning"><p>' . $label . ' ' . $link . '</p></div>';
    }

    /**
     * Render configuration warning when API or HMAC secrets are missing.
     */
    protected function render_configuration_notice() {
        $options = \sor_booking_get_options();
        $issues  = array();

        if ( empty( $options['qr_secret'] ) ) {
            $issues[] = \__( 'QR Geheimnis', 'sor-booking' );
        }

        if ( ! empty( $options['api_enabled'] ) ) {
            if ( empty( $options['api_base_url'] ) || 0 !== strpos( $options['api_base_url'], 'https://' ) ) {
                $issues[] = \__( 'API Basis-URL', 'sor-booking' );
            }

            if ( empty( $options['api_key'] ) ) {
                $issues[] = \__( 'API Schlüssel', 'sor-booking' );
            }

            if ( empty( $options['api_secret'] ) ) {
                $issues[] = \__( 'API Secret', 'sor-booking' );
            }
        }

        if ( empty( $issues ) ) {
            return;
        }

        $labels       = implode( ', ', array_map( '\\esc_html', $issues ) );
        $settings_url = \esc_url( \admin_url( 'admin.php?page=sor-booking-settings' ) );
        $link         = sprintf(
            '<a href="%s">%s</a>',
            $settings_url,
            \esc_html__( 'Einstellungen öffnen', 'sor-booking' )
        );

        printf(
            '<div class="notice notice-error"><p>%s %s</p></div>',
            sprintf(
                /* translators: %s: Missing configuration fields. */
                \__( 'Bitte vervollständige die folgenden Einstellungen: %s.', 'sor-booking' ),
                $labels
            ),
            $link
        );
    }

    /**
     * Render bookings page.
     */
    public function render_bookings_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $filters   = $this->get_filters();
        $uuid      = isset( $_GET['uuid'] ) ? \sanitize_text_field( \wp_unslash( $_GET['uuid'] ) ) : '';
        $has_uuid  = ! empty( $uuid );
        if ( $has_uuid ) {
            $booking  = $this->db->get_booking( $uuid );
            $bookings = $booking ? array( $booking ) : array();
            $total    = count( $bookings );
            $page     = 1;
            $per_page = $this->get_per_page();
            $max_pages = 1;
            $pagination = null;
        } else {
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
            $pagination = $this->get_pagination_links( $page, $max_pages, $filters );
        }
        $resources = \sor_booking_get_resources();

        $export_url = \add_query_arg(
            array_merge( array_filter( $filters ), array( 'page' => 'sor-booking-export' ) ),
            \admin_url( 'admin.php' )
        );

        if ( $has_uuid && empty( $bookings ) ) {
            echo '<div class="notice notice-warning"><p>' . \esc_html__( 'Die angeforderte Buchung wurde nicht gefunden.', 'sor-booking' ) . '</p></div>';
        }
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
     * Render sync overview page.
     */
    public function render_sync_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }

        $items      = $this->sync ? $this->sync->get_unsynced_items( 100 ) : array();
        $resources  = \sor_booking_get_resources();
        $rest_url   = \esc_url_raw( \rest_url( 'sor/v1/admin/sync-retry' ) );
        $rest_nonce = \wp_create_nonce( 'wp_rest' );
        $flag       = isset( $_GET['sor_sync_marked'] ) ? (int) \wp_unslash( $_GET['sor_sync_marked'] ) : null;
        $can_retry  = $this->sync && $this->sync->is_enabled();

        echo '<div class="wrap" id="sor-booking-sync">';
        echo '<h1>' . \esc_html__( 'Sync-Status', 'sor-booking' ) . '</h1>';

        if ( ! $this->sync ) {
            echo '<div class="notice notice-error"><p>' . \esc_html__( 'Die Synchronisierungsfunktion ist derzeit nicht verfügbar.', 'sor-booking' ) . '</p></div>';
        } elseif ( ! $this->sync->is_enabled() ) {
            echo '<div class="notice notice-info"><p>' . \esc_html__( 'Die API-Synchronisierung ist deaktiviert. Buchungen werden lokal verarbeitet.', 'sor-booking' ) . '</p></div>';
        }

        if ( 1 === $flag ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Buchung wurde als synchron markiert.', 'sor-booking' ) . '</p></div>';
        } elseif ( 0 === $flag ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html__( 'Buchung konnte nicht aktualisiert werden.', 'sor-booking' ) . '</p></div>';
        }

        if ( empty( $items ) ) {
            echo '<p>' . \esc_html__( 'Alle Buchungen sind synchronisiert.', 'sor-booking' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . \esc_html__( 'Buchung', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Ressource', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Aktion', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Sync-Status', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Letzter Versuch', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Nachricht', 'sor-booking' ) . '</th>';
        echo '<th>' . \esc_html__( 'Aktionen', 'sor-booking' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $items as $item ) {
            $booking         = $item['booking'];
            $log             = $item['log'];
            $resource_label  = isset( $resources[ $booking->resource ]['label'] ) ? $resources[ $booking->resource ]['label'] : $booking->resource;
            $action_label    = $this->get_sync_action_label( $booking->sync_action ?? '' );
            $status_label    = $this->get_sync_status_label( $booking->sync_status ?? '' );
            $attempted       = $this->format_sync_datetime( $booking->sync_attempted_at ?? '' );
            $message         = $booking->sync_message ?? '';
            $status_code     = $log && isset( $log->status_code ) ? (int) $log->status_code : 0;
            if ( empty( $message ) && $log && ! empty( $log->message ) ) {
                $message = $log->message;
            }
            if ( $status_code && $message ) {
                $message = sprintf( \esc_html__( '[HTTP %1$d] %2$s', 'sor-booking' ), $status_code, $message );
            }
            if ( empty( $message ) ) {
                $message = \esc_html__( 'Keine Fehlermeldung vorhanden.', 'sor-booking' );
            }

            $view_url   = \add_query_arg( array( 'page' => 'sor-booking', 'uuid' => $booking->uuid ), \admin_url( 'admin.php' ) );
            $can_retry_attr = $can_retry ? '' : ' disabled="disabled"';

            echo '<tr>';
            echo '<td><strong>' . \esc_html( $booking->name ) . '</strong><br /><code>' . \esc_html( $booking->uuid ) . '</code><br />' . \esc_html( $booking->email ) . '</td>';
            echo '<td>' . \esc_html( $resource_label ) . '</td>';
            echo '<td>' . \esc_html( $action_label ) . '</td>';
            echo '<td>' . \esc_html( $status_label ) . '</td>';
            echo '<td>' . \esc_html( $attempted ? $attempted : '—' ) . '</td>';
            echo '<td>' . \esc_html( $message ) . '</td>';
            echo '<td>';
            echo '<button type="button" class="button button-secondary js-sor-sync-retry" data-uuid="' . \esc_attr( $booking->uuid ) . '"' . $can_retry_attr . '>' . \esc_html__( 'Erneut versuchen', 'sor-booking' ) . '</button> ';
            echo '<a class="button button-link" href="' . \esc_url( $view_url ) . '">' . \esc_html__( 'Ansehen', 'sor-booking' ) . '</a> ';
            echo '<form method="post" style="display:inline-block;margin-left:4px;" action="' . \esc_url( \admin_url( 'admin-post.php' ) ) . '">';
            \wp_nonce_field( 'sor_booking_mark_synced' );
            echo '<input type="hidden" name="action" value="sor_booking_mark_synced" />';
            echo '<input type="hidden" name="uuid" value="' . \esc_attr( $booking->uuid ) . '" />';
            echo '<button type="submit" class="button button-link-delete">' . \esc_html__( 'Als synchron markieren', 'sor-booking' ) . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ( $can_retry ) {
            $error_message = \esc_js( \__( 'Die Synchronisierung konnte nicht erneut gestartet werden.', 'sor-booking' ) );
            echo '<script>document.addEventListener("DOMContentLoaded",function(){const buttons=document.querySelectorAll(".js-sor-sync-retry");buttons.forEach(function(button){button.addEventListener("click",function(event){event.preventDefault();if(button.hasAttribute("disabled")){return;}const uuid=button.dataset.uuid;if(!uuid){button.removeAttribute("disabled");return;}button.setAttribute("disabled","disabled");fetch("' . \esc_js( $rest_url ) . '",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . \esc_js( $rest_nonce ) . '"},body:JSON.stringify({uuid:uuid})}).then(function(response){if(!response.ok){throw new Error("request_failed");}return response.json();}).then(function(){window.location.reload();}).catch(function(){alert("' . $error_message . '");button.removeAttribute("disabled");});});});});</script>';
        }

        echo '</div>';
    }

    /**
     * Handle manual sync mark action.
     */
    public function handle_mark_synced() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( \esc_html__( 'Keine Berechtigung.', 'sor-booking' ) );
        }

        \check_admin_referer( 'sor_booking_mark_synced' );

        $uuid = isset( $_POST['uuid'] ) ? \sanitize_text_field( \wp_unslash( $_POST['uuid'] ) ) : '';
        if ( $uuid ) {
            if ( $this->sync ) {
                $this->sync->mark_booking_manually_synced( $uuid );
            } else {
                $this->db->update_booking_fields(
                    $uuid,
                    array(
                        'synced'         => 1,
                        'sync_status'    => 'manual',
                        'sync_action'    => '',
                        'sync_synced_at' => \current_time( 'mysql' ),
                        'sync_message'   => '',
                    )
                );
                $this->db->clear_sync_logs( $uuid );
            }
            $flag = 1;
        } else {
            $flag = 0;
        }

        $redirect = \add_query_arg(
            array(
                'page'            => 'sor-booking-sync',
                'sor_sync_marked' => $flag,
            ),
            \admin_url( 'admin.php' )
        );

        \wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Format sync datetime for display.
     *
     * @param string $datetime Datetime value.
     *
     * @return string
     */
    protected function format_sync_datetime( $datetime ) {
        if ( empty( $datetime ) ) {
            return '';
        }

        return \mysql2date( $this->get_datetime_format(), $datetime );
    }

    /**
     * Retrieve formatted datetime pattern.
     *
     * @return string
     */
    protected function get_datetime_format() {
        $date = \get_option( 'date_format', 'd.m.Y' );
        $time = \get_option( 'time_format', 'H:i' );

        return trim( $date . ' ' . $time );
    }

    /**
     * Retrieve human readable sync action label.
     *
     * @param string $action Action key.
     *
     * @return string
     */
    protected function get_sync_action_label( $action ) {
        $action = \sanitize_key( $action );

        switch ( $action ) {
            case 'create':
                return \__( 'Erstellung', 'sor-booking' );
            case 'status_paid':
                return \__( 'Status: bezahlt', 'sor-booking' );
            case 'status_completed':
                return \__( 'Status: abgeschlossen', 'sor-booking' );
            case 'status_cancelled':
                return \__( 'Status: storniert', 'sor-booking' );
            default:
                return $action ? $action : \__( 'Unbekannt', 'sor-booking' );
        }
    }

    /**
     * Retrieve human readable sync status label.
     *
     * @param string $status Status key.
     *
     * @return string
     */
    protected function get_sync_status_label( $status ) {
        $status = \sanitize_key( $status );

        $labels = array(
            'pending'  => \__( 'Wartet', 'sor-booking' ),
            'error'    => \__( 'Fehler', 'sor-booking' ),
            'synced'   => \__( 'Synchronisiert', 'sor-booking' ),
            'disabled' => \__( 'Deaktiviert', 'sor-booking' ),
            'manual'   => \__( 'Manuell bestätigt', 'sor-booking' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
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

        $output['api_enabled'] = ! empty( $input['api_enabled'] ) ? 1 : 0;

        $base_url = isset( $input['api_base_url'] ) ? \esc_url_raw( trim( $input['api_base_url'] ) ) : $defaults['api_base_url'];
        if ( $base_url && 0 !== strpos( $base_url, 'https://' ) ) {
            $base_url = $defaults['api_base_url'];
        }
        $output['api_base_url'] = $base_url ? \untrailingslashit( $base_url ) : $defaults['api_base_url'];

        $output['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $output['api_secret'] = isset( $input['api_secret'] ) ? sanitize_text_field( $input['api_secret'] ) : '';

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
     * Render API sync section description.
     */
    public function render_api_section() {
        echo '<p>' . \esc_html__( 'Synchronisiere Buchungen optional mit dem Silent Oak Ranch Backend.', 'sor-booking' ) . '</p>';
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
     * Render checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $options = \sor_booking_get_options();
        $name    = $args['name'];
        $value   = ! empty( $options[ $name ] );
        $label   = isset( $args['label'] ) ? $args['label'] : '';
        ?>
        <label>
            <input type="checkbox" name="sor_booking_options[<?php echo \esc_attr( $name ); ?>]" value="1" <?php \checked( $value, true ); ?> />
            <?php if ( $label ) : ?>
                <span><?php echo \esc_html( $label ); ?></span>
            <?php endif; ?>
        </label>
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
