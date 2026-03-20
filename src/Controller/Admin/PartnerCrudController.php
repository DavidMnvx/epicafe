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
    // ===== ONGLET 1 : Infos =====
    yield FormField::addTab('Infos');

    yield IdField::new('id')->onlyOnIndex();

    yield TextField::new('name', 'Nom')
        ->setRequired(true)
        ->setColumns('col-md-6');

    yield SlugField::new('slug', 'Slug')
        ->setTargetFieldName('name')
        ->setRequired(false)
        ->onlyOnForms()
        ->setColumns('col-md-6');

    yield ChoiceField::new('type', 'Type')
        ->setChoices([
            'Premium' => Partner::TYPE_PREMIUM,
            'Secondaire' => Partner::TYPE_SECONDARY,
            
        ])
        ->renderAsNativeWidget()
        ->setHelp('Premium = affichage “grand bloc” avec 3 images. Secondaire = affichage card/photo.')
        ->setColumns('col-md-3');

    yield IntegerField::new('position', 'Ordre')
        ->setHelp('0 = en premier')
        ->setColumns('col-md-2');

    yield BooleanField::new('isPublished', 'Publié')
        ->renderAsSwitch(false)
        ->setColumns('col-md-2');

    yield UrlField::new('websiteUrl', 'Site web')
        ->setColumns('col-md-8');

    yield TextareaField::new('description', 'Description')
        ->hideOnIndex()
        ->setColumns('col-md-12');


    // ===== ONGLET 2 : Points =====
    yield FormField::addTab('Points');

    yield TextField::new('bullet1', 'Point 1')->hideOnIndex()->setColumns('col-md-4');
    yield TextField::new('bullet2', 'Point 2')->hideOnIndex()->setColumns('col-md-4');
    yield TextField::new('bullet3', 'Point 3')->hideOnIndex()->setColumns('col-md-4');


    // ===== ONGLET 3 : Logo =====
    yield FormField::addTab('Logo');

    yield ImageField::new('logoFileName', 'Logo uploadé')
        ->setUploadDir('public/uploads/partners')
        ->setBasePath('uploads/partners')
        ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
        ->setHelp('PNG/SVG/WebP recommandé')
        ->hideOnIndex()
        ->setColumns('col-md-8');

    yield TextField::new('logoUrl', 'Logo URL (fallback)')
        ->hideOnIndex()
        ->setColumns('col-md-8');

    yield ImageField::new('logoFileName', 'Aperçu logo')
        ->setBasePath('uploads/partners')
        ->onlyOnIndex();


    // ===== ONGLET 4 : Images Premium =====
    yield FormField::addTab('Images Premium');

    yield ImageField::new('heroImageFileName', 'Image principale')
        ->setUploadDir('public/uploads/partners')
        ->setBasePath('uploads/partners')
        ->setUploadedFileNamePattern('[slug]-hero-[timestamp].[extension]')
        ->hideOnIndex()
        ->setColumns('col-md-4');

    yield ImageField::new('image2FileName', 'Image 2')
        ->setUploadDir('public/uploads/partners')
        ->setBasePath('uploads/partners')
        ->setUploadedFileNamePattern('[slug]-2-[timestamp].[extension]')
        ->hideOnIndex()
        ->setColumns('col-md-4');

    yield ImageField::new('image3FileName', 'Image 3')
        ->setUploadDir('public/uploads/partners')
        ->setBasePath('uploads/partners')
        ->setUploadedFileNamePattern('[slug]-3-[timestamp].[extension]')
        ->hideOnIndex()
        ->setColumns('col-md-4');

        yield ChoiceField::new('type', 'Type')
    ->setChoices([
        'Premium' => Partner::TYPE_PREMIUM,
        'Normal' => Partner::TYPE_PARTNER,
        'Secondaire' => Partner::TYPE_SECONDARY,
    ])
    ->renderAsBadges([
        Partner::TYPE_PREMIUM => 'success',
        Partner::TYPE_PARTNER => 'info',
        Partner::TYPE_SECONDARY => 'secondary',
    ])
    ->onlyOnIndex();
    }
}