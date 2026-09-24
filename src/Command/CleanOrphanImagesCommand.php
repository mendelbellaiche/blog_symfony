<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:clean-orphan-images',
    description: "Supprime les images qui ne sont plus utilisées par aucun article",
)]
#[AsCronTask('0 3 * * *')]
final class CleanOrphanImagesCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public/uploads/articles')]
        private string $uploadDir,
        private LoggerInterface $auditLogger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les fichiers concernés sans les supprimer')
            ->addOption('min-age', null, InputOption::VALUE_REQUIRED, 'Âge minimum des fichiers à supprimer, en minutes', 60);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $minAge = (int) $input->getOption('min-age');

        if (!is_dir($this->uploadDir)) {
            $io->note("Le dossier d'upload n'existe pas, rien à nettoyer.");

            return Command::SUCCESS;
        }

        // array_flip transforme ['a.jpg', 'b.jpg'] en ['a.jpg' => 0, 'b.jpg' => 1]
        // pour vérifier très rapidement si un fichier est utilisé avec isset()
        $usedImages = array_flip($this->articleRepository->findAllImageNames());

        $files = new Finder()
            ->files()
            ->in($this->uploadDir)
            ->date(sprintf('until %d minutes ago', $minAge));

        $count = 0;
        foreach ($files as $file) {
            $name = $file->getRelativePathname();

            if (isset($usedImages[$name])) {
                continue;
            }

            $io->writeln(sprintf('%s %s', $dryRun ? '[simulation]' : 'Suppression :', $name));

            if (!$dryRun) {
                $this->filesystem->remove($file->getPathname());
                $this->auditLogger->info('Image orpheline supprimée', ['file' => $name]);
            }

            $count++;
        }

        $io->success(sprintf(
            $dryRun ? '%d fichier(s) orphelin(s) trouvé(s).' : '%d fichier(s) supprimé(s).',
            $count
        ));

        return Command::SUCCESS;
    }
}
