<?php

namespace App\Controller\Api;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuditTrailController extends AbstractController
{
    public function __construct(private readonly AuditLogRepository $auditLogs)
    {
    }

    #[Route('/api/audit/{entityType}/{entityId}', name: 'app_audit_trail', methods: ['GET'])]
    public function audit(string $entityType, string $entityId): JsonResponse
    {
        $normalizedType = strtoupper($entityType);
        $entries = $this->auditLogs->findForEntity($normalizedType, $entityId);

        $trail = array_map(static function (AuditLog $entry): array {
            return [
                'id' => (string) $entry->getId(),
                'timestamp' => $entry->getTimestamp()->format(DATE_ATOM),
                'action' => $entry->getAction(),
                'hash' => $entry->getHash(),
                'user' => $entry->getUserIdentifier(),
                'ip' => $entry->getIpAddress(),
                'meta' => $entry->getMeta(),
            ];
        }, $entries);

        return $this->json([
            'entity_type' => $normalizedType,
            'entity_id' => $entityId,
            'count' => count($trail),
            'audit' => $trail,
        ]);
    }
}
