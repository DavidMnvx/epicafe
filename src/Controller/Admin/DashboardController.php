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
        return Assets::new()
            ->addCssFile('/styles/admin.css')
            // Le module ne s'active que sur la page du builder de carte : il
            // cherche [data-menu-builder] et ne fait rien s'il ne le trouve pas.
            ->addAssetMapperEntry('admin-menu-builder');
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

    /**
     * Menu organisé par tâche plutôt que par entité : une entrée = une chose que
     * la gérante veut faire. Les écrans secondaires (catégories de la carte,
     * édition photo par photo) vivent en sous-menu de l'écran principal auquel
     * ils se rattachent, au lieu d'occuper une ligne de premier niveau.
     */
    public function configureMenuItems(): iterable
    {
        yield EaMenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');

        yield EaMenuItem::section('Le site');

        // Le Menu Builder couvre la création/modification/suppression des lignes
        // et de leurs variantes ; le CRUD Catégories reste nécessaire pour les
        // renommer, les réordonner ou les dépublier.
        yield EaMenuItem::subMenu('La carte', 'fa fa-utensils')->setSubItems([
            EaMenuItem::linkToRoute('Composer la carte', 'fa fa-pen-to-square', 'admin_menu_builder'),
            EaMenuItem::linkToCrud('Catégories de la carte', 'fa fa-list', MenuCategory::class),
        ]);

        yield EaMenuItem::linkToCrud('La boutique', 'fa fa-basket-shopping', ShopCategory::class);

        yield EaMenuItem::linkToCrud('Les événements', 'fa fa-calendar', Event::class);

        yield EaMenuItem::subMenu('Les photos', 'fa fa-image')->setSubItems([
            EaMenuItem::linkToRoute('Ajouter des photos', 'fa fa-upload', 'admin_gallery_upload'),
            EaMenuItem::linkToCrud('Toutes les photos', 'fa fa-images', GalleryPhoto::class),
        ]);

        yield EaMenuItem::linkToCrud('Les partenaires', 'fa fa-handshake', Partner::class);

        yield EaMenuItem::section('Réglages');
        yield EaMenuItem::linkToCrud('Coordonnées & options', 'fa fa-sliders', SiteSetting::class);
        yield EaMenuItem::linkToCrud('Images du site', 'fa fa-panorama', SiteImage::class);
        yield EaMenuItem::linkToCrud('Avis Google', 'fa fa-star', GoogleReview::class);

        yield EaMenuItem::section();
        yield EaMenuItem::linkToUrl('Voir le site', 'fa fa-arrow-up-right-from-square', '/')
            ->setLinkTarget('_blank');
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
