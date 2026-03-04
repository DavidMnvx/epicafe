<?php

namespace App\Repository;

use App\Entity\MenuCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }

    /**
     * Récupère l'arbre publié (catégories racines + enfants) trié.
     * (Items/variants seront chargés quand tu les affiches ou via requêtes dédiées)
     */
    public function findMenuPublished(): array
{
    return $this->createQueryBuilder('c')
        ->leftJoin('c.items', 'i')
        ->addSelect('i')
        ->andWhere('c.isPublished = true')
        ->andWhere('i.isPublished = true')
        ->orderBy('c.position', 'ASC')
        ->addOrderBy('i.position', 'ASC')
        ->getQuery()
        ->getResult();
    }
}