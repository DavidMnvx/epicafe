<?php

namespace App\Controller\Admin;

use App\Entity\GalleryPhoto;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class GalleryPhotoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GalleryPhoto::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Photo')
            ->setEntityLabelInPlural('Galerie')
            ->setDefaultSort(['takenAt' => 'DESC']);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof GalleryPhoto) {
            $entityInstance->touch();
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof GalleryPhoto) {
            $entityInstance->touch();
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Infos');

        yield IdField::new('id')->onlyOnIndex();

        // ✅ Aperçu (index)
        yield ImageField::new('fileName', 'Aperçu')
            ->setBasePath('uploads/gallery')
            ->onlyOnIndex();

        yield TextField::new('title', 'Titre')
            ->setColumns('col-md-6');

        yield DateField::new('takenAt', 'Date')
            ->setHelp('Optionnel (sert pour le tri chronologique)')
            ->setColumns('col-md-3');

        // ✅ Statut publication (je te conseille de le garder)
        // Si tu veux le retirer complètement -> supprime ces 2 lignes
        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3');

        // ✅ Aperçu en édition (lecture seule)
        yield FormField::addTab('Image');

        yield TextField::new('fileName', 'Fichier')
            ->onlyOnForms()
            ->setFormTypeOption('disabled', true)
            ->setHelp('Upload via "Upload multiple" (menu admin).');

       
    }
}