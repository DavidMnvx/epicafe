<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\GalleryPhotoRepository;
use App\Repository\PartnerRepository;
use App\Repository\ShopCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * sitemap.xml dynamique :
 *  - pages statiques visibles dans la nav
 *  - événements publiés (page de détail)
 */
final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], defaults: ['_format' => 'xml'])]
    public function sitemap(
        EventRepository $events,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $now = (new \DateTimeImmutable())->format('Y-m-d');
        $urls = [];

        // Pages statiques principales
        $staticRoutes = [
            'app_home'        => ['priority' => '1.0',  'changefreq' => 'weekly'],
            'event_index'     => ['priority' => '0.9',  'changefreq' => 'weekly'],
            'menu'            => ['priority' => '0.9',  'changefreq' => 'weekly'],
            'shop_index'      => ['priority' => '0.8',  'changefreq' => 'weekly'],
            'gallery_index'   => ['priority' => '0.7',  'changefreq' => 'monthly'],
            'partner_index'   => ['priority' => '0.6',  'changefreq' => 'monthly'],
            'contact_index'   => ['priority' => '0.6',  'changefreq' => 'monthly'],
            'legal_mentions'  => ['priority' => '0.3',  'changefreq' => 'yearly'],
            'legal_privacy'   => ['priority' => '0.3',  'changefreq' => 'yearly'],
        ];

        foreach ($staticRoutes as $routeName => $meta) {
            try {
                $urls[] = [
                    'loc'        => $urlGenerator->generate($routeName, [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'lastmod'    => $now,
                    'changefreq' => $meta['changefreq'],
                    'priority'   => $meta['priority'],
                ];
            } catch (\Throwable) {
                // Si la route n'existe pas, on saute silencieusement.
            }
        }

        // Événements publiés (pages détail)
        foreach ($events->findBy(['isPublished' => true]) as $event) {
            if (!$event->getSlug()) {
                continue;
            }
            $lastmod = $event->getUpdatedAt() ?? $event->getCreatedAt();
            $urls[] = [
                'loc'        => $urlGenerator->generate('event_show', ['slug' => $event->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'    => ($lastmod ?? new \DateTimeImmutable())->format('Y-m-d'),
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }

        $xml = $this->renderXml($urls);

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @param array<int, array{loc:string, lastmod:string, changefreq:string, priority:string}> $urls
     */
    private function renderXml(array $urls): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
            $xml .= "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }
}
