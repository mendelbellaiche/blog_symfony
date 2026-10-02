<?php

namespace App\Stats;

use Doctrine\DBAL\Connection;

final class DailyStatsCalculator
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{visitors: int, pageViews: int, registrations: int, comments: int}
     */
    public function compute(\DateTimeImmutable $day): array
    {
        $start = $day->setTime(0, 0);
        $range = [$start->format('Y-m-d H:i:s'), $start->modify('+1 day')->format('Y-m-d H:i:s')];

        $visits = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS visitors, COALESCE(SUM(page_views), 0) AS page_views FROM visit WHERE day = ?',
            [$start->format('Y-m-d')],
        );

        return [
            'visitors' => (int) $visits['visitors'],
            'pageViews' => (int) $visits['page_views'],
            'registrations' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `user` WHERE created_at >= ? AND created_at < ?',
                $range,
            ),
            'comments' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM comment WHERE created_at >= ? AND created_at < ?',
                $range,
            ),
        ];
    }
}
