<?php

namespace App\Service;

use App\Entity\Contract;
use App\Enum\SignatureValidationStatus;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SignatureValidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $validationApiUrl,
        private readonly int $signatureTtlDays
    ) {
    }

    public function validate(Contract $contract): SignatureValidationResult
    {
        $signedHash = $contract->getSignedHash();
        $path = $contract->getSignedPath() ?? $contract->getPath();

        if ($signedHash === null || $contract->getSignedPath() === null) {
            return new SignatureValidationResult(
                SignatureValidationStatus::UNSIGNED,
                $contract->getHash(),
                $contract->getSignedHash(),
                [
                    'reason' => 'Contract has not been digitally signed.',
                ]
            );
        }

        if (!is_file($path)) {
            return new SignatureValidationResult(
                SignatureValidationStatus::TAMPERED,
                '',
                $signedHash,
                [
                    'reason' => 'Signed contract file is missing.',
                ]
            );
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ServiceUnavailableHttpException(null, 'Unable to read contract contents for validation.');
        }

        $calculatedHash = hash('sha256', $contents);
        if (!hash_equals($signedHash, $calculatedHash)) {
            return new SignatureValidationResult(
                SignatureValidationStatus::TAMPERED,
                $calculatedHash,
                $signedHash,
                [
                    'reason' => 'SHA-256 digest mismatch between stored and calculated hash.',
                ]
            );
        }

        if ($this->validationApiUrl) {
            $remoteResult = $this->validateWithExternalService($contract, $contents, $calculatedHash);
            if ($remoteResult instanceof SignatureValidationResult) {
                return $remoteResult;
            }
        }

        if ($this->isExpired($contract)) {
            return new SignatureValidationResult(
                SignatureValidationStatus::EXPIRED,
                $calculatedHash,
                $signedHash,
                [
                    'reason' => sprintf('Signature exceeded validity threshold of %d days.', $this->signatureTtlDays),
                ]
            );
        }

        if (!$this->hasEmbeddedSignature($contents)) {
            return new SignatureValidationResult(
                SignatureValidationStatus::UNSIGNED,
                $calculatedHash,
                $signedHash,
                [
                    'reason' => 'Embedded PKCS7 signature markers missing from PDF.',
                ]
            );
        }

        return new SignatureValidationResult(
            SignatureValidationStatus::VALID,
            $calculatedHash,
            $signedHash,
            [
                'source' => 'local',
            ]
        );
    }

    private function isExpired(Contract $contract): bool
    {
        if ($this->signatureTtlDays <= 0) {
            return false;
        }

        $signedAt = $contract->getSignedAt();
        if ($signedAt === null) {
            return false;
        }

        $expiry = $signedAt->modify(sprintf('+%d days', $this->signatureTtlDays));

        return $expiry < new DateTimeImmutable('now');
    }

    private function hasEmbeddedSignature(string $contents): bool
    {
        return str_contains($contents, '/Sig') || str_contains($contents, 'ByteRange') || str_contains($contents, 'PKCS7');
    }

    private function validateWithExternalService(Contract $contract, string $contents, string $calculatedHash): ?SignatureValidationResult
    {
        try {
            $response = $this->httpClient->request('POST', $this->validationApiUrl, [
                'json' => [
                    'contract_uuid' => (string) $contract->getId(),
                    'hash' => $contract->getSignedHash(),
                    'calculated_hash' => $calculatedHash,
                    'signed_at' => $contract->getSignedAt()?->format(DateTimeImmutable::ATOM),
                    'document' => base64_encode($contents),
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
        } catch (TransportExceptionInterface | DecodingExceptionInterface $exception) {
            $this->logger->warning('External signature validation failed, falling back to local validation.', [
                'contract_uuid' => (string) $contract->getId(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $status = $data['status'] ?? null;
        $details = $data['details'] ?? [];
        if (!is_array($details)) {
            $details = ['details' => $details];
        }
        $details['source'] = 'remote';

        return match ($status) {
            'valid', 'VALID', true => new SignatureValidationResult(
                SignatureValidationStatus::VALID,
                $calculatedHash,
                $contract->getSignedHash(),
                $details
            ),
            'expired', 'EXPIRED' => new SignatureValidationResult(
                SignatureValidationStatus::EXPIRED,
                $calculatedHash,
                $contract->getSignedHash(),
                $details
            ),
            'unsigned', 'UNSIGNED' => new SignatureValidationResult(
                SignatureValidationStatus::UNSIGNED,
                $calculatedHash,
                $contract->getSignedHash(),
                $details
            ),
            'tampered', 'TAMPERED', 'invalid', false => new SignatureValidationResult(
                SignatureValidationStatus::TAMPERED,
                $calculatedHash,
                $contract->getSignedHash(),
                $details
            ),
            default => null,
        };
    }
}
