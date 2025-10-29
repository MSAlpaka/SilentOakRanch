<?php
/**
 * Admin view for contract dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$items            = isset( $contracts_data['items'] ) && is_array( $contracts_data['items'] ) ? $contracts_data['items'] : array();
$meta             = isset( $contracts_data['meta'] ) && is_array( $contracts_data['meta'] ) ? $contracts_data['meta'] : array();
$total_contracts  = isset( $meta['count'] ) ? (int) $meta['count'] : count( $items );
$fetched_at       = isset( $meta['fetched_at'] ) ? $meta['fetched_at'] : '';
$has_items        = ! empty( $items );
$viewer_id        = 'sor-contracts-viewer';
$audit_modal_id   = 'sor-contracts-audit-modal';

?>
<div class="wrap sor-contracts-admin" id="sor-booking-contracts" data-nonce="<?php echo esc_attr( $contracts_nonce ); ?>">
    <h1><?php esc_html_e( 'Verträge', 'sor-booking' ); ?></h1>
    <div class="sor-contracts-messages" aria-live="polite"></div>

    <?php if ( ! empty( $contracts_error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $contracts_error ); ?></p></div>
    <?php else : ?>
        <div class="notice notice-info inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of contracts */
                        __( '%d Verträge geladen.', 'sor-booking' ),
                        $total_contracts
                    )
                );
                ?>
                <?php if ( $fetched_at ) : ?>
                    <span class="description">
                        <?php
                        printf(
                            /* translators: %s: timestamp */
                            esc_html__( 'Aktualisiert um %s.', 'sor-booking' ),
                            esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fetched_at ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( $has_items ) : ?>
        <table class="wp-list-table widefat fixed striped sor-contracts-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Booking-ID', 'sor-booking' ); ?></th>
                    <th><?php esc_html_e( 'Pferd / Vertrag', 'sor-booking' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'sor-booking' ); ?></th>
                    <th><?php esc_html_e( 'Signiert', 'sor-booking' ); ?></th>
                    <th><?php esc_html_e( 'Letzte Prüfung', 'sor-booking' ); ?></th>
                    <th><?php esc_html_e( 'Aktionen', 'sor-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) :
                    $booking           = isset( $item['booking'] ) && is_array( $item['booking'] ) ? $item['booking'] : array();
                    $contract          = isset( $item['contract'] ) && is_array( $item['contract'] ) ? $item['contract'] : array();
                    $contract_uuid     = isset( $contract['uuid'] ) ? (string) $contract['uuid'] : '';
                    $booking_uuid      = isset( $booking['uuid'] ) && $booking['uuid'] ? (string) $booking['uuid'] : ( isset( $booking['id'] ) ? (string) $booking['id'] : '' );
                    $horse_name        = isset( $booking['horse'] ) && $booking['horse'] ? (string) $booking['horse'] : ( isset( $booking['label'] ) ? (string) $booking['label'] : '' );
                    $status_key        = isset( $contract['status'] ) ? (string) $contract['status'] : 'unknown';
                    $status_label      = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : ucfirst( $status_key );
                    $status_class      = 'sor-contract-status sor-contract-status--' . sanitize_html_class( strtolower( $status_key ) );
                    $signed            = ! empty( $contract['signed'] );
                    $signed_label      = $signed ? __( 'Ja', 'sor-booking' ) : __( 'Nein', 'sor-booking' );
                    $signed_badge      = $signed ? 'sor-contract-flag sor-contract-flag--positive' : 'sor-contract-flag sor-contract-flag--neutral';
                    $audit_summary     = isset( $contract['audit_summary'] ) && is_array( $contract['audit_summary'] ) ? $contract['audit_summary'] : null;
                    $validation_status = $audit_summary && ! empty( $audit_summary['status'] ) ? strtoupper( (string) $audit_summary['status'] ) : '';
                    $validation_label  = $validation_status && isset( $validation_labels[ $validation_status ] ) ? $validation_labels[ $validation_status ] : $validation_status;
                    $validation_class  = $validation_status ? 'sor-contract-validation sor-contract-validation--' . sanitize_html_class( strtolower( $validation_status ) ) : 'sor-contract-validation';
                    $download_url      = isset( $contract['download_url'] ) ? (string) $contract['download_url'] : '';
                    $signed_url        = isset( $contract['signed_download_url'] ) ? (string) $contract['signed_download_url'] : '';
                    $verify_url        = isset( $contract['verify_url'] ) ? (string) $contract['verify_url'] : '';
                    $signed_at         = isset( $contract['signed_at'] ) ? $contract['signed_at'] : '';
                    $last_event_time   = $audit_summary && ! empty( $audit_summary['timestamp'] ) ? $audit_summary['timestamp'] : '';
                    $last_event_label  = $audit_summary && ! empty( $audit_summary['action'] ) ? (string) $audit_summary['action'] : '';
                    ?>
                    <tr
                        class="sor-contracts-row"
                        data-contract="<?php echo esc_attr( $contract_uuid ); ?>"
                        data-verify="<?php echo esc_attr( $verify_url ); ?>"
                        data-download="<?php echo esc_attr( $download_url ); ?>"
                        data-signed-download="<?php echo esc_attr( $signed_url ); ?>"
                    >
                        <td>
                            <strong><?php echo esc_html( $booking_uuid ); ?></strong>
                            <?php if ( isset( $booking['status'] ) ) : ?>
                                <div class="description"><?php echo esc_html( $booking['status'] ); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sor-contracts-horse"><?php echo esc_html( $horse_name ); ?></span>
                            <?php if ( $signed_at ) : ?>
                                <div class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: timestamp */
                                        esc_html__( 'Signiert am %s', 'sor-booking' ),
                                        esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $signed_at ) )
                                    );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                        </td>
                        <td>
                            <span class="<?php echo esc_attr( $signed_badge ); ?>"><?php echo esc_html( $signed_label ); ?></span>
                        </td>
                        <td class="js-sor-contract-last-check" data-status="<?php echo esc_attr( $validation_status ); ?>">
                            <?php if ( $validation_status ) : ?>
                                <span class="<?php echo esc_attr( $validation_class ); ?>"><?php echo esc_html( $validation_label ); ?></span>
                            <?php else : ?>
                                <span class="sor-contract-validation sor-contract-validation--pending"><?php esc_html_e( 'Noch nicht geprüft', 'sor-booking' ); ?></span>
                            <?php endif; ?>
                            <?php if ( $last_event_time ) : ?>
                                <div class="description">
                                    <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_event_time ) ); ?>
                                    <?php if ( $last_event_label ) : ?>
                                        · <?php echo esc_html( $last_event_label ); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="sor-contracts-actions">
                            <button type="button" class="button button-secondary js-sor-contract-verify" data-contract="<?php echo esc_attr( $contract_uuid ); ?>">
                                <?php esc_html_e( 'Prüfen', 'sor-booking' ); ?>
                            </button>
                            <?php if ( $download_url ) : ?>
                                <a class="button button-secondary" href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e( 'Download', 'sor-booking' ); ?>
                                </a>
                                <button type="button" class="button js-sor-contract-preview" data-url="<?php echo esc_attr( $download_url ); ?>">
                                    <?php esc_html_e( 'Vorschau', 'sor-booking' ); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ( $signed_url ) : ?>
                                <a class="button" href="<?php echo esc_url( $signed_url ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e( 'Signierte Version', 'sor-booking' ); ?>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="button button-link js-sor-contract-audit" data-contract="<?php echo esc_attr( $contract_uuid ); ?>">
                                <?php esc_html_e( 'Audit', 'sor-booking' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <?php if ( empty( $contracts_error ) ) : ?>
            <div class="notice notice-info"><p><?php esc_html_e( 'Es wurden keine Verträge gefunden.', 'sor-booking' ); ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="sor-contracts-viewer" id="<?php echo esc_attr( $viewer_id ); ?>" hidden>
        <div class="sor-contracts-viewer__header">
            <h2><?php esc_html_e( 'Vertragsvorschau', 'sor-booking' ); ?></h2>
            <button type="button" class="button button-link js-sor-contract-viewer-close"><?php esc_html_e( 'Schließen', 'sor-booking' ); ?></button>
        </div>
        <iframe class="sor-contracts-viewer__frame" src="about:blank" title="<?php esc_attr_e( 'Vertragsvorschau', 'sor-booking' ); ?>"></iframe>
    </div>

    <div class="sor-contracts-modal" id="<?php echo esc_attr( $audit_modal_id ); ?>" hidden>
        <div class="sor-contracts-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sor-contracts-audit-title">
            <div class="sor-contracts-modal__header">
                <h2 id="sor-contracts-audit-title"><?php esc_html_e( 'Audit-Trail', 'sor-booking' ); ?></h2>
                <button type="button" class="button button-link js-sor-contract-modal-close" aria-label="<?php esc_attr_e( 'Schließen', 'sor-booking' ); ?>">&times;</button>
            </div>
            <div class="sor-contracts-modal__body">
                <table class="wp-list-table widefat fixed striped sor-contracts-audit-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Zeitpunkt', 'sor-booking' ); ?></th>
                            <th><?php esc_html_e( 'Aktion', 'sor-booking' ); ?></th>
                            <th><?php esc_html_e( 'Benutzer', 'sor-booking' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'sor-booking' ); ?></th>
                            <th><?php esc_html_e( 'Hash', 'sor-booking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody class="js-sor-contract-audit-entries">
                        <tr class="sor-contracts-audit-empty">
                            <td colspan="5"><?php esc_html_e( 'Für diesen Vertrag liegen keine Audit-Einträge vor.', 'sor-booking' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
