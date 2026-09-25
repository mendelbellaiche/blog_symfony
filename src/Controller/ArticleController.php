<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Comment;
use App\Entity\User;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Security\Voter\ArticleVoter;

final class ArticleController extends AbstractController
{
    public function __construct(private ArticleRepository $articleRepository)
    {
    }

    #[Route('/', name: 'article_index')]
    public function index(#[MapQueryParameter] int $page = 1): Response
    {
        return $this->renderList($page, 'Derniers articles');
    }

    #[Route('/categorie/{slug:category}', name: 'article_by_category')]
    public function byCategory(Category $category, #[MapQueryParameter] int $page = 1): Response
    {
        return $this->renderList($page, 'Catégorie : ' . $category->getName(), category: $category);
    }

    #[Route('/tag/{slug:tag}', name: 'article_by_tag')]
    public function byTag(Tag $tag, #[MapQueryParameter] int $page = 1): Response
    {
        return $this->renderList($page, 'Tag : #' . $tag->getName(), tag: $tag);
    }

    #[Route('/recherche', name: 'article_search')]
    public function search(
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] int $page = 1,
    ): Response {
        $q = trim($q);

        if ($q === '') {
            return $this->redirectToRoute('article_index');
        }

        return $this->renderList($page, sprintf('Résultats pour « %s »', $q), search: $q);
    }

    #[Route('/article/{slug:article}', name: 'article_show', methods: ['GET', 'POST'])]
    public function show(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $auditLogger,
        #[CurrentUser] ?User $user,
    ): Response {
        // Un article non publié n'est visible que par son auteur et l'admin
        if (!$article->isPublished() && !$this->isGranted(ArticleVoter::EDIT, $article)) {
            throw $this->createNotFoundException();
        }

        $commentForm = null;

        // Pas de commentaires sur un article qui n'est pas encore en ligne
        if ($article->isPublished()) {
            $comment = new Comment();
            $commentForm = $this->createForm(CommentType::class, $comment);
            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted()) {
                $this->denyAccessUnlessGranted('ROLE_USER');
            }

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $comment->setAuthor($user)
                    ->setArticle($article)
                    ->setApproved($this->isGranted('ROLE_ADMIN'));

                $em->persist($comment);
                $em->flush();

                $auditLogger->info('Nouveau commentaire', [
                    'user' => $user->getEmail(),
                    'article' => $article->getSlug(),
                    'approved' => $comment->isApproved(),
                ]);

                $this->addFlash('success', $comment->isApproved()
                    ? 'Ton commentaire a été publié.'
                    : 'Merci ! Ton commentaire sera visible après validation par un modérateur.'
                );

                return $this->redirectToRoute('article_show', [
                    'slug' => $article->getSlug(),
                    '_fragment' => 'comments',
                ]);
            }
        }

        $response = $this->render('article/show.html.twig', [
            'article' => $article,
            'commentForm' => $commentForm,
        ]);

        // Les moteurs de recherche ne doivent jamais indexer un aperçu
        if (!$article->isPublished()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function renderList(
        int $page,
        string $title,
        ?Category $category = null,
        ?Tag $tag = null,
        ?string $search = null,
    ): Response {
        $page = max(1, $page);
        $articles = $this->articleRepository->findPublishedPaginated($page, $category, $tag, $search);
        $pages = (int) ceil($articles->getTotalCount() / ArticleRepository::ARTICLES_PER_PAGE);

        if ($page > max(1, $pages)) {
            throw $this->createNotFoundException();
        }

        return $this->render('article/index.html.twig', [
            'articles' => $articles,
            'title' => $title,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'total' => $articles->getTotalCount(),
        ]);
    }

}
