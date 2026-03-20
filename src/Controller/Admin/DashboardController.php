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
use App\Entity\MenuCategory;
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
        return Dashboard::new()->setTitle('Epi-Café');
    }

    public function configureMenuItems(): iterable
    {

        yield MenuItem::section('Evenements');
        yield MenuItem::linkToCrud('Événements', 'fa fa-calendar', Event::class);
        

        yield MenuItem::section('Galerie Photos');
        yield MenuItem::linkToCrud('Galerie', 'fa fa-image', GalleryPhoto::class);
        yield MenuItem::linkToRoute('Upload Photos', 'fa fa-upload', 'admin_gallery_upload');

        yield MenuItem::section('Partenaires');
        yield MenuItem::linkToCrud('Partenaires', 'fa fa-handshake', Partner::class);


        yield MenuItem::section('Menu');
        yield MenuItem::linkToCrud('Catégories menu', 'fa fa-list', \App\Entity\MenuCategory::class);
        yield MenuItem::linkToCrud('Lignes menu', 'fa fa-utensils', \App\Entity\MenuItem::class);
        yield MenuItem::linkToRoute('Menu Builder', 'fa fa-utensils', 'admin_menu_builder');
        
    }
}