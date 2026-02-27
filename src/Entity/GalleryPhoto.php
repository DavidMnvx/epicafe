<?php

namespace App\Entity;

use App\Repository\GalleryPhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryPhotoRepository::class)]
#[ORM\Table(name: 'gallery_photo')]
class GalleryPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    // Date de l’événement / photo (pour tri chrono) - optionnel
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    // Nom du fichier uploadé (stocké dans public/uploads/gallery)
    #[ORM\Column(length: 255)]
    private string $fileName = '';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PostRemove]
    public function deleteFile(): void
    {
        if (!$this->fileName) return;

        $path = __DIR__ . '/../../public/uploads/gallery/' . $this->fileName;
        if (is_file($path)) @unlink($path);
    }

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function __toString(): string
    {
        return $this->title ?: 'Photo';
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getTakenAt(): ?\DateTimeImmutable { return $this->takenAt; }
    public function setTakenAt(?\DateTimeImmutable $takenAt): self { $this->takenAt = $takenAt; return $this; }

    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): self { $this->fileName = $fileName; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}