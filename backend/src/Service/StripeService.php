<?php

namespace App\Service;

use Stripe\StripeClient;

class StripeService
{
    public function __construct(private readonly StripeClient $client)
    {
    }

    public function createPaymentIntent(int $amount, string $currency = 'usd', array $metadata = []): \Stripe\PaymentIntent
    {
        return $this->client->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);
    }

    public function createCheckoutSession(
        int $amount,
        string $currency,
        string $successUrl,
        string $cancelUrl,
        string $description,
        array $metadata = [],
        ?string $customerEmail = null
    ): \Stripe\Checkout\Session {
        $normalizedMetadata = array_map(static fn($value) => (string) $value, $metadata);

        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => $description !== '' ? $description : 'Booking payment',
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => $normalizedMetadata,
            'payment_intent_data' => [
                'metadata' => $normalizedMetadata,
            ],
        ];

        if (!empty($normalizedMetadata['booking_id'])) {
            $payload['client_reference_id'] = $normalizedMetadata['booking_id'];
        }

        if ($customerEmail !== null) {
            $payload['customer_email'] = $customerEmail;
        }

        return $this->client->checkout->sessions->create($payload);
    }

    public function createInvoiceDraft(string $customerId, array $params = []): \Stripe\Invoice
    {
        return $this->client->invoices->create(array_merge([
            'customer' => $customerId,
            'collection_method' => 'send_invoice',
            'auto_advance' => false,
        ], $params));
    }
}
