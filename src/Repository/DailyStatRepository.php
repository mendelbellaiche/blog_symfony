<?php

namespace App\Repository;

use App\Entity\DailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyStat>
 */
class DailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyStat::class);
    }

    /**
     * @return DailyStat[]
     */
    public function findLastDays(int $days): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.day >= :from')
            ->setParameter('from', new \DateTimeImmutable(sprintf('-%d days', $days)))
            ->orderBy('s.day', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
