<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GoogleReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis client (Google Reviews) saisi manuellement via l'admin
 * et affiché dans le carousel de la section avis Google.
 */
#[ORM\Entity(repositoryClass: GoogleReviewRepository::class)]
#[ORM\Table(name: 'google_review')]
#[ORM\HasLifecycleCallbacks]
class GoogleReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom de l'auteur (ex : "Sophie M."). */
    #[ORM\Column(length: 120)]
    private string $author = '';

    /** Note sur 5 (1 à 5). */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 5])]
    private int $rating = 5;

    /** Texte de l'avis (peut être long). */
    #[ORM\Column(type: Types::TEXT)]
    private string $text = '';

    /** Date de publication de l'avis sur Google (optionnel). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewDate = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->author ?: 'Avis';
    }

    public function getId(): ?int { return $this->id; }

    public function getAuthor(): string { return $this->author; }
    public function setAuthor(string $author): self { $this->author = $author; return $this; }

    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): self
    {
        $this->rating = max(1, min(5, $rating));
        return $this;
    }

    public function getText(): string { return $this->text; }
    public function setText(string $text): self { $this->text = $text; return $this; }

    public function getReviewDate(): ?\DateTimeImmutable { return $this->reviewDate; }
    public function setReviewDate(?\DateTimeImmutable $date): self { $this->reviewDate = $date; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $published): self { $this->isPublished = $published; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
