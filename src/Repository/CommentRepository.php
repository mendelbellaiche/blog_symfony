<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

//    /**
//     * @return Comment[] Returns an array of Comment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Comment
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Commentaires pas encore vérifiés qui contiennent peut-être un des termes.
     *
     * @param string[] $terms
     *
     * @return Comment[]
     */
    public function findModerationCandidates(array $terms): array
    {
        if (!$terms) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->addSelect('a', 'u')
            ->innerJoin('c.article', 'a')
            ->innerJoin('c.author', 'u')
            ->where('c.moderatedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC');

        $conditions = $qb->expr()->orX();
        foreach (array_values($terms) as $i => $term) {
            $conditions->add('c.content LIKE :term' . $i);
            $qb->setParameter('term' . $i, '%' . addcslashes(rtrim($term, '*'), '%_\\') . '%');
        }

        return $qb->andWhere($conditions)->getQuery()->getResult();
    }
}
