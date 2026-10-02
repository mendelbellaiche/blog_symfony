<?php

namespace App\EventListener;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::TERMINATE)]
final class VisitTrackerListener
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|curl|wget|python|http-?client|headless|preview|monitor|scanner/i';

    public function __construct(
        private Connection $connection,
        private Security $security,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {

        $request = $event->getRequest();
        $response = $event->getResponse();
        $userAgent = (string) $request->headers->get('User-Agent');

        // On ne compte que les vraies pages HTML affichées avec succès
        if (!$request->isMethod('GET')
            || $response->getStatusCode() !== 200
            || !str_contains((string) $response->headers->get('Content-Type'), 'text/html')
        ) {
            return;
        }

        // Ni l'admin, ni les outils de debug (/_profiler, /_wdt)
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/_')) {
            return;
        }

        // Ni les préchargements de Turbo, ni les robots
        if ($request->headers->has('X-Sec-Purpose')
            || $userAgent === ''
            || preg_match(self::BOT_PATTERN, $userAgent)
        ) {
            return;
        }

        // Ni les visites des auteurs et de l'admin
        if ($this->security->isGranted('ROLE_AUTHOR')) {
            return;
        }

        $day = new \DateTimeImmutable()->format('Y-m-d');

        // Empreinte anonyme : impossible de retrouver l'IP, et différente chaque jour
        $visitorHash = hash('sha256', implode('|', [
            $request->getClientIp(),
            $userAgent,
            $day,
            $this->secret,
        ]));

        try {
            $this->connection->executeStatement(
                'INSERT INTO visit (day, visitor_hash, page_views) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE page_views = page_views + 1',
                [$day, $visitorHash],
            );
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
