<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use App\Repository\SiteSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les paramètres globaux du site aux templates Twig.
 *
 * Usage :
 *   {{ setting('contact_phone') }}
 *   {{ setting('contact_phone', '04 90 00 00 00') }}  (avec valeur par défaut)
 *   {% if setting_bool('closure_active') %} ... {% endif %}
 */
final class SiteSettingExtension extends AbstractExtension
{
    /** @var array<string, string|null>|null Cache mémoire — chargé 1 seule fois par requête */
    private ?array $cache = null;

    /** @var array<int, GoogleReview>|null */
    private ?array $reviewsCache = null;

    /** @var array{count:int, average:float}|null */
    private ?array $reviewsStatsCache = null;

    public function __construct(
        private readonly SiteSettingRepository $repository,
        private readonly GoogleReviewRepository $reviewRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->setting(...)),
            new TwigFunction('setting_bool', $this->settingBool(...)),
            new TwigFunction('google_reviews', $this->googleReviews(...)),
            new TwigFunction('google_reviews_stats', $this->googleReviewsStats(...)),
        ];
    }

    /**
     * Retourne les N derniers avis Google publiés (par défaut : les 5 plus récents).
     *
     * @return array<int, GoogleReview>
     */
    public function googleReviews(int $limit = 5): array
    {
        if ($this->reviewsCache === null) {
            try {
                $this->reviewsCache = $this->reviewRepository->findLatestPublished($limit);
            } catch (\Throwable) {
                $this->reviewsCache = [];
            }
        }

        return $this->reviewsCache;
    }

    /**
     * Stats agrégées (note moyenne + nombre total d'avis publiés).
     *
     * @return array{count:int, average:float}
     */
    public function googleReviewsStats(): array
    {
        if ($this->reviewsStatsCache === null) {
            try {
                $this->reviewsStatsCache = $this->reviewRepository->getPublishedStats();
            } catch (\Throwable) {
                $this->reviewsStatsCache = ['count' => 0, 'average' => 0.0];
            }
        }

        return $this->reviewsStatsCache;
    }

    public function setting(string $key, string $default = ''): string
    {
        $map = $this->getMap();
        $value = $map[$key] ?? null;

        return ($value === null || $value === '') ? $default : (string) $value;
    }

    public function settingBool(string $key, bool $default = false): bool
    {
        $map = $this->getMap();
        $value = $map[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, string|null>
     */
    private function getMap(): array
    {
        if ($this->cache === null) {
            try {
                $this->cache = $this->repository->loadAllAsMap();
            } catch (\Throwable) {
                // Si la table n'existe pas encore (avant migration), on dégrade proprement.
                $this->cache = [];
            }
        }

        return $this->cache;
    }
}
