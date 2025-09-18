<?php

namespace App\Tests\Service;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StripeServiceTest extends KernelTestCase
{
    public function testCreatePaymentIntentWithConfiguredApiKey(): void
    {
        $this->ensureStripeApiKeyOrSkip();

        self::bootKernel();

        /** @var StripeService $service */
        $service = self::getContainer()->get(StripeService::class);

        $intent = $service->createPaymentIntent(123, 'usd', [
            'test_case' => static::class,
            'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        self::assertSame(123, $intent->amount);
        self::assertSame('usd', $intent->currency);
        self::assertNotEmpty($intent->id);
        self::assertSame(static::class, $intent->metadata['test_case']);
    }

    public function testCreateCheckoutSessionWithConfiguredApiKey(): void
    {
        $this->ensureStripeApiKeyOrSkip();

        self::bootKernel();

        /** @var StripeService $service */
        $service = self::getContainer()->get(StripeService::class);

        $session = $service->createCheckoutSession(
            123,
            'usd',
            'https://example.com/success',
            'https://example.com/cancel',
            'Test checkout session',
            [
                'booking_id' => 'test-checkout',
                'test_case' => static::class,
            ],
            'customer@example.com'
        );

        self::assertNotEmpty($session->id);
        self::assertSame('payment', $session->mode);
        self::assertSame('https://example.com/success', $session->success_url);
        self::assertSame('https://example.com/cancel', $session->cancel_url);
        self::assertSame('test-checkout', $session->client_reference_id);
    }

    private function ensureStripeApiKeyOrSkip(): void
    {
        $apiKey = $_ENV['STRIPE_SECRET_KEY']
            ?? $_SERVER['STRIPE_SECRET_KEY']
            ?? getenv('STRIPE_SECRET_KEY');

        if (empty($apiKey) || str_contains((string) $apiKey, 'replace-me')) {
            self::markTestSkipped('No Stripe API key configured for integration testing.');
        }
    }
}
