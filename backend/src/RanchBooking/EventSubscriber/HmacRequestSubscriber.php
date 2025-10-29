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

        $pathInfo = $request->getPathInfo();

        if (!str_starts_with($pathInfo, '/api/wp/')) {
            return;
        }

        if (preg_match('#^/api/wp/contracts/[^/]+/download$#', $pathInfo) === 1) {
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

        $method = strtoupper($request->getMethod());
        $path = $request->getPathInfo();
        $body = (string) $request->getContent();

        $payloadPipe = sprintf('%s|%s|%s|%s', $method, $path, $dateHeader, $body);
        $payloadNewline = sprintf("%s\n%s\n%s\n%s", $method, $path, $dateHeader, $body);

        $signatures = [];

        foreach ([$payloadPipe, $payloadNewline] as $candidate) {
            $raw = hash_hmac('sha256', $candidate, $this->wpBridgeSecret, true);
            $signatures[] = hash_hmac('sha256', $candidate, $this->wpBridgeSecret);
            $signatures[] = base64_encode($raw);
        }

        $signatureIsValid = false;
        foreach ($signatures as $expected) {
            if (hash_equals($expected, $signatureHeader)) {
                $signatureIsValid = true;
                break;
            }
        }

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
