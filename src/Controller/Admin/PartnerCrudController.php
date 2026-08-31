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

        // ===== Onglet 1 : tout ce qu'il faut pour publier =====
        // L'ordre et le type se règlent au glisser-déposer sur la page « Les
        // partenaires » : ni champ « position », ni onglet dédié.
        yield FormField::addTab('L’essentiel')
            ->setIcon('fa fa-circle-info')
            ->setHelp('De quoi présenter un partenaire : un nom, une photo, quelques mots. Le rang et la mise en avant se règlent en glissant les cartes sur la page « Les partenaires ».');

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Nom commercial tel qu’il apparaît sur le site.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Visible sur le site')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3')
            ->setHelp('Décoche pour préparer la fiche sans l’afficher.')
            ->onlyOnForms();

        yield UrlField::new('websiteUrl', 'Site web')
            ->setColumns('col-md-8')
            ->setHelp('Un clic sur le partenaire renverra vers ce lien. Facultatif.')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setHelp('Quelques phrases de présentation, affichées sur le site.')
            ->onlyOnForms();

        yield ImageField::new('heroImageFileName', 'Photo principale')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-hero-[timestamp].[extension]')
            ->setColumns('col-md-6')
            ->setHelp('La photo qui représente le partenaire. Paysage recommandé — elle est optimisée automatiquement, un PDF est accepté.')
            ->onlyOnForms();

        // ===== Onglet 2 : compléments, surtout pour les Premium =====
        yield FormField::addTab('Détails')
            ->setIcon('fa fa-sliders')
            ->setHelp('Compléments facultatifs — surtout utiles pour les partenaires mis en avant (Premium).');

        yield FormField::addFieldset('Points forts (bloc Premium)')
            ->setHelp('Jusqu’à 3 arguments courts. Ex : "Produits 100% locaux", "Ouvert 7j/7".')
            ->onlyOnForms();

        yield TextField::new('bullet1', 'Point 1')->setColumns('col-md-4')->onlyOnForms();
        yield TextField::new('bullet2', 'Point 2')->setColumns('col-md-4')->onlyOnForms();
        yield TextField::new('bullet3', 'Point 3')->setColumns('col-md-4')->onlyOnForms();

        yield FormField::addFieldset('Images complémentaires (bloc Premium)')
            ->setHelp('Deux photos de plus dans le grand bloc — laisse vide pour un affichage à une seule image.')
            ->onlyOnForms();

        yield ImageField::new('image2FileName', 'Image 2')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-2-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->onlyOnForms();

        yield ImageField::new('image3FileName', 'Image 3')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-3-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->onlyOnForms();

        yield FormField::addFieldset('Divers')->onlyOnForms();

        yield ImageField::new('logoFileName', 'Logo')
            ->setUploadDir('public/uploads/partners')
            ->setBasePath('uploads/partners')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setColumns('col-md-4')
            ->setHelp('Facultatif — PNG à fond transparent de préférence.')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Adresse (slug)')
            ->setTargetFieldName('name')
            ->setRequired(false)
            ->setColumns('col-md-4')
            ->setHelp('Générée depuis le nom, à ne modifier qu’en cas de besoin.')
            ->onlyOnForms();

        // Le type reste modifiable ici pour qui préfère le formulaire, mais le
        // geste normal est le glisser-déposer entre sections.
        yield ChoiceField::new('type', 'Mise en avant')
            ->setChoices(self::TYPE_CHOICES)
            ->renderAsNativeWidget()
            ->setColumns('col-md-4')
            ->setHelp('Se règle aussi en glissant la carte d’une section à l’autre.')
            ->onlyOnForms();
    }
}
