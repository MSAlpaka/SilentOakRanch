<?php

namespace App\Service;

use App\Entity\AuditLog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly string $auditStoragePath
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function log(object $entity, string $action, array $meta = []): AuditLog
    {
        $entityType = $this->resolveEntityType($entity);
        $entityId = $this->resolveEntityId($entity);
        $hash = $this->extractHash($meta);
        $userIdentifier = $this->security->getUser()?->getUserIdentifier();
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        $entry = new AuditLog($entityType, $entityId, $action, $hash, $userIdentifier, $ip, $meta);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $this->replicateToWorm($entry);

        return $entry;
    }

    private function resolveEntityType(object $entity): string
    {
        $class = $entity::class;
        $parts = explode('\\', $class);

        return strtoupper((string) array_pop($parts));
    }

    private function resolveEntityId(object $entity): string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if ($id instanceof \Stringable) {
                return (string) $id;
            }

            if (is_scalar($id)) {
                return (string) $id;
            }
        }

        return spl_object_hash($entity);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function extractHash(array $meta): ?string
    {
        $hash = $meta['hash'] ?? null;
        if (is_string($hash) && $hash !== '') {
            return $hash;
        }

        return null;
    }

    private function replicateToWorm(AuditLog $entry): void
    {
        $this->ensureDirectory();
        $filename = sprintf('%s/%s-audit.log', rtrim($this->auditStoragePath, '/'), $entry->getTimestamp()->format('Y-m-d'));
        $payload = [
            'id' => (string) $entry->getId(),
            'timestamp' => $entry->getTimestamp()->format(DateTimeImmutable::ATOM),
            'entity_type' => $entry->getEntityType(),
            'entity_id' => $entry->getEntityId(),
            'action' => $entry->getAction(),
            'hash' => $entry->getHash(),
            'user' => $entry->getUserIdentifier(),
            'ip' => $entry->getIpAddress(),
            'meta' => $entry->getMeta(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $digest = hash('sha256', $json);
        $record = json_encode($payload + ['digest' => $digest], JSON_THROW_ON_ERROR) . PHP_EOL;

        try {
            file_put_contents($filename, $record, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to append audit log replica.', [
                'file' => $filename,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureDirectory(): void
    {
        if ($this->filesystem->exists($this->auditStoragePath)) {
            return;
        }

        $this->filesystem->mkdir($this->auditStoragePath, 0700);
    }
}
