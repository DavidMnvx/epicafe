<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    public function findOneByKey(string $key): ?SiteSetting
    {
        return $this->findOneBy(['key' => $key]);
    }

    /**
     * Charge toutes les settings une fois et retourne un map [key => value].
     * Utilisé par la Twig extension pour éviter N requêtes par page.
     *
     * @return array<string, string|null>
     */
    public function loadAllAsMap(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.key', 's.value')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['key']] = $row['value'];
        }

        return $map;
    }

    /**
     * @return SiteSetting[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.groupName', 'ASC')
            ->addOrderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
