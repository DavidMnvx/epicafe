<?php

namespace App\Repository;

use App\Entity\Partner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }

 
    /** @return Partner[] */
    public function findPublished(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Partner[] */
    public function findPublishedOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->addSelect("
                CASE
                    WHEN p.type = :premium THEN 0
                    WHEN p.type = :partner THEN 1
                    WHEN p.type = :secondary THEN 2
                    ELSE 3
                END AS HIDDEN typeOrder
            ")
            ->setParameter('premium', Partner::TYPE_PREMIUM)
            ->setParameter('partner', Partner::TYPE_PARTNER)
            ->setParameter('secondary', Partner::TYPE_SECONDARY)
            ->addOrderBy('typeOrder', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

}