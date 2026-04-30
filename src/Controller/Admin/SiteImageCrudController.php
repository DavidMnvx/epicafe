<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteImage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class SiteImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SiteImage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image du site')
            ->setEntityLabelInPlural('Images du site')
            ->setPageTitle(Crud::PAGE_INDEX, 'Images du site')
            ->setPageTitle(Crud::PAGE_EDIT, fn (SiteImage $img) => 'Modifier : ' . $img->getLabel())
            ->setSearchFields(['slug', 'label'])
            ->setDefaultSort(['label' => 'ASC'])
            ->setPaginatorPageSize(24)
            ->overrideTemplate('crud/index', 'admin/site_image/index.html.twig')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Chaque carte correspond à un emplacement d’image du site (bannière, photo d’une page). '
                . 'Clique sur "Modifier l’image" pour remplacer la photo. '
                . 'Si aucune image n’est uploadée, l’image par défaut est utilisée.'
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Édition uniquement : pas de création ni de suppression.
        // Les emplacements sont pré-définis par la commande app:site-images:seed.
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Index =====
        yield ImageField::new('fileName', 'Aperçu')
            ->setBasePath('uploads/site')
            ->onlyOnIndex();

        yield TextField::new('label', 'Emplacement')->onlyOnIndex();
        yield TextField::new('slug', 'Identifiant technique')->onlyOnIndex();
        yield DateTimeField::new('updatedAt', 'Dernière modification')->onlyOnIndex();

        // ===== Formulaire =====
        yield FormField::addTab('Image')
            ->setIcon('fa fa-image')
            ->setHelp('Remplace ici l’image de cet emplacement du site. Tant qu’aucune image n’est uploadée, l’image par défaut est utilisée automatiquement.');

        yield TextField::new('label', 'Emplacement')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Nom de l’emplacement (non modifiable).')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description et recommandations')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Rappel du rôle de cette image et des dimensions conseillées.')
            ->onlyOnForms();

        yield ImageField::new('fileName', 'Image')
            ->setUploadDir('public/uploads/site')
            ->setBasePath('uploads/site')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setRequired(false)
            ->setHelp('Respecte les dimensions indiquées ci-dessus. Laisse vide pour garder l’image actuelle.')
            ->onlyOnForms();

        yield TextField::new('fallbackPath', 'Image par défaut')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Chemin de l’image utilisée tant qu’aucune image personnalisée n’a été uploadée (lecture seule).')
            ->onlyOnForms();
    }
}
