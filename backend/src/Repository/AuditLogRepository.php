<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return list<AuditLog>
     */
    public function findForEntity(string $entityType, string $entityId): array
    {
        return $this->createQueryBuilder('audit')
            ->andWhere('audit.entityType = :type')
            ->andWhere('audit.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('audit.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestForEntity(string $entityType, string $entityId): ?AuditLog
    {
        return $this->createQueryBuilder('audit')
            ->andWhere('audit.entityType = :type')
            ->andWhere('audit.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('audit.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
