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

    public function createInvoiceDraft(string $customerId, array $params = []): \Stripe\Invoice
    {
        return $this->client->invoices->create(array_merge([
            'customer' => $customerId,
            'collection_method' => 'send_invoice',
            'auto_advance' => false,
        ], $params));
    }
}
