<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoogleReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleReview>
 */
class GoogleReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleReview::class);
    }

    /**
     * @return GoogleReview[]
     */
    public function findAllPublishedOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isPublished = true')
            ->orderBy('r.position', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les N avis publiés les plus récents (par date de l'avis ou de création).
     *
     * @return GoogleReview[]
     */
    public function findLatestPublished(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isPublished = true')
            ->orderBy('r.position', 'ASC')
            ->addOrderBy('r.reviewDate', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales sur les avis publiés.
     *
     * @return array{count:int, average:float}
     */
    public function getPublishedStats(): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS reviews_count', 'AVG(r.rating) AS avg_rating')
            ->where('r.isPublished = true')
            ->getQuery()
            ->getSingleResult();

        return [
            'count'   => (int) ($row['reviews_count'] ?? 0),
            'average' => round((float) ($row['avg_rating'] ?? 0), 1),
        ];
    }

    public function findPreviousByPosition(GoogleReview $review): ?GoogleReview
    {
        return $this->createQueryBuilder('r')
            ->where('r.position < :pos')
            ->setParameter('pos', $review->getPosition())
            ->orderBy('r.position', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextByPosition(GoogleReview $review): ?GoogleReview
    {
        return $this->createQueryBuilder('r')
            ->where('r.position > :pos')
            ->setParameter('pos', $review->getPosition())
            ->orderBy('r.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
