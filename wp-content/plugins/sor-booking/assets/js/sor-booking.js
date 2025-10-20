(function ($) {
    'use strict';

    function serializeForm($form) {
        var data = {};
        $form.serializeArray().forEach(function (item) {
            data[item.name] = item.value;
        });
        return data;
    }

    function showMessage($container, message, type) {
        var cls = 'sor-booking-message ' + (type || 'info');
        $container.html('<div class="' + cls + '">' + message + '</div>');
    }

    function renderQR($container, payload) {
        fetch(SORBooking.restUrl + 'qr?ref=' + encodeURIComponent(payload.uuid || payload))
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.ok) {
                    $container.html('<img src="' + response.url + '" alt="QR Code" class="sor-booking-qr" />');
                }
            });
    }

    function finalizePayment(booking, orderId, amount, $result, $qr) {
        return fetch(SORBooking.restUrl + 'paypal/webhook', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': SORBooking.nonce
            },
            body: JSON.stringify({
                booking_id: booking.booking_id,
                order_id: orderId,
                amount: amount
            })
        }).then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.ok) {
                    showMessage($result, SORBooking.i18n.paymentComplete, 'success');
                    renderQR($qr, booking.uuid);
                } else {
                    showMessage($result, response.message || SORBooking.i18n.error, 'error');
                }
            })
            .catch(function () {
                showMessage($result, SORBooking.i18n.error, 'error');
            });
    }

    function mountPayPal($container, booking, $result, $qr) {
        if (!booking || booking.price <= 0) {
            renderQR($qr, booking.uuid);
            showMessage($result, SORBooking.i18n.bookingCreated, 'success');
            return;
        }

        function renderButtons() {
            paypal.Buttons({
                style: {
                    color: 'gold',
                    shape: 'pill'
                },
                createOrder: function (data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: booking.price.toFixed(2)
                            },
                            custom_id: booking.uuid
                        }],
                        application_context: {
                            shipping_preference: 'NO_SHIPPING'
                        }
                    });
                },
                onApprove: function (data, actions) {
                    return actions.order.capture().then(function () {
                        return finalizePayment(booking, data.orderID, booking.price, $result, $qr);
                    });
                }
            }).render($container[0]);
        }

        if (window.paypal && window.paypal.Buttons) {
            renderButtons();
        } else {
            var attempts = 0;
            var timer = setInterval(function () {
                attempts++;
                if (window.paypal && window.paypal.Buttons) {
                    clearInterval(timer);
                    renderButtons();
                } else if (attempts > 20) {
                    clearInterval(timer);
                    showMessage($result, SORBooking.i18n.error, 'error');
                }
            }, 300);
        }
    }

    function initForm($form) {
        var $result = $form.find('.sor-booking-result');
        var $qr = $form.find('.sor-booking-qr-container');
        var $paypal = $form.find('.sor-booking-paypal');

        $form.on('submit', function (event) {
            event.preventDefault();
            var payload = serializeForm($form);
            payload.resource = $form.data('resource');

            fetch(SORBooking.restUrl + 'book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': SORBooking.nonce
                },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (response) {
                    if (!response.ok) {
                        showMessage($result, response.message || SORBooking.i18n.error, 'error');
                        return;
                    }

                    var booking = {
                        booking_id: response.booking_id,
                        uuid: response.uuid,
                        price: parseFloat(response.price || 0),
                        resource: response.resource
                    };

                    showMessage($result, SORBooking.i18n.bookingCreated, 'success');
                    $paypal.empty();
                    mountPayPal($paypal, booking, $result, $qr);
                })
                .catch(function () {
                    showMessage($result, SORBooking.i18n.error, 'error');
                });
        });
    }

    $(function () {
        $('.sor-booking-form').each(function () {
            initForm($(this));
        });
    });
})(jQuery);
