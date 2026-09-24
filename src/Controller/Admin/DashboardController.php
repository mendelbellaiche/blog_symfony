<?php

namespace App\Controller\Admin;

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
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_comment_index');
        }

        return $this->redirectToRoute('admin_article_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('MonBlog · Administration');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Contenu');
        yield MenuItem::linkTo(ArticleCrudController::class, 'Articles', 'fa fa-newspaper');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-folder')
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'fa fa-tags')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Communauté')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(CommentCrudController::class, 'Commentaires', 'fa fa-comments')
            ->setPermission('ROLE_ADMIN');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Retour au blog', 'fa fa-arrow-left', $this->generateUrl('article_index'));
    }

}
