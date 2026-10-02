<?php

namespace App\Entity;

use App\Enum\ModerationWordType;
use App\Repository\ModerationWordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ModerationWordRepository::class)]
#[ORM\UniqueConstraint(name: 'moderation_word_unique', columns: ['term', 'type'])]
#[UniqueEntity(fields: ['term', 'type'], message: 'Ce terme existe déjà dans cette liste.')]
class ModerationWord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le terme ne peut pas être vide.')]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[^*]+\*?$/', message: "Le caractère * n'est autorisé qu'à la fin du terme.")]
    private ?string $term = null;

    #[ORM\Column(length: 20, enumType: ModerationWordType::class)]
    private ?ModerationWordType $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): static
    {
        // Enregistré en minuscules, avec des espaces simples
        $this->term = $term === null ? null : mb_strtolower(preg_replace('/\s+/', ' ', trim($term)));

        return $this;
    }

    public function getType(): ?ModerationWordType
    {
        return $this->type;
    }

    public function setType(?ModerationWordType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function __toString(): string
    {
        return $this->term ?? '';
    }
}
