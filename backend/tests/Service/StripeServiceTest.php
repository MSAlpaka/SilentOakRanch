<?php

namespace App\Tests\Service;

use App\Service\StripeService;
use PHPUnit\Framework\TestCase;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Service\Checkout\SessionService as CheckoutSessionService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

final class StripeServiceTest extends TestCase
{
    public function testCreatePaymentIntentReturnsStripeResponse(): void
    {
        $metadata = [
            'test_case' => static::class,
            'created_at' => '2024-01-01T00:00:00+00:00',
        ];

        $expectedIntent = PaymentIntent::constructFrom([
            'id' => 'pi_test',
            'amount' => 123,
            'currency' => 'usd',
            'metadata' => $metadata,
        ]);

        $paymentIntents = $this->createMock(PaymentIntentService::class);
        $paymentIntents
            ->expects(self::once())
            ->method('create')
            ->with([
                'amount' => 123,
                'currency' => 'usd',
                'metadata' => $metadata,
            ])
            ->willReturn($expectedIntent);

        $client = $this->createMock(StripeClient::class);
        $client
            ->expects(self::once())
            ->method('__get')
            ->with('paymentIntents')
            ->willReturn($paymentIntents);

        $service = new StripeService($client);

        $intent = $service->createPaymentIntent(123, 'usd', $metadata);

        self::assertSame($expectedIntent, $intent);
    }

    public function testCreateCheckoutSessionReturnsStripeResponse(): void
    {
        $metadata = [
            'booking_id' => 'test-checkout',
            'test_case' => static::class,
        ];

        $expectedMetadata = array_map(static fn($value): string => (string) $value, $metadata);

        $expectedSession = Session::constructFrom([
            'id' => 'cs_test',
            'mode' => 'payment',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'client_reference_id' => $expectedMetadata['booking_id'],
        ]);

        $sessions = $this->createMock(CheckoutSessionService::class);
        $sessions
            ->expects(self::once())
            ->method('create')
            ->with([
                'mode' => 'payment',
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => 123,
                        'product_data' => [
                            'name' => 'Test checkout session',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => $expectedMetadata,
                'payment_intent_data' => [
                    'metadata' => $expectedMetadata,
                ],
                'client_reference_id' => $expectedMetadata['booking_id'],
                'customer_email' => 'customer@example.com',
            ])
            ->willReturn($expectedSession);

        $checkoutFactory = new class($sessions) {
            public function __construct(public CheckoutSessionService $sessions)
            {
            }
        };

        $client = $this->createMock(StripeClient::class);
        $client
            ->expects(self::once())
            ->method('__get')
            ->with('checkout')
            ->willReturn($checkoutFactory);

        $service = new StripeService($client);

        $session = $service->createCheckoutSession(
            123,
            'usd',
            'https://example.com/success',
            'https://example.com/cancel',
            'Test checkout session',
            $metadata,
            'customer@example.com'
        );

        self::assertSame($expectedSession, $session);
    }
}
