<?php

namespace App\Controller;

use App\Service\HealthCheckService;
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
    public function health(HealthCheckService $healthCheckService): Response
    {
        $result = $healthCheckService->check();

        if ($result['ok'] ?? false) {
            return new JsonResponse($result, Response::HTTP_OK);
        }

        return new JsonResponse($result, Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
