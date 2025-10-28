<?php

namespace App\Tests\Service;

use App\Entity\Contract;
use App\Repository\AuditLogRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('audit')]
class AuditLoggerTest extends KernelTestCase
{
    public function testLogAppendsWithoutOverwritingExistingEntries(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var Filesystem $filesystem */
        $filesystem = $container->get(Filesystem::class);
        /** @var string $auditPath */
        $auditPath = $container->getParameter('audit_storage_path');
        $filesystem->remove($auditPath);
        $filesystem->mkdir($auditPath);

        $entityManager = $container->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);

        /** @var AuditLogger $logger */
        $logger = $container->get(AuditLogger::class);
        /** @var AuditLogRepository $repository */
        $repository = $container->get(AuditLogRepository::class);

        $contract = new Contract();
        $logger->log($contract, 'CONTRACT_GENERATED', ['hash' => hash('sha256', 'first')]);
        $logger->log($contract, 'CONTRACT_VIEWED', ['hash' => hash('sha256', 'second')]);

        $entries = $repository->findForEntity('CONTRACT', (string) $contract->getId());

        self::assertCount(2, $entries);
        self::assertSame('CONTRACT_GENERATED', $entries[0]->getAction());
        self::assertSame('CONTRACT_VIEWED', $entries[1]->getAction());

        $files = glob(sprintf('%s/*.log', $auditPath));
        self::assertNotFalse($files);
        self::assertNotEmpty($files);
        $content = file_get_contents($files[0]);
        self::assertNotFalse($content);
        self::assertSame(2, substr_count(trim($content), PHP_EOL) + 1);
    }
}
