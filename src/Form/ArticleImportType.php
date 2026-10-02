<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class ArticleImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csv', FileType::class, [
                'label' => 'Fichier CSV',
                'constraints' => [
                    new NotNull(message: 'Choisis un fichier CSV.'),
                    new File(
                        maxSize: '10M',
                        extensions: ['csv'],
                        extensionsMessage: 'Le fichier doit être au format CSV.',
                    ),
                ],
            ])
            ->add('images', FileType::class, [
                'label' => "Dossier d'images",
                'required' => false,
                'multiple' => true,
                'help' => "Optionnel. Sélectionne le dossier « images » de l'export. Seuls les fichiers JPG, PNG et WebP sont importés.",
                'attr' => ['webkitdirectory' => 'webkitdirectory'],
            ]);
    }
}
