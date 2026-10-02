<?php

namespace App\Controller\Admin;

use App\Entity\ModerationWord;
use App\Enum\ModerationWordType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ModerationWord>
 */
#[IsGranted('ROLE_ADMIN')]
class ModerationWordCrudController extends AbstractCrudController
{
    private const CHOICES = [
        'À surveiller' => ModerationWordType::Watch,
        'Interdit' => ModerationWordType::Forbidden,
    ];

    public static function getEntityFqcn(): string
    {
        return ModerationWord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Terme')
            ->setEntityLabelInPlural('Listes de mots')
            ->setSearchFields(['term'])
            ->setDefaultSort(['type' => 'ASC', 'term' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('type', 'Liste')->setChoices(self::CHOICES));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('term', 'Mot ou expression')
            ->setHelp('Insensible à la casse et aux accents. Termine par * pour inclure les mots qui commencent ainsi (ex. : arnaq*).');

        yield ChoiceField::new('type', 'Liste')
            ->setChoices(self::CHOICES)
            ->renderAsBadges([
                ModerationWordType::Watch->value => 'warning',
                ModerationWordType::Forbidden->value => 'danger',
            ]);
    }
}
