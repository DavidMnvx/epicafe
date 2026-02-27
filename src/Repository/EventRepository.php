<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

   /** @return Event[] */
public function findUpcoming(int $limit = 6): array
{
    $now = new \DateTimeImmutable();

    return $this->createQueryBuilder('e')
        ->andWhere('e.isPublished = :published')
        ->andWhere('e.isRecurring = false')
        ->andWhere('e.startAt IS NOT NULL')
        ->andWhere('e.startAt >= :now')
        ->setParameter('published', true)
        ->setParameter('now', $now)
        ->orderBy('e.startAt', 'ASC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}


/** @return Event[] */
public function findPast(int $limit = 12): array
{
    $now = new \DateTimeImmutable();

    return $this->createQueryBuilder('e')
        ->andWhere('e.isPublished = :published')
        ->andWhere('e.isRecurring = false')
        ->andWhere('e.startAt IS NOT NULL')
        ->andWhere('e.startAt < :now')
        ->setParameter('published', true)
        ->setParameter('now', $now)
        ->orderBy('e.startAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/** @return Event[] */
public function findRecurring(int $limit = 3): array
{
    return $this->createQueryBuilder('e')
        ->andWhere('e.isPublished = :published')
        ->andWhere('e.isRecurring = true')
        ->setParameter('published', true)
        ->orderBy('e.recurringDayOfWeek', 'ASC')
        ->addOrderBy('e.recurringTime', 'ASC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}


    public function findOneBySlug(string $slug): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}