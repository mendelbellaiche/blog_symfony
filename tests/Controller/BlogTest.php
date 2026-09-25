<?php

namespace App\Tests\Controller;

use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BlogTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomepageDisplaysPublishedArticles(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Derniers articles');
        self::assertSelectorCount(ArticleRepository::ARTICLES_PER_PAGE, 'article');
    }

    public function testPageOutOfRangeReturns404(): void
    {
        $this->client->request('GET', '/?page=999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDraftArticleIsNotAccessible(): void
    {
        $draft = static::getContainer()->get(ArticleRepository::class)
            ->findOneBy(['status' => ArticleStatus::Draft]);

        if (!$draft) {
            self::markTestSkipped('Aucun brouillon dans les fixtures.');
        }

        $this->client->request('GET', '/article/' . $draft->getSlug());

        self::assertResponseStatusCodeSame(404);
    }

    public function testSearchWithoutKeywordRedirectsToHomepage(): void
    {
        $this->client->request('GET', '/recherche?q=');

        self::assertResponseRedirects('/');
    }

    public function testAnonymousIsRedirectedToLoginFromAdmin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testAuthorCannotAccessCommentModeration(): void
    {
        $this->loginAs('user1@blog.fr');
        $this->client->request('GET', '/admin/comment');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessCommentModeration(): void
    {
        $this->loginAs('admin@blog.fr');
        $this->client->request('GET', '/admin/comment');

        self::assertResponseIsSuccessful();
    }

    private function loginAs(string $email): void
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $this->client->loginUser($user);
    }

    public function testAdminCanPreviewDraft(): void
    {
        $draft = static::getContainer()->get(ArticleRepository::class)
            ->findOneBy(['status' => ArticleStatus::Draft]);

        if (!$draft) {
            self::markTestSkipped('Aucun brouillon dans les fixtures.');
        }

        $this->loginAs('admin@blog.fr');
        $this->client->request('GET', '/article/' . $draft->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Aperçu');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }
}
