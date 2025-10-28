<?php

namespace App\Controller\Api;

use App\Repository\ContractRepository;
use App\Service\AuditLogger;
use App\Service\SignatureValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class ContractVerificationController extends AbstractController
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly SignatureValidator $validator,
        private readonly AuditLogger $auditLogger
    ) {
    }

    #[Route('/api/contracts/{uuid}/verify', name: 'app_contracts_verify', methods: ['GET'])]
    public function verify(string $uuid): JsonResponse
    {
        try {
            $contractId = Uuid::fromString($uuid);
        } catch (\Throwable) {
            return $this->json([
                'message' => 'Invalid contract identifier provided.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $contract = $this->contracts->find($contractId);
        if ($contract === null) {
            return $this->json([
                'message' => 'Contract not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->validator->validate($contract);
        $this->auditLogger->log($contract, 'CONTRACT_VERIFIED', [
            'hash' => $result->getCalculatedHash(),
            'status' => $result->getStatus()->value,
        ]);

        return $this->json([
            'contract_uuid' => (string) $contract->getId(),
            'status' => $result->getStatus()->value,
            'hash' => $result->getCalculatedHash(),
            'expected_hash' => $result->getExpectedHash(),
            'signed_hash' => $contract->getSignedHash(),
            'signed_at' => $contract->getSignedAt()?->format(DATE_ATOM),
            'details' => $result->getDetails(),
        ]);
    }
}
