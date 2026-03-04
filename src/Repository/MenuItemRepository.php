<?php

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    // Exemple de méthode utile
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')->addSelect('c')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}