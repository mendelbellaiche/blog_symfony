<?php

namespace App\Tests\Security\Voter;

use App\Entity\Article;
use App\Entity\User;
use App\Security\Voter\ArticleVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ArticleVoterTest extends TestCase
{
    public static function attributes(): iterable
    {
        yield 'modification' => [ArticleVoter::EDIT];
        yield 'suppression' => [ArticleVoter::DELETE];
    }

    #[DataProvider('attributes')]
    public function testAuthorCanManageOwnArticle(string $attribute): void
    {
        $author = $this->createUser('auteur@blog.fr');
        $article = $this->createArticle($author);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($author, $article, $attribute),
        );
    }

    #[DataProvider('attributes')]
    public function testAuthorCannotManageSomeoneElsesArticle(string $attribute): void
    {
        $author = $this->createUser('auteur@blog.fr');
        $otherAuthor = $this->createUser('autre@blog.fr');
        $article = $this->createArticle($otherAuthor);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($author, $article, $attribute),
        );
    }

    #[DataProvider('attributes')]
    public function testAdminCanManageAnyArticle(string $attribute): void
    {
        $admin = $this->createUser('admin@blog.fr');
        $article = $this->createArticle($this->createUser('auteur@blog.fr'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($admin, $article, $attribute, isAdmin: true),
        );
    }

    #[DataProvider('attributes')]
    public function testAnonymousCannotManageArticles(string $attribute): void
    {
        $article = $this->createArticle($this->createUser('auteur@blog.fr'));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote(null, $article, $attribute),
        );
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $author = $this->createUser('auteur@blog.fr');
        $article = $this->createArticle($author);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->vote($author, $article, 'ARTICLE_VIEW'),
        );
    }

    private function vote(?User $user, Article $article, string $attribute, bool $isAdmin = false): int
    {
        // On simule la vérification du rôle admin, sans charger Symfony
        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn($isAdmin);

        $voter = new ArticleVoter($accessDecisionManager);

        $token = $user
            ? new UsernamePasswordToken($user, 'main', $user->getRoles())
            : new NullToken();

        return $voter->vote($token, $article, [$attribute]);
    }

    private function createUser(string $email): User
    {
        return (new User())->setEmail($email)->setPseudo($email);
    }

    private function createArticle(User $author): Article
    {
        return (new Article())->setTitle('Un article')->setAuthor($author);
    }
}
