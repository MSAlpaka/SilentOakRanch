(function ($) {
    'use strict';

    if ( typeof SORBookingAdmin === 'undefined' ) {
        return;
    }

    const noticesContainerSelector = '#sor-booking-admin-notices';

    function escapeHtml( value ) {
        return $( '<div />' ).text( value == null ? '' : value ).html();
    }

    function showNotice( message, type ) {
        const $container = $( noticesContainerSelector );
        if ( ! $container.length ) {
            return;
        }

        const noticeType = type || 'success';
        const $notice = $(
            '<div class="notice is-dismissible notice-' + noticeType + ' sor-booking-notice"><p>' + escapeHtml( message ) + '</p></div>'
        );

        const $button = $(
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + escapeHtml( SORBookingAdmin.i18n.close ) + '</span></button>'
        );

        $button.on( 'click', function () {
            $notice.remove();
        } );

        $notice.append( $button );
        $container.append( $notice );

        setTimeout( function () {
            $notice.fadeOut( 200, function () {
                $( this ).remove();
            } );
        }, 6000 );
    }

    function getBookingData( $row ) {
        const raw = $row.attr( 'data-booking' );
        if ( ! raw ) {
            return null;
        }

        try {
            return JSON.parse( raw );
        } catch ( e ) {
            return null;
        }
    }

    function updateRowData( $row, booking ) {
        if ( ! booking ) {
            return;
        }

        $row.attr( 'data-booking', JSON.stringify( booking ) );

        $row.find( 'td[data-label="' + SORBookingAdmin.i18n.statusLabel + '"] .sor-booking-status-badge' )
            .text( booking.status_label )
            .removeClass(function ( index, className ) {
                return ( className.match( /status-[^\s]+/g ) || [] ).join( ' ' );
            })
            .addClass( 'status-' + booking.status );

        $row.find( 'td[data-label="' + SORBookingAdmin.i18n.fieldSlot + '"]' ).text( booking.slot_display );
        $row.find( 'td[data-label="' + SORBookingAdmin.i18n.fieldPrice + '"]' ).text( booking.price_display );
        $row.find( 'td[data-label="' + SORBookingAdmin.i18n.fieldPaymentRef + '"]' ).text( booking.payment_ref || '' );
        $row.find( 'td[data-label="' + SORBookingAdmin.i18n.fieldUpdated + '"]' ).text( booking.updated_at );
        $row.find( '.sor-booking-status-select' )
            .val( booking.status )
            .attr( 'data-original', booking.status );
    }

    function openModal( $modal ) {
        $modal.removeAttr( 'hidden' );
        $modal.addClass( 'is-open' );
        const $close = $modal.find( '.sor-booking-modal__close' );
        if ( $close.length ) {
            $close.trigger( 'focus' );
        }
    }

    function closeModal( $modal ) {
        $modal.removeClass( 'is-open' );
        $modal.attr( 'hidden', 'hidden' );
        $modal.find( '.sor-booking-modal__content' ).empty();
    }

    function renderDetails( booking ) {
        const rows = [];
        const fields = [
            { label: SORBookingAdmin.i18n.fieldResource, value: booking.resource_label },
            { label: SORBookingAdmin.i18n.fieldName, value: booking.name },
            { label: SORBookingAdmin.i18n.fieldEmail, value: booking.email },
            { label: SORBookingAdmin.i18n.fieldPhone, value: booking.phone },
            { label: SORBookingAdmin.i18n.fieldHorse, value: booking.horse_name },
            { label: SORBookingAdmin.i18n.fieldStatus, value: booking.status_label },
            { label: SORBookingAdmin.i18n.fieldSlot, value: booking.slot_display },
            { label: SORBookingAdmin.i18n.fieldPrice, value: booking.price_display },
            { label: SORBookingAdmin.i18n.fieldPaymentRef, value: booking.payment_ref },
            { label: SORBookingAdmin.i18n.fieldCreated, value: booking.created_at },
            { label: SORBookingAdmin.i18n.fieldUpdated, value: booking.updated_at },
            { label: SORBookingAdmin.i18n.fieldUuid, value: booking.uuid }
        ];

        fields.forEach( function ( field ) {
            if ( ! field.value ) {
                return;
            }

            rows.push(
                '<div class="sor-booking-detail"><dt>' + escapeHtml( field.label ) + '</dt><dd>' + escapeHtml( field.value ) + '</dd></div>'
            );
        } );

        return '<dl class="sor-booking-details">' + rows.join( '' ) + '</dl>';
    }

    function handleStatusChange( event ) {
        const $select = $( event.currentTarget );
        const original = $select.attr( 'data-original' );
        const uuid = $select.data( 'uuid' );
        const status = $select.val();

        if ( ! uuid || ! status ) {
            return;
        }

        if ( status === 'cancelled' && ! window.confirm( SORBookingAdmin.i18n.confirmCancel ) ) {
            $select.val( original );
            return;
        }

        const payload = JSON.stringify( { uuid: uuid, status: status } );
        $select.prop( 'disabled', true );

        window.fetch( SORBookingAdmin.restUrl + 'admin/update', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': SORBookingAdmin.nonce
            },
            body: payload
        } ).then( function ( response ) {
            if ( ! response.ok ) {
                throw new Error( 'http_error' );
            }
            return response.json();
        } ).then( function ( data ) {
            if ( ! data || ! data.ok || ! data.booking ) {
                throw new Error( 'invalid_response' );
            }

            const $row = $select.closest( 'tr.sor-booking-row' );
            updateRowData( $row, data.booking );
            showNotice( SORBookingAdmin.i18n.statusUpdated, 'success' );
        } ).catch( function () {
            showNotice( SORBookingAdmin.i18n.statusUpdateFailed, 'error' );
            $select.val( original );
        } ).finally( function () {
            $select.prop( 'disabled', false );
        } );
    }

    $( document ).on( 'click', '.sor-booking-row', function ( event ) {
        if ( $( event.target ).closest( 'select,button,a,label' ).length ) {
            return;
        }

        const $row = $( event.currentTarget );
        const booking = getBookingData( $row );
        if ( ! booking ) {
            return;
        }

        const $modal = $( '#sor-booking-details-modal' );
        $modal.find( '.sor-booking-modal__content' ).html( renderDetails( booking ) );
        openModal( $modal );
    } );

    $( document ).on( 'click', '.sor-booking-qr-button', function ( event ) {
        event.stopPropagation();
        const uuid = $( this ).data( 'uuid' );
        if ( ! uuid ) {
            return;
        }

        const $modal = $( '#sor-booking-qr-modal' );
        const url = SORBookingAdmin.qrEndpoint + encodeURIComponent( uuid );
        const html = '<div class="sor-booking-qr-preview"><img src="' + escapeHtml( url ) + '" alt="QR" /></div>';
        $modal.find( '.sor-booking-modal__content' ).html( html );
        openModal( $modal );
    } );

    $( document ).on( 'click', '.sor-booking-modal__close', function () {
        closeModal( $( this ).closest( '.sor-booking-modal' ) );
    } );

    $( document ).on( 'click', '.sor-booking-modal', function ( event ) {
        if ( $( event.target ).is( '.sor-booking-modal' ) ) {
            closeModal( $( this ) );
        }
    } );

    $( document ).on( 'keydown', function ( event ) {
        if ( event.key === 'Escape' ) {
            $( '.sor-booking-modal.is-open' ).each( function () {
                closeModal( $( this ) );
            } );
        }
    } );

    $( document ).on( 'change', '.sor-booking-status-select', handleStatusChange );
})( jQuery );
