<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private SluggerInterface $slugger,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // --- Utilisateurs ---
        $admin = new User();
        $admin->setEmail('admin@blog.fr')
            ->setPseudo('Admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($admin, 'password'));
        $manager->persist($admin);

        $users = [$admin];
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user$i@blog.fr")
                ->setPseudo($faker->userName())
                ->setPassword($this->hasher->hashPassword($user, 'password'));

            if ($i <= 2) {
                $user->setRoles(['ROLE_AUTHOR']);
            }

            $manager->persist($user);
            $users[] = $user;
        }

        // --- Catégories ---
        $categories = [];
        foreach (['Développement web', 'Symfony', 'PHP', 'JavaScript', 'DevOps'] as $name) {
            $category = new Category();
            $category->setName($name)
                ->setSlug($this->slugger->slug($name)->lower());
            $manager->persist($category);
            $categories[] = $category;
        }

        // --- Tags ---
        $tags = [];
        foreach (['Débutant', 'Avancé', 'Tutoriel', 'Astuce', 'Sécurité', 'Performance', 'Doctrine', 'Twig'] as $name) {
            $tag = new Tag();
            $tag->setName($name)
                ->setSlug($this->slugger->slug($name)->lower());
            $manager->persist($tag);
            $tags[] = $tag;
        }

        // --- Articles ---
        for ($i = 0; $i < 30; $i++) {
            $title = rtrim($faker->unique()->sentence(6), '.');

            $article = new Article();
            $article->setTitle($title)
                ->setSlug($this->slugger->slug($title)->lower())
                ->setContent(implode("\n\n", $faker->paragraphs(5)))
                ->setAuthor($faker->randomElement($users))
                ->setCategory($faker->randomElement($categories));

            foreach ($faker->randomElements($tags, $faker->numberBetween(1, 3)) as $tag) {
                $article->addTag($tag);
            }

            // 70 % publiés, 10 % programmés, 20 % brouillons
            $rand = $faker->numberBetween(1, 10);
            if ($rand <= 7) {
                $article->setStatus(ArticleStatus::Published)
                    ->setPublishedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months')));
            } elseif ($rand === 8) {
                $article->setStatus(ArticleStatus::Scheduled)
                    ->setPublishedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('+1 day', '+1 month')));
            }
            // sinon : reste en brouillon (valeur par défaut du constructeur)

            $manager->persist($article);

            // --- Commentaires (uniquement sur les articles publiés) ---
            if ($article->getStatus() === ArticleStatus::Published) {
                for ($j = 0; $j < $faker->numberBetween(0, 5); $j++) {
                    $comment = new Comment();
                    $comment->setContent($faker->paragraph())
                        ->setAuthor($faker->randomElement($users))
                        ->setArticle($article)
                        ->setApproved($faker->boolean(80));
                    $manager->persist($comment);
                }
            }
        }


        $manager->flush();
    }
}
