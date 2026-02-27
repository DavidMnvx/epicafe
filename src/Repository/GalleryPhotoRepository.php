<?php

namespace App\Repository;

use App\Entity\GalleryPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class GalleryPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryPhoto::class);
    }

    /** @return GalleryPhoto[] */
    public function findPublished(?int $year = null, string $sort = 'new', int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true');

        if ($year) {
            // Filtre année via range (compatible DQL partout)
            $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
            $end   = $start->modify('+1 year');

            $qb->andWhere('p.takenAt >= :start')
               ->andWhere('p.takenAt < :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        }

        // Tri: takenAt d’abord, sinon createdAt
        // NOTE: COALESCE en DQL peut poser souci selon versions,
        // donc on garde simple et fiable.
        if ($sort === 'old') {
            $qb->addOrderBy('p.takenAt', 'ASC')
               ->addOrderBy('p.createdAt', 'ASC')
               ->addOrderBy('p.id', 'ASC');
        } else {
            $qb->addOrderBy('p.takenAt', 'DESC')
               ->addOrderBy('p.createdAt', 'DESC')
               ->addOrderBy('p.id', 'DESC');
        }

        return $qb->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return int[] */
    public function findAvailableYears(): array
    {
        // Ici on passe en SQL DBAL: EXTRACT est OK côté Postgres
        $conn = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $sql = <<<SQL
SELECT DISTINCT EXTRACT(YEAR FROM taken_at) AS y
FROM {$table}
WHERE taken_at IS NOT NULL
  AND is_published = true
ORDER BY y DESC
SQL;

        // fetchFirstColumn => array de "2026", "2025", etc.
        $years = $conn->fetchFirstColumn($sql);

        // cast clean en int
        return array_values(array_map(
            fn($y) => (int) $y,
            array_filter($years, fn($y) => $y !== null)
        ));
    }
}