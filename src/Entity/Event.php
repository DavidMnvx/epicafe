<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    // nullable + unique => permet de créer sans slug au formulaire, EasyAdmin/SlugField le remplira
    #[ORM\Column(length: 200, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * nullable pour permettre les événements permanents
     * (calculés à partir de recurringDayOfWeek + recurringTime)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    // URL d'image (fallback)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    // Image uploadée (optionnelle) : on stocke juste le nom de fichier
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFileName = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ===== Récurrence (événement permanent) =====

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRecurring = false;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $recurringDayOfWeek = null; // 1=Lundi ... 7=Dimanche

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $recurringTime = null; // heure (ex 19:00)

    // ===== Menu (optionnel) =====
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $menu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $menuStarter = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $menuMain = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $menuDessert = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $menuDessert2 = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $menuPrice = null; // Doctrine map decimal => string

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;

        // Par défaut, on met une valeur “raisonnable” pour les événements normaux
        // (mais ça n'empêche pas de laisser vide côté CRUD si tu veux)
        $this->startAt = $now->modify('+7 days');
    }

    public function __toString(): string
    {
        return $this->title ?: 'Événement';
    }

    /**
     * À appeler avant persist/update (EasyAdmin)
     * - si isRecurring: calcule startAt à partir du prochain jour + heure
     * - sinon: ne touche pas
     */
    public function ensureStartAtForRecurring(): void
    {
        if (!$this->isRecurring) {
            return;
        }

        // En permanent, pas de fin obligatoire
        $this->endAt = null;

        // Si info manquante, on laisse startAt null (pas d'explosion)
        if (!$this->recurringDayOfWeek || !$this->recurringTime) {
            $this->startAt = null;
            return;
        }

        $now = new \DateTimeImmutable('now');

        // Construire la datetime "aujourd'hui à l'heure X"
        $h = (int) $this->recurringTime->format('H');
        $m = (int) $this->recurringTime->format('i');

        $todayAtTime = $now->setTime($h, $m);

        // Jour ciblé (1..7)
        $targetDow = (int) $this->recurringDayOfWeek;
        $todayDow  = (int) $now->format('N');

        // Si on est le bon jour:
        // - si l'heure n'est pas passée => c'est aujourd'hui
        // - sinon => +7 jours
        if ($targetDow === $todayDow) {
            $this->startAt = ($todayAtTime > $now)
                ? $todayAtTime
                : $todayAtTime->modify('+7 days');
            return;
        }

        // Sinon: calcul du prochain jour de semaine
        $daysToAdd = ($targetDow - $todayDow + 7) % 7;
        if ($daysToAdd === 0) {
            $daysToAdd = 7;
        }

        $nextDate = $now->modify("+{$daysToAdd} days")->setTime($h, $m);
        $this->startAt = $nextDate;
    }

    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ===== Getters/Setters =====

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getStartAt(): ?\DateTimeImmutable { return $this->startAt; }
    public function setStartAt(?\DateTimeImmutable $startAt): self { $this->startAt = $startAt; return $this; }

    public function getEndAt(): ?\DateTimeImmutable { return $this->endAt; }
    public function setEndAt(?\DateTimeImmutable $endAt): self { $this->endAt = $endAt; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl; return $this; }

    public function getImageFileName(): ?string { return $this->imageFileName; }
    public function setImageFileName(?string $imageFileName): self { $this->imageFileName = $imageFileName; return $this; }

    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function isRecurring(): bool { return $this->isRecurring; }
    public function setIsRecurring(bool $isRecurring): self { $this->isRecurring = $isRecurring; return $this; }

    public function getRecurringDayOfWeek(): ?int { return $this->recurringDayOfWeek; }
    public function setRecurringDayOfWeek(?int $day): self { $this->recurringDayOfWeek = $day; return $this; }

    public function getRecurringTime(): ?\DateTimeImmutable { return $this->recurringTime; }
    public function setRecurringTime(?\DateTimeImmutable $time): self { $this->recurringTime = $time; return $this; }

    public function getMenu(): ?string { return $this->menu; }
    public function setMenu(?string $menu): self { $this->menu = $menu; return $this; }

    public function getMenuStarter(): ?string { return $this->menuStarter; }
    public function setMenuStarter(?string $menuStarter): self { $this->menuStarter = $menuStarter; return $this; }

    public function getMenuMain(): ?string { return $this->menuMain; }
    public function setMenuMain(?string $menuMain): self { $this->menuMain = $menuMain; return $this; }

    public function getMenuDessert(): ?string { return $this->menuDessert; }
    public function setMenuDessert(?string $menuDessert): self { $this->menuDessert = $menuDessert; return $this; }

    public function getMenuDessert2(): ?string { return $this->menuDessert2; }
public function setMenuDessert2(?string $menuDessert2): self { $this->menuDessert2 = $menuDessert2; return $this; }

    public function getMenuPrice(): ?string { return $this->menuPrice; }
    public function setMenuPrice(?string $menuPrice): self { $this->menuPrice = $menuPrice; return $this; }

    public function getNextOccurrence(): ?\DateTimeImmutable
{
    // Si événement normal, on renvoie startAt
    if (!$this->isRecurring) {
        return $this->startAt;
    }

    // Si récurrent mais incomplet, on ne peut pas calculer
    if (!$this->recurringDayOfWeek || !$this->recurringTime) {
        return null;
    }

    $now = new \DateTimeImmutable('now');
    $targetDow = (int) $this->recurringDayOfWeek; // 1..7 (Mon..Sun)
    $todayDow  = (int) $now->format('N');

    // prochain jour (si même jour -> semaine suivante)
    $daysToAdd = ($targetDow - $todayDow + 7) % 7;
    if ($daysToAdd === 0) {
        $daysToAdd = 7;
    }

    $nextDate = $now->modify("+{$daysToAdd} days")->setTime(0, 0);

    $h = (int) $this->recurringTime->format('H');
    $m = (int) $this->recurringTime->format('i');

    return $nextDate->setTime($h, $m);
}
}