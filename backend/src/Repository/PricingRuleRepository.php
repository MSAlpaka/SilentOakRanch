<?php

namespace App\Repository;

use App\Entity\PricingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingRule>
 */
class PricingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingRule::class);
    }

    /**
     * @return PricingRule[]
     */
    public function findDefaults(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isDefault = true')
            ->getQuery()
            ->getResult();
    }
}
