<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\SiteImageRepository;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SiteImageExtension extends AbstractExtension
{
    /** @var array<string, string> Cache mémoire pour éviter les requêtes multiples */
    private array $cache = [];

    public function __construct(
        private readonly SiteImageRepository $repository,
        private readonly Packages $packages,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_image', $this->siteImage(...)),
        ];
    }

    /**
     * Retourne l'URL d'une image du site à partir de son slug.
     * Si une image a été uploadée → URL du fichier uploadé.
     * Sinon → chemin par défaut (fallback).
     * Si le slug n'existe pas du tout → null (le template doit gérer).
     */
    public function siteImage(string $slug, ?string $fallback = null): ?string
    {
        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug] ?: null;
        }

        $image = $this->repository->findOneBySlug($slug);

        if ($image === null) {
            return $this->cache[$slug] = ($fallback !== null ? $this->packages->getUrl($fallback) : '');
        }

        if ($image->getFileName()) {
            return $this->cache[$slug] = $this->packages->getUrl('uploads/site/' . $image->getFileName());
        }

        $fb = $image->getFallbackPath() ?? $fallback;

        return $this->cache[$slug] = ($fb ? $this->packages->getUrl($fb) : '');
    }
}
