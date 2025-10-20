(function ($) {
    'use strict';

    var paypalScriptPromise = null;

    function toJson(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (data) {
            return {
                status: response.status,
                ok: response.ok,
                data: data
            };
        });
    }

    function showAlert($container, message, type) {
        if (!$container || !$container.length) {
            return;
        }
        var tone = type || 'info';
        var $alert = $('<div>', {
            class: 'sor-alert sor-alert--' + tone
        });

        if (Array.isArray(message)) {
            var $list = $('<ul>', { class: 'sor-alert__list' });
            message.forEach(function (item) {
                $list.append($('<li>').text(item));
            });
            $alert.append($list);
        } else if (message) {
            $alert.text(message);
        }

        $container.empty().append($alert);
    }

    function clearAlert($container) {
        if ($container && $container.length) {
            $container.empty();
        }
    }

    function validateFields($form) {
        var errors = [];
        var name = ($form.find('input[name="name"]').val() || '').trim();
        var email = ($form.find('input[name="email"]').val() || '').trim();
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!name) {
            errors.push(SORBooking.i18n.requiredName || 'Bitte gib deinen Namen ein.');
        }

        if (!email || !emailPattern.test(email)) {
            errors.push(SORBooking.i18n.requiredEmail || 'Bitte gib eine gültige E-Mail-Adresse ein.');
        }

        var datetimeField = $form.find('input[name="slot_start"]');
        if (datetimeField.length && datetimeField.prop('required')) {
            var slot = (datetimeField.val() || '').trim();
            if (!slot) {
                errors.push(SORBooking.i18n.requiredSlot || 'Bitte wähle Datum und Uhrzeit.');
            }
        }

        return errors;
    }

    function loadPayPalSdk(clientId) {
        if (window.paypal && window.paypal.Buttons) {
            return Promise.resolve(window.paypal);
        }

        if (!clientId) {
            return Promise.reject(new Error('PayPal client ID missing.'));
        }

        if (!paypalScriptPromise) {
            paypalScriptPromise = new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(clientId) + '&currency=EUR';
                script.async = true;
                script.onload = function () {
                    if (window.paypal && window.paypal.Buttons) {
                        resolve(window.paypal);
                    } else {
                        reject(new Error('PayPal SDK loaded without buttons.'));
                    }
                };
                script.onerror = function () {
                    reject(new Error('PayPal SDK failed to load.'));
                };
                document.head.appendChild(script);
            });
        }

        return paypalScriptPromise;
    }

    function postBooking(payload) {
        return fetch(SORBooking.restUrl + 'book', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': SORBooking.nonce
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(toJson);
    }

    function notifyWebhook(uuid, orderId) {
        return fetch(SORBooking.restUrl + 'paypal/webhook', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': SORBooking.nonce
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                uuid: uuid,
                orderId: orderId
            })
        }).then(toJson);
    }

    function fetchQr(uuid) {
        var url = SORBooking.restUrl + 'qr?ref=' + encodeURIComponent(uuid);
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        }).then(toJson).catch(function () {
            return {
                ok: false,
                data: {}
            };
        });
    }

    function renderSuccess($form, uuid) {
        fetchQr(uuid).catch(function () {
            return null;
        }).then(function (response) {
            var qrUrl = '';
            if (response && response.ok && response.data && response.data.url) {
                qrUrl = response.data.url;
            }

            var successHtml = '' +
                '<div class="sor-success">' +
                '<h3>Buchung bestätigt!</h3>' +
                '<p>Bitte speichere dein QR-Ticket und bring es zum Termin mit.</p>' +
                (qrUrl ? '<img id="sor-qr-img" src="' + qrUrl + '" alt="QR Ticket" width="256">' : '') +
                (qrUrl ? '' : '<p>' + (SORBooking.i18n.qrUnavailable || 'Das QR-Ticket konnte nicht geladen werden. Prüfe bitte deine E-Mail für dein Ticket.') + '</p>') +
                '<p>Eine Bestätigung wurde per E-Mail an dich gesendet.</p>' +
                '</div>';
            $form.replaceWith(successHtml);
        });
    }

    function mountPayPal($form, booking, $feedback) {
        var clientId = $form.data('paypal-client');
        var $paypal = $form.find('#paypal-button-container');
        if (!$paypal.length) {
            return;
        }

        if (booking.price <= 0) {
            showAlert($feedback, SORBooking.i18n.paymentComplete || 'Buchung bestätigt.', 'success');
            renderSuccess($form, booking.uuid);
            return;
        }

        $paypal.removeAttr('hidden');
        $paypal.empty();

        loadPayPalSdk(clientId).then(function () {
            return paypal.Buttons({
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
                        showAlert($feedback, SORBooking.i18n.processingPayment || 'Zahlung wird verarbeitet …', 'info');
                        return notifyWebhook(booking.uuid, data.orderID).then(function (response) {
                            if (!response.ok || !response.data.ok) {
                                throw new Error(response.data && response.data.message ? response.data.message : 'Zahlung konnte nicht bestätigt werden.');
                            }
                            showAlert($feedback, SORBooking.i18n.paymentComplete || 'Zahlung abgeschlossen.', 'success');
                            renderSuccess($form, booking.uuid);
                        });
                    });
                },
                onError: function () {
                    showAlert($feedback, SORBooking.i18n.paymentError || 'Bei der Zahlung ist ein Fehler aufgetreten.', 'error');
                }
            }).render($paypal[0]);
        }).catch(function (error) {
            showAlert($feedback, error && error.message ? error.message : (SORBooking.i18n.error || 'Ein Fehler ist aufgetreten.'), 'error');
            $paypal.attr('hidden', 'hidden');
        });
    }

    function handleSubmit(event) {
        event.preventDefault();

        var $form = $(event.currentTarget);
        var $feedback = $form.find('.sor-form__feedback');
        var $submit = $form.find('.sor-form__submit');

        clearAlert($feedback);

        var errors = validateFields($form);
        if (errors.length) {
            showAlert($feedback, errors, 'error');
            return;
        }

        $submit.prop('disabled', true);

        var payload = {};
        var formData = new FormData(event.currentTarget);
        formData.forEach(function (value, key) {
            payload[key] = value;
        });

        payload.resource = $form.data('resource') || payload.resource;

        showAlert($feedback, SORBooking.i18n.creatingBooking || 'Buchung wird erstellt …', 'info');

        postBooking(payload).then(function (response) {
            if (!response.ok || !response.data.ok) {
                throw new Error((response.data && response.data.message) || SORBooking.i18n.error || 'Leider ist ein Fehler aufgetreten.');
            }

            var data = response.data;
            var booking = {
                uuid: data.uuid,
                price: parseFloat(data.price || 0),
                resource: data.resource
            };

            showAlert($feedback, SORBooking.i18n.bookingCreated || 'Buchung erstellt. Bitte jetzt bezahlen, um zu bestätigen.', 'success');
            mountPayPal($form, booking, $feedback);
        }).catch(function (error) {
            showAlert($feedback, error && error.message ? error.message : (SORBooking.i18n.error || 'Leider ist ein Fehler aufgetreten.'), 'error');
        }).finally(function () {
            $submit.prop('disabled', false);
        });
    }

    $(function () {
        $('.sor-form').each(function () {
            var $form = $(this);
            $form.on('submit', handleSubmit);
        });
    });
})(jQuery);
