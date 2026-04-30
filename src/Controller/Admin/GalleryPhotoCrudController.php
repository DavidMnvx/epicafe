<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GalleryPhoto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

final class GalleryPhotoCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return GalleryPhoto::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Photo')
            ->setEntityLabelInPlural('Galerie')
            ->setDefaultSort(['takenAt' => 'DESC'])
            ->setSearchFields(['title'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvelle photo'));

        return $this->addPublishableBatchActions($actions);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Publié'))
            ->add(DateTimeFilter::new('takenAt', 'Prise le'));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('fileName', 'Aperçu')
            ->setBasePath('uploads/gallery')
            ->onlyOnIndex();
        yield TextField::new('title', 'Titre')->onlyOnIndex();
        yield DateField::new('takenAt', 'Date')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Formulaire =====
        yield FormField::addTab('Infos')
            ->setIcon('fa fa-circle-info')
            ->setHelp(
                'Pour IMPORTER plusieurs photos d’un coup, utilise "Upload photos" dans le menu de gauche. '
                . 'Cette page sert à éditer le titre, la date et la publication d’une photo déjà importée.'
            );

        yield TextField::new('title', 'Titre')
            ->setColumns('col-md-6')
            ->setHelp('Titre affiché au survol de la photo sur le site (ex : "Fête du village 2024").')
            ->onlyOnForms();

        yield DateField::new('takenAt', 'Date de la photo')
            ->setHelp('Optionnel — sert au tri chronologique de la galerie (les plus récentes en premier).')
            ->setColumns('col-md-3')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3')
            ->setHelp('Décoche pour cacher cette photo de la galerie publique.')
            ->onlyOnForms();

        yield FormField::addTab('Image')
            ->setIcon('fa fa-image')
            ->setHelp(
                'Le fichier image ne peut pas être modifié ici. '
                . 'Pour remplacer une photo : supprime-la puis ré-importe la nouvelle via "Upload photos". '
                . 'Tailles recommandées : 1600 px de large, JPG ou WebP, max 500 Ko. '
                . 'Les photos plus grandes sont acceptées mais ralentissent le site.'
            );

        yield TextField::new('fileName', 'Fichier')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Nom du fichier sur le serveur (lecture seule). Pour importer de nouvelles photos, utilise "Upload photos" dans le menu.')
            ->onlyOnForms();
    }
}
