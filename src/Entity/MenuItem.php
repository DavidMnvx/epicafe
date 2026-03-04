<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MenuCategory $category = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // Pour item simple
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $unit = null; // ex: "25 cl", "1 fruit (0,33cl)"

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null; // decimal stocké en string

    /** @var Collection<int, MenuItemVariant> */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: MenuItemVariant::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $variants;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function __construct()
    {
        $this->variants = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?: 'Item';
    }

    public function getId(): ?int { return $this->id; }

    public function getCategory(): ?MenuCategory { return $this->category; }
    public function setCategory(?MenuCategory $category): self { $this->category = $category; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(?string $unit): self { $this->unit = $unit; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(?string $price): self { $this->price = $price; return $this; }

    /** @return Collection<int, MenuItemVariant> */
    public function getVariants(): Collection { return $this->variants; }

    public function addVariant(MenuItemVariant $variant): self
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setItem($this);
        }
        return $this;
    }

    public function removeVariant(MenuItemVariant $variant): self
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getItem() === $this) {
                $variant->setItem(null);
            }
        }
        return $this;
    }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function hasVariants(): bool
    {
        return $this->variants->count() > 0;
    }
}