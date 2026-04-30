<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteImageRepository::class)]
#[ORM\Table(name: 'site_image')]
#[ORM\HasLifecycleCallbacks]
class SiteImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Identifiant technique de l'emplacement (ex : "banner_menu", "home_hero").
     * Utilisé dans les templates via {{ site_image('banner_menu') }}.
     */
    #[ORM\Column(length: 80, unique: true)]
    private string $slug = '';

    /**
     * Libellé lisible affiché dans l'admin.
     */
    #[ORM\Column(length: 180)]
    private string $label = '';

    /**
     * Description explicative (où l'image apparaît, taille recommandée).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Fichier uploadé par le client (dans public/uploads/site/).
     * Null = on utilise fallbackPath.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    /**
     * Chemin relatif de l'image par défaut (ex : "images/banners-menu.png").
     * Utilisé tant qu'aucune image n'a été uploadée.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fallbackPath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onWrite(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->label ?: $this->slug;
    }

    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $fileName): self { $this->fileName = $fileName; return $this; }

    public function getFallbackPath(): ?string { return $this->fallbackPath; }
    public function setFallbackPath(?string $fallbackPath): self { $this->fallbackPath = $fallbackPath; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
