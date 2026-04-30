<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopCategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopCategoryRepository::class)]
#[ORM\Table(name: 'shop_category')]
#[ORM\HasLifecycleCallbacks]
class ShopCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $slug = '';

    /** Petit accroche au-dessus du titre (ex : "Produits locaux") */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $kicker = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Points mis en avant — une ligne = un item, affiché en liste à puces.
     * Ex :
     *   Miel de lavande du Ventoux
     *   Miel de garrigue
     *   Huile d'olive extra-vierge
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $highlights = null;

    /** Icône / emoji affiché si aucune image (ex : "🍯", "🍷") */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $icon = null;

    /** Image uploadée (dans public/uploads/shop/) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFileName = null;

    /** URL externe (utilisée si aucun fichier uploadé) */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    /**
     * Citation mise en avant après cet article (optionnel).
     * Ex : « On ne veut pas tout faire — on veut bien faire les choses qu'on aime. »
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pullQuote = null;

    /** Auteur de la citation (optionnel — défaut "L'esprit de l'Épi-Café"). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pullQuoteAuthor = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = true;

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
        return $this->name ?: 'Catégorie boutique';
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getKicker(): ?string { return $this->kicker; }
    public function setKicker(?string $kicker): self { $this->kicker = $kicker; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getHighlights(): ?string { return $this->highlights; }
    public function setHighlights(?string $highlights): self { $this->highlights = $highlights; return $this; }

    /**
     * Retourne highlights sous forme de tableau (une entrée par ligne non vide).
     * @return string[]
     */
    public function getHighlightLines(): array
    {
        if (!$this->highlights) {
            return [];
        }

        $lines = preg_split('/\R/u', $this->highlights) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
    }

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): self { $this->icon = $icon; return $this; }

    public function getImageFileName(): ?string { return $this->imageFileName; }
    public function setImageFileName(?string $imageFileName): self { $this->imageFileName = $imageFileName; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl ?: null; return $this; }

    public function getPullQuote(): ?string { return $this->pullQuote; }
    public function setPullQuote(?string $pullQuote): self { $this->pullQuote = $pullQuote ?: null; return $this; }

    public function getPullQuoteAuthor(): ?string { return $this->pullQuoteAuthor; }
    public function setPullQuoteAuthor(?string $author): self { $this->pullQuoteAuthor = $author ?: null; return $this; }

    /**
     * Indique si la catégorie a une image (uploadée ou via URL).
     */
    public function hasImage(): bool
    {
        return !empty($this->imageFileName) || !empty($this->imageUrl);
    }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
