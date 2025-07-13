<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @return Subscription[]
     */
    public function findDueSubscriptions(\DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->andWhere('s.nextDue <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
