<?php

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

class HealthCheckService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $agreementsStoragePath,
        private readonly string $auditStoragePath
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function check(): array
    {
        $checks = [];
        $overallHealthy = true;

        $checks['database'] = $this->checkDatabase();
        $overallHealthy = $overallHealthy && ($checks['database']['ok'] ?? false);

        $checks['queue'] = $this->checkQueue();
        $overallHealthy = $overallHealthy && ($checks['queue']['ok'] ?? false);

        $checks['storage'] = $this->checkStorage();
        $overallHealthy = $overallHealthy && ($checks['storage']['ok'] ?? false);

        return [
            'ok' => $overallHealthy,
            'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return [
                'ok' => true,
                'message' => 'Database connection healthy.',
            ];
        } catch (DBALException $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkQueue(): array
    {
        try {
            $result = $this->connection->executeQuery(
                'SELECT COUNT(*) AS pending, EXTRACT(EPOCH FROM (NOW() - MIN(available_at))) AS oldest_delay FROM messenger_messages'
            );

            $row = $result->fetchAssociative();

            $pending = isset($row['pending']) ? (int) $row['pending'] : 0;
            $oldestDelay = isset($row['oldest_delay']) ? max(0, (int) $row['oldest_delay']) : 0;

            return [
                'ok' => true,
                'pending_messages' => $pending,
                'oldest_delay_seconds' => $oldestDelay,
            ];
        } catch (DBALException|\Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkStorage(): array
    {
        $paths = [
            'agreements' => $this->agreementsStoragePath,
            'audit' => $this->auditStoragePath,
        ];

        $details = [];
        $ok = true;

        foreach ($paths as $label => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $readable = $exists && is_readable($path);

            $details[$label] = [
                'path' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'writable' => $writable,
            ];

            $ok = $ok && $exists && $readable && $writable;
        }

        return [
            'ok' => $ok,
            'paths' => $details,
        ];
    }
}
