<?php

namespace App\Command;

use App\Entity\DailyStat;
use App\Repository\DailyStatRepository;
use App\Stats\DailyStatsCalculator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:compute-daily-stats',
    description: "Calcule les métriques d'une journée (par défaut : hier)",
)]
#[AsCronTask('5 0 * * *')]
final class ComputeDailyStatsCommand extends Command
{
    private const KEEP_VISITS_DAYS = 30;

    public function __construct(
        private DailyStatsCalculator $calculator,
        private DailyStatRepository $dailyStatRepository,
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Jour à calculer (AAAA-MM-JJ, ou "today")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $day = new \DateTimeImmutable($input->getOption('date') ?? 'yesterday')->setTime(0, 0);
        $values = $this->calculator->compute($day);

        $stat = $this->dailyStatRepository->findOneBy(['day' => $day]);
        if (!$stat) {
            $stat = new DailyStat($day);
            $this->em->persist($stat);
        }
        $stat->update($values);
        $this->em->flush();

        // Les empreintes de plus de 30 jours ne servent plus : on les supprime
        $this->connection->executeStatement(
            'DELETE FROM visit WHERE day < ?',
            [$day->modify(sprintf('-%d days', self::KEEP_VISITS_DAYS))->format('Y-m-d')],
        );

        $io->table(
            ['Jour', 'Visiteurs', 'Pages vues', 'Inscriptions', 'Commentaires'],
            [[$day->format('d/m/Y'), ...array_values($values)]],
        );

        return Command::SUCCESS;
    }
}
