<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public const ARTICLES_PER_PAGE = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

//    /**
//     * @return Article[] Returns an array of Article objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Article
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function findPublishedPaginated(
        int $page,
        ?Category $category = null,
        ?Tag $tag = null,
        ?string $search = null,
    ): WindowPage {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('c', 'u')
            ->join('a.category', 'c')
            ->join('a.author', 'u')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if ($category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        if ($tag) {
            $qb->andWhere(':tag MEMBER OF a.tags')
                ->setParameter('tag', $tag);
        }

        if ($search) {
            $booleanQuery = $this->toBooleanQuery($search);

            if ($booleanQuery) {
                $qb->addSelect('MATCH_AGAINST(a.title, a.content, :search) AS HIDDEN score')
                    ->andWhere('MATCH_AGAINST(a.title, a.content, :search) > 0')
                    ->setParameter('search', $booleanQuery)
                    ->orderBy('score', 'DESC')
                    ->addOrderBy('a.publishedAt', 'DESC');
            } else {
                // Mots trop courts pour l'index FULLTEXT : on garde le LIKE
                $qb->andWhere('a.title LIKE :search OR a.content LIKE :search')
                    ->setParameter('search', '%' . addcslashes($search, '%_') . '%');
            }
        }

        $offset = ($page - 1) * self::ARTICLES_PER_PAGE;

        return new OffsetPaginator(fetchJoinCollection: false)->paginate($qb->getQuery(), new Window($offset, self::ARTICLES_PER_PAGE));
    }

    /**
     * @return Article[]
     */
    public function findScheduledToPublish(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[]
     */
    public function findAllImageNames(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.image')
            ->where('a.image IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Transforme "symfony doctrine" en "+symfony* +doctrine*"
     */
    private function toBooleanQuery(string $search): ?string
    {
        // On retire les caractères qui ont un sens spécial en mode booléen
        $clean = preg_replace('/[+\-<>()~*"@]+/', ' ', $search);

        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        // MySQL n'indexe pas les mots de moins de 3 caractères
        $words = array_filter($words, fn (string $word) => mb_strlen($word) >= 3);

        if (!$words) {
            return null;
        }

        return implode(' ', array_map(fn (string $word) => '+' . $word . '*', $words));
    }

}
