<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Contract;
use App\Enum\ContractStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 */
class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    public function findOneByBooking(Booking $booking): ?Contract
    {
        return $this->findOneBy(['booking' => $booking]);
    }

    /**
     * @return list<Contract>
     */
    public function findSignedContracts(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', ContractStatus::SIGNED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
