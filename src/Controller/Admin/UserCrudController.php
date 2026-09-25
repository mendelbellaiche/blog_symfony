<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<User>
 */
#[IsGranted('ROLE_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $auditLogger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['pseudo', 'email'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Suppression possible uniquement pour un compte sans article ni commentaire,
        // et jamais pour son propre compte
        $canDelete = fn (User $user) => $user !== $this->getUser()
            && $user->getArticles()->isEmpty()
            && $user->getComments()->isEmpty();

        return $actions->update(
            Crud::PAGE_INDEX,
            Action::DELETE,
            fn (Action $action) => $action->displayIf($canDelete),
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('pseudo', 'Pseudo');
        yield EmailField::new('email', 'Email');

        yield ChoiceField::new('roles', 'Rôles')
            ->setChoices([
                'Lecteur' => 'ROLE_USER',
                'Auteur' => 'ROLE_AUTHOR',
                'Administrateur' => 'ROLE_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();

        yield TextField::new('plainPassword', 'Mot de passe')
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('attr', ['autocomplete' => 'new-password'])
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->setHelp($pageName === Crud::PAGE_EDIT
                ? 'Laisser vide pour conserver le mot de passe actuel.'
                : 'Au moins 8 caractères.')
            ->onlyOnForms();

        yield AssociationField::new('articles', 'Articles')->onlyOnIndex();
        yield AssociationField::new('comments', 'Commentaires')->onlyOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);

        $this->auditLogger->info("Utilisateur créé depuis l'admin", [
            'user' => $entityInstance->getEmail(),
            'roles' => $entityInstance->getRoles(),
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $passwordChanged = $entityInstance->getPlainPassword() !== null;

        $this->hashPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);

        $this->auditLogger->info("Utilisateur modifié depuis l'admin", [
            'user' => $entityInstance->getEmail(),
            'roles' => $entityInstance->getRoles(),
            'password_changed' => $passwordChanged,
            'admin' => $this->getUser()?->getUserIdentifier(),
        ]);
    }

    private function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();

        if ($plainPassword) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $user->setPlainPassword(null);
        }
    }
}
