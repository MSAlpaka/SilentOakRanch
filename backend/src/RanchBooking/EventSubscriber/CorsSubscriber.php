<?php

namespace App\RanchBooking\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        if (str_starts_with($request->getPathInfo(), '/api/bookings') && $request->getMethod() === 'OPTIONS') {
            $response = new Response();
            $this->applyCorsHeaders($response);
            $response->setStatusCode(Response::HTTP_NO_CONTENT);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/bookings')) {
            $this->applyCorsHeaders($event->getResponse());
        }
    }

    private function applyCorsHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', 'https://silent-oak-ranch.de');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, OPTIONS');
    }
}
