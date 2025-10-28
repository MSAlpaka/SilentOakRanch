<?php

namespace App\Service;

use App\Entity\Contract;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SignatureClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly string $contractsStoragePath,
        private readonly bool $signaturesEnabled,
        private readonly ?string $signatureBaseUrl = null,
        private readonly ?string $signatureToken = null
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->signaturesEnabled;
    }

    public function sign(Contract $contract): SignatureResult
    {
        if (!$this->signaturesEnabled) {
            throw new ServiceUnavailableHttpException(null, 'Signature service disabled.');
        }

        $sourcePath = $contract->getSignedPath() ?? $contract->getPath();
        if (!is_file($sourcePath)) {
            throw new \RuntimeException(sprintf('Contract source file not found: %s', $sourcePath));
        }

        $payload = $this->requestSignature($contract);
        if ($payload === null) {
            $payload = file_get_contents($sourcePath) ?: '';
        }

        $signedPath = sprintf('%s/%s-signed.pdf', rtrim($this->contractsStoragePath, '/'), $contract->getId()->toRfc4122());
        $this->filesystem->dumpFile($signedPath, $payload);
        $hash = hash('sha256', $payload);

        return new SignatureResult($signedPath, $hash, new DateTimeImmutable('now'));
    }

    private function requestSignature(Contract $contract): ?string
    {
        $baseUrl = $this->signatureBaseUrl ? rtrim($this->signatureBaseUrl, '/') : null;
        if ($baseUrl === null || $this->signatureToken === null) {
            $this->logger->warning('Signature client misconfigured. Falling back to local copy.', [
                'contract_uuid' => (string) $contract->getId(),
            ]);

            return null;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf('%s/contracts/sign', $baseUrl), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->signatureToken),
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'contract_uuid' => (string) $contract->getId(),
                    'hash' => $contract->getHash(),
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Signature request failed', [
                'contract_uuid' => (string) $contract->getId(),
                'error' => $exception->getMessage(),
            ]);

            throw new ServiceUnavailableHttpException(null, 'Signature service unavailable.', $exception);
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->error('Signature service responded with error status', [
                'status' => $response->getStatusCode(),
                'contract_uuid' => (string) $contract->getId(),
            ]);

            throw new ServiceUnavailableHttpException(null, 'Signature service error.');
        }

        $data = $response->toArray(false);
        if (!isset($data['document'])) {
            $this->logger->warning('Signature response missing document payload, using original file.', [
                'contract_uuid' => (string) $contract->getId(),
            ]);

            return null;
        }

        $decoded = base64_decode((string) $data['document'], true);
        if ($decoded === false) {
            $this->logger->error('Unable to decode signature payload.', [
                'contract_uuid' => (string) $contract->getId(),
            ]);

            throw new ServiceUnavailableHttpException(null, 'Invalid signature payload.');
        }

        return $decoded;
    }
}
