<?php

namespace App\Repository;

use App\Entity\Agreement;
use App\Entity\User;
use App\Enum\AgreementStatus;
use App\Enum\AgreementType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agreement::class);
    }

    public function findActiveByUserAndType(User $user, AgreementType $type): ?Agreement
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.type = :type')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('status', AgreementStatus::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
