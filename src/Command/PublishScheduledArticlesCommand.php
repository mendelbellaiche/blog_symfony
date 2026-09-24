<?php

namespace App\Command;

use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'app:publish-scheduled-articles',
    description: 'Publie les articles programmés dont la date de publication est passée',
)]
#[AsPeriodicTask(frequency: '1 minute')]
final class PublishScheduledArticlesCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $articles = $this->articleRepository->findScheduledToPublish(new \DateTimeImmutable());

        foreach ($articles as $article) {
            $article->setStatus(ArticleStatus::Published);
            $io->writeln(sprintf('Publication : %s', $article->getTitle()));
        }

        $this->em->flush();

        $io->success(sprintf('%d article(s) publié(s).', count($articles)));

        return Command::SUCCESS;
    }
}
