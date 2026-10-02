<?php

namespace App\Entity;

use App\Repository\DailyStatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyStatRepository::class)]
class DailyStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private \DateTimeImmutable $day;

    #[ORM\Column]
    private int $visitors = 0;

    #[ORM\Column]
    private int $pageViews = 0;

    #[ORM\Column]
    private int $registrations = 0;

    #[ORM\Column]
    private int $comments = 0;

    public function __construct(\DateTimeImmutable $day)
    {
        $this->day = $day;
    }

    /**
     * @param array{visitors: int, pageViews: int, registrations: int, comments: int} $values
     */
    public function update(array $values): void
    {
        $this->visitors = $values['visitors'];
        $this->pageViews = $values['pageViews'];
        $this->registrations = $values['registrations'];
        $this->comments = $values['comments'];
    }

    public function getDay(): \DateTimeImmutable { return $this->day; }
    public function getVisitors(): int { return $this->visitors; }
    public function getPageViews(): int { return $this->pageViews; }
    public function getRegistrations(): int { return $this->registrations; }
    public function getComments(): int { return $this->comments; }
}
