<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

final class PartnerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Partner::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partenaire')
            ->setEntityLabelInPlural('Partenaires')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield FormField::addPanel('Informations');

        yield TextField::new('name', 'Nom')->setRequired(true);
        yield SlugField::new('slug')->setTargetFieldName('name')->onlyOnForms();
        yield UrlField::new('websiteUrl', 'Lien site partenaire');

        yield ChoiceField::new('type', 'Type de présentation')
            ->setChoices([
                'Premium (3 images)' => 'premium',
                'Secondaire (carte simple)' => 'secondary',
            ])
            ->renderAsBadges();

        yield IntegerField::new('position', 'Ordre');
        yield BooleanField::new('isPublished', 'Publié');

        yield TextareaField::new('description')->hideOnIndex();

        yield FormField::addPanel('Points forts (Premium uniquement)');
        yield TextField::new('bullet1')->hideOnIndex();
        yield TextField::new('bullet2')->hideOnIndex();
        yield TextField::new('bullet3')->hideOnIndex();

        yield FormField::addPanel('Images');

        yield ImageField::new('heroImageFileName', 'Image principale')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-hero-[timestamp].[extension]')
            ->hideOnIndex();

        yield ImageField::new('image2FileName', 'Image secondaire 1')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-2-[timestamp].[extension]')
            ->hideOnIndex();

        yield ImageField::new('image3FileName', 'Image secondaire 2')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-3-[timestamp].[extension]')
            ->hideOnIndex();

        yield ImageField::new('logoFileName', 'Logo')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->hideOnIndex();
    }
}