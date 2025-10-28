<?php

namespace App\RanchBooking\EventSubscriber;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HmacRequestSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_SKEW_SECONDS = 300;

    public function __construct(
        private readonly string $wpBridgeKey,
        private readonly string $wpBridgeSecret,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/wp/')) {
            return;
        }

        $keyHeader = $request->headers->get('X-SOR-Key');
        $dateHeader = $request->headers->get('X-SOR-Date');
        $signatureHeader = $request->headers->get('X-SOR-Signature');

        if ($keyHeader === null || $dateHeader === null || $signatureHeader === null) {
            $this->deny($event);
            return;
        }

        if (!hash_equals($this->wpBridgeKey, $keyHeader)) {
            $this->deny($event);
            return;
        }

        $timestamp = $this->parseTimestamp($dateHeader);

        if ($timestamp === null) {
            $this->deny($event);
            return;
        }

        $now = new DateTimeImmutable();

        if (abs($now->getTimestamp() - $timestamp->getTimestamp()) > self::ALLOWED_SKEW_SECONDS) {
            $this->deny($event);
            return;
        }

        $payload = sprintf(
            '%s|%s|%s|%s',
            strtoupper($request->getMethod()),
            $request->getPathInfo(),
            $dateHeader,
            (string) $request->getContent(),
        );

        $expectedRaw = hash_hmac('sha256', $payload, $this->wpBridgeSecret, true);
        $expectedHex = hash_hmac('sha256', $payload, $this->wpBridgeSecret);
        $expectedBase64 = base64_encode($expectedRaw);

        $signatureIsValid = hash_equals($expectedHex, $signatureHeader) || hash_equals($expectedBase64, $signatureHeader);

        if (!$signatureIsValid) {
            $this->deny($event);
        }
    }

    private function deny(RequestEvent $event): void
    {
        $event->setResponse(new JsonResponse(null, JsonResponse::HTTP_UNAUTHORIZED));
    }

    private function parseTimestamp(string $value): ?DateTimeImmutable
    {
        $timestamp = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        if ($timestamp instanceof DateTimeImmutable) {
            return $timestamp;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
