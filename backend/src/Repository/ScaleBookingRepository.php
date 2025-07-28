<?php

namespace App\Repository;

use App\Entity\ScaleBooking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScaleBooking>
 */
class ScaleBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScaleBooking::class);
    }

    /**
     * Checks if a booking already exists for the given date and time.
     */
    public function existsForDateTime(\DateTimeInterface $bookingDateTime): bool
    {
        return (bool) $this->createQueryBuilder('b')
            ->select('1')
            ->andWhere('b.bookingDateTime = :dt')
            ->setParameter('dt', $bookingDateTime)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
