<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\GalleryPhoto;
use App\Entity\GoogleReview;
use App\Entity\MenuCategory;
use App\Entity\MenuItem;
use App\Entity\Partner;
use App\Entity\ShopCategory;
use App\Entity\SiteImage;
use App\Entity\SiteSetting;
use App\Repository\GoogleReviewRepository;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem as EaMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly GoogleReviewRepository $googleReviewRepo,
        private readonly SiteSettingRepository $siteSettingRepo,
    ) {
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('/styles/admin.css');
    }

    public function index(): Response
    {
        $reviewStats = $this->googleReviewRepo->getPublishedStats();
        $latestReviews = $this->googleReviewRepo->findLatestPublished(3);
        $navToggles = $this->buildNavToggles();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $this->buildStats(),
            'upcomingEvents' => $this->em->getRepository(Event::class)
                ->createQueryBuilder('e')
                ->where('e.isPublished = true')
                ->andWhere('e.startAt >= :now OR e.isRecurring = true')
                ->setParameter('now', new \DateTimeImmutable())
                ->orderBy('e.startAt', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult(),
            'reviewStats' => $reviewStats,
            'latestReviews' => $latestReviews,
            'reviewsHref' => $this->crudUrl(GoogleReviewCrudController::class),
            'navToggles' => $navToggles,
            'links' => [
                'event'        => $this->crudUrl(EventCrudController::class),
                'gallery'      => $this->crudUrl(GalleryPhotoCrudController::class),
                'partner'      => $this->crudUrl(PartnerCrudController::class),
                'menuItem'     => $this->crudUrl(MenuItemCrudController::class),
                'menuCategory' => $this->crudUrl(MenuCategoryCrudController::class),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Épi-Café')
            ->setFaviconPath('/favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield EaMenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');

        yield EaMenuItem::section('Événements');
        yield EaMenuItem::linkToCrud('Événements', 'fa fa-calendar', Event::class);

        yield EaMenuItem::section('Galerie photos');
        yield EaMenuItem::linkToCrud('Galerie', 'fa fa-image', GalleryPhoto::class);
        yield EaMenuItem::linkToRoute('Upload photos', 'fa fa-upload', 'admin_gallery_upload');

        yield EaMenuItem::section('Partenaires');
        yield EaMenuItem::linkToCrud('Partenaires', 'fa fa-handshake', Partner::class);

        yield EaMenuItem::section('Menu');
        yield EaMenuItem::linkToCrud('Catégories menu', 'fa fa-list', MenuCategory::class);
        yield EaMenuItem::linkToCrud('Lignes menu', 'fa fa-utensils', MenuItem::class);
        yield EaMenuItem::linkToRoute('Menu Builder', 'fa fa-sliders', 'admin_menu_builder');

        yield EaMenuItem::section('Boutique');
        yield EaMenuItem::linkToCrud('Catégories boutique', 'fa fa-basket-shopping', ShopCategory::class);

        yield EaMenuItem::section('Contenu du site');
        yield EaMenuItem::linkToCrud('Images du site', 'fa fa-panorama', SiteImage::class);
        yield EaMenuItem::linkToCrud('Paramètres du site', 'fa fa-sliders', SiteSetting::class);
        yield EaMenuItem::linkToCrud('Avis clients (Google)', 'fa fa-star', GoogleReview::class);
    }

    /**
     * @return array<string, array{label: string, value: int, icon: string, href: string}>
     */
    private function buildStats(): array
    {
        $now = new \DateTimeImmutable();

        $upcoming = (int) $this->em->createQuery(
            'SELECT COUNT(e) FROM ' . Event::class . ' e
             WHERE e.isPublished = true AND (e.startAt >= :now OR e.isRecurring = true)'
        )->setParameter('now', $now)->getSingleScalarResult();

        return [
            'events' => [
                'label' => 'Événements à venir',
                'value' => $upcoming,
                'icon'  => 'fa-calendar',
                'href'  => $this->crudUrl(EventCrudController::class),
            ],
            'photos' => [
                'label' => 'Photos publiées',
                'value' => (int) $this->em->getRepository(GalleryPhoto::class)->count(['isPublished' => true]),
                'icon'  => 'fa-image',
                'href'  => $this->crudUrl(GalleryPhotoCrudController::class),
            ],
            'partners' => [
                'label' => 'Partenaires actifs',
                'value' => (int) $this->em->getRepository(Partner::class)->count(['isPublished' => true]),
                'icon'  => 'fa-handshake',
                'href'  => $this->crudUrl(PartnerCrudController::class),
            ],
            'menuItems' => [
                'label' => 'Lignes menu',
                'value' => (int) $this->em->getRepository(MenuItem::class)->count(['isPublished' => true]),
                'icon'  => 'fa-utensils',
                'href'  => $this->crudUrl(MenuItemCrudController::class),
            ],
            'shopCategories' => [
                'label' => 'Catégories boutique',
                'value' => (int) $this->em->getRepository(ShopCategory::class)->count(['isPublished' => true]),
                'icon'  => 'fa-basket-shopping',
                'href'  => $this->crudUrl(ShopCategoryCrudController::class),
            ],
            'siteImages' => [
                'label' => 'Images du site',
                'value' => (int) $this->em->getRepository(SiteImage::class)->count([]),
                'icon'  => 'fa-panorama',
                'href'  => $this->crudUrl(SiteImageCrudController::class),
            ],
        ];
    }

    private function crudUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    /**
     * Construit la liste des switches "visibilité des pages" pour le dashboard.
     *
     * @return array<int, array{
     *     setting: \App\Entity\SiteSetting,
     *     label: string,
     *     icon: string,
     *     toggleUrl: string
     * }>
     */
    private function buildNavToggles(): array
    {
        $config = [
            'nav_show_events'   => ['label' => 'Événements',     'icon' => 'fa-calendar'],
            'nav_show_gallery'  => ['label' => 'Galerie photos', 'icon' => 'fa-image'],
            'nav_show_partners' => ['label' => 'Partenaires',    'icon' => 'fa-handshake'],
            'nav_show_shop'     => ['label' => 'Boutique',       'icon' => 'fa-basket-shopping'],
            'nav_show_menu'     => ['label' => 'Menu',           'icon' => 'fa-utensils'],
            'nav_show_contact'  => ['label' => 'Contact',        'icon' => 'fa-envelope'],
        ];

        $items = [];
        foreach ($config as $key => $meta) {
            $setting = $this->siteSettingRepo->findOneByKey($key);
            if ($setting === null) {
                continue;
            }

            $toggleUrl = $this->adminUrlGenerator
                ->setController(SiteSettingCrudController::class)
                ->setAction('toggleBoolean')
                ->setEntityId($setting->getId())
                ->generateUrl();

            $items[] = [
                'setting'   => $setting,
                'label'     => $meta['label'],
                'icon'      => $meta['icon'],
                'toggleUrl' => $toggleUrl,
            ];
        }

        return $items;
    }
}
