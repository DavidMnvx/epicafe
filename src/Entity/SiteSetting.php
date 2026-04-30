<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètres globaux du site (téléphone, email, réseaux sociaux,
 * bandeau fermeture, horaires textuels, etc.) — édités via l'admin.
 *
 * Les valeurs sont accédées dans Twig via {{ setting('key', 'default') }}.
 */
#[ORM\Entity(repositoryClass: SiteSettingRepository::class)]
#[ORM\Table(name: 'site_setting')]
#[ORM\HasLifecycleCallbacks]
class SiteSetting
{
    public const TYPE_TEXT     = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL    = 'email';
    public const TYPE_TEL      = 'tel';
    public const TYPE_URL      = 'url';
    public const TYPE_BOOLEAN  = 'boolean';

    public const GROUP_CONTACT    = 'contact';
    public const GROUP_SOCIAL     = 'social';
    public const GROUP_CLOSURE    = 'closure';
    public const GROUP_NAVIGATION = 'navigation';
    public const GROUP_GENERAL    = 'general';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé technique (ex: "contact_phone", "social_facebook"). */
    #[ORM\Column(length: 80, unique: true)]
    private string $key = '';

    /** Libellé lisible affiché dans l'admin. */
    #[ORM\Column(length: 180)]
    private string $label = '';

    /** Description / aide pour le client. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Valeur (peut être vide). Booléens stockés comme '1' / '0'. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    /** Type de champ pour le rendu admin (text, email, tel, url, textarea, boolean). */
    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TEXT;

    /** Groupe pour organiser l'admin (contact, social, closure, general). */
    #[ORM\Column(length: 40)]
    private string $groupName = self::GROUP_GENERAL;

    /** Ordre d'affichage dans le groupe. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $position = 0;

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
        return $this->label ?: $this->key;
    }

    public function asBool(): bool
    {
        return in_array($this->value, ['1', 'true', 'on', 'yes'], true);
    }

    public function getId(): ?int { return $this->id; }

    public function getKey(): string { return $this->key; }
    public function setKey(string $key): self { $this->key = $key; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): self { $this->value = $value; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getGroupName(): string { return $this->groupName; }
    public function setGroupName(string $group): self { $this->groupName = $group; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
