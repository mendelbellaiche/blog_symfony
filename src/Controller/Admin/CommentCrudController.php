<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Moderation\ModerationMatcher;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[IsGranted('ROLE_ADMIN')]
class CommentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commentaire')
            ->setEntityLabelInPlural('Commentaires')
            // Les commentaires en attente en premier, puis les plus récents
            ->setDefaultSort(['approved' => 'ASC', 'createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Un commentaire est toujours écrit depuis le blog, pas depuis l'admin
        return $actions->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('approved', 'Approuvé'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextareaField::new('content', 'Commentaire');
        yield AssociationField::new('article', 'Article')->hideOnForm();
        yield AssociationField::new('author', 'Auteur')->hideOnForm();
        yield BooleanField::new('approved', 'Approuvé');
        yield DateTimeField::new('createdAt', 'Date')->hideOnForm();
    }

    #[AdminRoute('/moderation', name: 'moderation')]
    public function moderation(CommentRepository $commentRepository, ModerationMatcher $matcher): Response
    {
        $items = [];

        foreach ($commentRepository->findModerationCandidates($matcher->allTerms()) as $comment) {
            $result = $matcher->analyze($comment->getContent());

            if ($result['forbidden'] || $result['watch']) {
                $items[] = ['comment' => $comment, ...$result];
            }
        }

        return $this->render('admin/comment_moderation.html.twig', ['items' => $items]);
    }

    #[AdminRoute('/{entityId:comment.id}/moderate', name: 'moderate')]
    public function moderate(
        Comment $comment,
        Request $request,
        EntityManagerInterface $em,
        LoggerInterface $auditLogger,
    ): Response {
        if (!$request->isMethod('POST')
            || !$this->isCsrfTokenValid('moderate' . $comment->getId(), $request->getPayload()->getString('_token'))
        ) {
            throw $this->createAccessDeniedException();
        }

        $decision = $request->getPayload()->getString('decision');
        $context = [
            'comment' => $comment->getId(),
            'author' => $comment->getAuthor()->getEmail(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ];

        match ($decision) {
            'keep' => $comment->setApproved(true)->setModeratedAt(new \DateTimeImmutable()),
            'hide' => $comment->setApproved(false)->setModeratedAt(new \DateTimeImmutable()),
            'delete' => $em->remove($comment),
            default => throw $this->createNotFoundException(),
        };

        $em->flush();
        $auditLogger->info(sprintf('Modération : %s', $decision), $context);

        $this->addFlash('success', match ($decision) {
            'keep' => 'Commentaire conservé et publié.',
            'hide' => 'Commentaire masqué.',
            'delete' => 'Commentaire supprimé.',
        });

        return $this->redirectToRoute('admin_comment_moderation');
    }
}
