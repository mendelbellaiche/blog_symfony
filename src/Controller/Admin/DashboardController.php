<?php

namespace App\Controller\Admin;

use App\Moderation\ModerationMatcher;
use App\Repository\CommentRepository;
use App\Repository\DailyStatRepository;
use App\Stats\DailyStatsCalculator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_AUTHOR')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private DailyStatsCalculator $calculator,
        private DailyStatRepository $dailyStatRepository,
        private CommentRepository $commentRepository,
        private ModerationMatcher $matcher,
    ) {
    }

    public function index(): Response
    {
        // Les auteurs n'ont pas accès aux statistiques
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/dashboard.html.twig', [
            'today' => $this->calculator->compute(new \DateTimeImmutable('today')),
            'stats' => $this->dailyStatRepository->findLastDays(30),
        ]);
    }
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->getParameter('app.blog_name') . ' · Administration');
    }

    public function configureMenuItems(): iterable
    {

        yield MenuItem::section('Principal')->setPermission('ROLE_ADMIN');

        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-chart-line')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Contenu');
        yield MenuItem::linkTo(ArticleCrudController::class, 'Articles', 'fa fa-newspaper');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-folder')
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'fa fa-tags')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Communauté')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(CommentCrudController::class, 'Commentaires', 'fa fa-comments')
            ->setPermission('ROLE_ADMIN');

        $toModerate = MenuItem::linkToUrl('À modérer', 'fa fa-shield-halved', $this->generateUrl('admin_comment_moderation'))
            ->setPermission('ROLE_ADMIN');
        $count = $this->isGranted('ROLE_ADMIN') ? $this->countCommentsToModerate() : 0;
        if ($count > 0) {
            $toModerate->setBadge($count, 'danger');
        }
        yield $toModerate;

        yield MenuItem::linkTo(ModerationWordCrudController::class, 'Listes de mots', 'fa fa-ban')
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Retour au blog', 'fa fa-arrow-left', $this->generateUrl('article_index'));
    }

    private function countCommentsToModerate(): int
    {
        $count = 0;

        foreach ($this->commentRepository->findModerationCandidates($this->matcher->allTerms()) as $comment) {
            $result = $this->matcher->analyze($comment->getContent());
            if ($result['forbidden'] || $result['watch']) {
                $count++;
            }
        }

        return $count;
    }

}
