<?php

namespace App\Entity;

use App\Repository\MenuItemVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemVariantRepository::class)]
class MenuItemVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MenuItem $item = null;

    #[ORM\Column(length: 80)]
    private string $label = ''; // ex: "25 cl"

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;


    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublished = true;


        public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    public function __toString(): string
    {
        return $this->label ?: 'Variante';
    }

    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getItem(): ?MenuItem 
    { 
        return $this->item; 
    }

    public function setItem(?MenuItem $item): self 
    { 
        $this->item = $item; return $this; 
    }

    public function getLabel(): string 
    { 
        return $this->label; 
    }

    public function setLabel(string $label): self 
    { 
        $this->label = $label; 
        return $this; 
    }

    public function getPrice(): ?string 
    { 
        return $this->price; 
    }

    public function setPrice(?string $price): static
    { 
        $this->price = $price; 
        return $this; 
    }

    public function getPosition(): int 
    { 
        return $this->position; 
    }

    public function setPosition(int $position): self 
    { 
        $this->position = $position; 
        return $this; 
    }
}