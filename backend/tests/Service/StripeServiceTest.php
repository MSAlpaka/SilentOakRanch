<?php

namespace App\Tests\Service;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StripeServiceTest extends KernelTestCase
{
    public function testCreatePaymentIntentWithConfiguredApiKey(): void
    {
        $apiKey = $_ENV['STRIPE_SECRET_KEY']
            ?? $_SERVER['STRIPE_SECRET_KEY']
            ?? getenv('STRIPE_SECRET_KEY');

        if (empty($apiKey) || str_contains((string) $apiKey, 'replace-me')) {
            self::markTestSkipped('No Stripe API key configured for integration testing.');
        }

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
}
