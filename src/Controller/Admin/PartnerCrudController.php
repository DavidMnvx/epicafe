<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Partner;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

final class PartnerCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    private const TYPE_CHOICES = [
        'Premium'    => Partner::TYPE_PREMIUM,
        'Partenaire' => Partner::TYPE_PARTNER,
        'Secondaire' => Partner::TYPE_SECONDARY,
    ];

    private const TYPE_BADGES = [
        Partner::TYPE_PREMIUM   => 'success',
        Partner::TYPE_PARTNER   => 'info',
        Partner::TYPE_SECONDARY => 'secondary',
    ];

    public static function getEntityFqcn(): string
    {
        return Partner::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Partenaire')
            ->setEntityLabelInPlural('Partenaires')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'description'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouveau partenaire'));

        return $this->addPublishableBatchActions($actions);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Publié'))
            ->add(ChoiceFilter::new('type', 'Type')->setChoices(self::TYPE_CHOICES));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('logoFileName', 'Logo')
            ->setBasePath('uploads/partners')
            ->onlyOnIndex();
        yield TextField::new('name', 'Nom')->onlyOnIndex();
        yield ChoiceField::new('type', 'Type')
            ->setChoices(self::TYPE_CHOICES)
            ->renderAsBadges(self::TYPE_BADGES)
            ->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Onglet Infos =====
        yield FormField::addTab('Infos')
            ->setIcon('fa fa-circle-info')
            ->setHelp('Informations générales du partenaire. Le "Type" détermine comment il sera affiché sur le site.');

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Nom commercial du partenaire tel qu’il apparaît sur le site.')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Slug (URL)')
            ->setTargetFieldName('name')
            ->setRequired(false)
            ->setColumns('col-md-6')
            ->setHelp('Généré automatiquement à partir du nom. Laisse vide sauf cas particulier.')
            ->onlyOnForms();

        yield ChoiceField::new('type', 'Type')
            ->setChoices(self::TYPE_CHOICES)
            ->renderAsNativeWidget()
            ->setHelp('• Premium = grand bloc avec 3 images (onglet "Images Premium"). • Partenaire = card avec logo. • Secondaire = petit logo dans la liste secondaire.')
            ->setColumns('col-md-4')
            ->onlyOnForms();

        yield IntegerField::new('position', 'Ordre d’affichage')
            ->setHelp('Plus petit = affiché en premier. Ex : 0 pour le mettre tout en haut, 10 pour le descendre.')
            ->setColumns('col-md-2')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-2')
            ->setHelp('Décoche pour cacher ce partenaire du site.')
            ->onlyOnForms();

        yield UrlField::new('websiteUrl', 'Site web')
            ->setColumns('col-md-8')
            ->setHelp('URL complète avec https:// (ex : https://monpartenaire.fr). Un clic sur le partenaire renverra vers ce lien.')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setHelp('Présentation du partenaire affichée sur le site. Utilisée surtout pour les partenaires Premium.')
            ->onlyOnForms();

        // ===== Onglet Points =====
        yield FormField::addTab('Points forts')
            ->setIcon('fa fa-list-check')
            ->setHelp('Jusqu’à 3 points courts mis en avant dans le bloc Premium (ex : "Produits locaux", "Livraison gratuite"). Utilisés uniquement si le type est Premium.');

        yield TextField::new('bullet1', 'Point 1')
            ->setColumns('col-md-4')
            ->setHelp('Ex : "Produits 100% locaux"')
            ->onlyOnForms();
        yield TextField::new('bullet2', 'Point 2')
            ->setColumns('col-md-4')
            ->setHelp('Ex : "Livraison gratuite"')
            ->onlyOnForms();
        yield TextField::new('bullet3', 'Point 3')
            ->setColumns('col-md-4')
            ->setHelp('Ex : "Ouvert 7j/7"')
            ->onlyOnForms();

        // ===== Onglet Logo =====
        yield FormField::addTab('Logo')
            ->setIcon('fa fa-icons')
            ->setHelp('Logo affiché pour tous les types de partenaires. Format carré ou horizontal, fond transparent de préférence. Taille recommandée : 500×500 px (PNG/SVG/WebP), poids < 200 Ko.');

        yield ImageField::new('logoFileName', 'Logo uploadé')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('Idéal : PNG/SVG avec fond transparent, 500×500 px, max 200 Ko. Évite les JPG qui n’ont pas de transparence.')
            ->setColumns('col-md-8')
            ->onlyOnForms();

        yield TextField::new('logoUrl', 'Logo URL (fallback)')
            ->setColumns('col-md-8')
            ->setHelp('Alternative : lien direct vers un logo en ligne. Utilisé uniquement si aucun fichier n’est uploadé ci-dessus.')
            ->onlyOnForms();

        // ===== Onglet Images Premium =====
        yield FormField::addTab('Images Premium')
            ->setIcon('fa fa-images')
            ->setHelp('⚠ Uniquement pour les partenaires de type "Premium" : 3 images affichées côte à côte dans le grand bloc. Format paysage recommandé : 1200×800 px (3:2), JPG ou WebP, max 500 Ko chacune.');

        yield ImageField::new('heroImageFileName', 'Image principale')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-hero-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->setHelp('La plus grande des trois, mise en avant. 1200×800 px recommandé.')
            ->onlyOnForms();

        yield ImageField::new('image2FileName', 'Image 2')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-2-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->setHelp('Image complémentaire — produit, ambiance, etc.')
            ->onlyOnForms();

        yield ImageField::new('image3FileName', 'Image 3')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-3-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->setHelp('Troisième image du bloc Premium.')
            ->onlyOnForms();
    }
}
