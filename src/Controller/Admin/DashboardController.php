<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\GalleryPhoto;
use App\Entity\Page;
use App\Entity\Partner;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('/styles/admin.css');
            
    }

    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect(
            $adminUrlGenerator
                ->setController(GalleryPhotoCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Epicafé');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Contenu');
        yield MenuItem::linkToCrud('Pages', 'fa fa-file', Page::class);
        yield MenuItem::linkToCrud('Événements', 'fa fa-calendar', Event::class);

        yield MenuItem::section('Galerie Photos');
        yield MenuItem::linkToCrud('Galerie', 'fa fa-image', GalleryPhoto::class);
        yield MenuItem::linkToRoute('Upload Photos', 'fa fa-upload', 'admin_gallery_upload');

        yield MenuItem::section('Partenaires');
        yield MenuItem::linkToCrud('Partenaires', 'fa fa-handshake', Partner::class);

        yield MenuItem::section('Divers');
        yield MenuItem::linkToCrud('Catégories', 'fa fa-tags', Category::class);
    }
}