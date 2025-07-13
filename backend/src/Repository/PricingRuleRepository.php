<?php

namespace App\Repository;

use App\Entity\PricingRule;
use App\Enum\BookingType;
use App\Enum\PricingRuleType;
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

    public function findDefault(BookingType $type, \DateTimeInterface $dateFrom): ?PricingRule
    {
        $ruleType = PricingRuleType::tryFrom($type->value);
        if (null === $ruleType) {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isDefault = true')
            ->andWhere('(p.activeFrom IS NULL OR p.activeFrom <= :date)')
            ->andWhere('(p.activeTo IS NULL OR p.activeTo >= :date)')
            ->setParameter('type', $ruleType)
            ->setParameter('date', $dateFrom)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
