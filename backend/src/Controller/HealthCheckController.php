<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthCheckController
{
    #[Route('/healthz', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): Response
    {
        return new Response('', Response::HTTP_OK);
    }
}
