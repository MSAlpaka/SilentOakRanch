<?php

namespace App\Repository;

use App\Entity\StallUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StallUnit>
 */
class StallUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StallUnit::class);
    }

    /**
     * @return StallUnit[]
     */
    public function findAll(): array
    {
        return parent::findAll();
    }
}
