<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopCategory>
 */
class ShopCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopCategory::class);
    }

    /**
     * @return ShopCategory[]
     */
    public function findAllPublishedOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isPublished = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégorie immédiatement avant celle donnée (par position).
     */
    public function findPreviousByPosition(ShopCategory $category): ?ShopCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.position < :pos')
            ->setParameter('pos', $category->getPosition())
            ->orderBy('c.position', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Catégorie immédiatement après celle donnée (par position).
     */
    public function findNextByPosition(ShopCategory $category): ?ShopCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.position > :pos')
            ->setParameter('pos', $category->getPosition())
            ->orderBy('c.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
