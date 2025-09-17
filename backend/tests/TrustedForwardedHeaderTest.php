<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrustedForwardedHeaderTest extends WebTestCase
{
    private bool $trustedProxiesWasSet;
    private ?string $trustedProxiesPrevious;
    private bool $trustedHostsWasSet;
    private ?string $trustedHostsPrevious;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->trustedProxiesWasSet, $this->trustedProxiesPrevious] = $this->captureEnv('TRUSTED_PROXIES');
        [$this->trustedHostsWasSet, $this->trustedHostsPrevious] = $this->captureEnv('TRUSTED_HOSTS');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('TRUSTED_PROXIES', $this->trustedProxiesPrevious, $this->trustedProxiesWasSet);
        $this->restoreEnv('TRUSTED_HOSTS', $this->trustedHostsPrevious, $this->trustedHostsWasSet);

        parent::tearDown();
    }

    public function testForwardedProtoMarksRequestAsSecure(): void
    {
        $this->setEnv('TRUSTED_PROXIES', '127.0.0.1');
        $this->setEnv('TRUSTED_HOSTS', '^localhost$');

        static::ensureKernelShutdown();

        $client = static::createClient(server: [
            'HTTP_HOST' => 'localhost',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.24',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $client->request('GET', '/healthz');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('https', $client->getRequest()->getScheme());
        self::assertTrue($client->getRequest()->isSecure());
    }

    private function captureEnv(string $name): array
    {
        $value = getenv($name);

        if (false === $value) {
            return [false, null];
        }

        return [true, $value];
    }

    private function setEnv(string $name, string $value): void
    {
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function restoreEnv(string $name, ?string $value, bool $wasSet): void
    {
        if ($wasSet) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;

            return;
        }

        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
}
