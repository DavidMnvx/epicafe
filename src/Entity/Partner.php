<?php

namespace App\Entity;

use App\Repository\PartnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partner')]
class Partner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name = '';

    #[ORM\Column(length: 220, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $websiteUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // 3 bullet points (optionnels)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bullet1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bullet2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bullet3 = null;

    // Logo uploadé (nom de fichier)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoFileName = null;

    // Fallback logo par URL (optionnel)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 20)]
    private string $type = 'partner'; // premium | secondary | partner

    #[ORM\Column(nullable: true)]
    private ?string $heroImageFileName = null;

    #[ORM\Column(nullable: true)]
    private ?string $image2FileName = null;

    #[ORM\Column(nullable: true)]
    private ?string $image3FileName = null;


    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?: 'Partenaire';
    }

        // Champ virtuel pour EasyAdmin (aperçu live)
    public function getLivePreview(): string
    {
        return '';
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getWebsiteUrl(): ?string { return $this->websiteUrl; }
    public function setWebsiteUrl(?string $websiteUrl): self { $this->websiteUrl = $websiteUrl; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getBullet1(): ?string { return $this->bullet1; }
    public function setBullet1(?string $bullet1): self { $this->bullet1 = $bullet1; return $this; }

    public function getBullet2(): ?string { return $this->bullet2; }
    public function setBullet2(?string $bullet2): self { $this->bullet2 = $bullet2; return $this; }

    public function getBullet3(): ?string { return $this->bullet3; }
    public function setBullet3(?string $bullet3): self { $this->bullet3 = $bullet3; return $this; }

    public function getLogoFileName(): ?string { return $this->logoFileName; }
    public function setLogoFileName(?string $logoFileName): self { $this->logoFileName = $logoFileName; return $this; }

    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): self { $this->logoUrl = $logoUrl; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}