<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\StallUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function hasOverlap(StallUnit $stallUnit, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $qb = $this->createQueryBuilder('b');
        $qb->select('count(b.id)')
            ->where('b.stallUnit = :stallUnit')
            ->andWhere('b.startDate < :end')
            ->andWhere('b.endDate > :start')
            ->setParameter('stallUnit', $stallUnit)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
