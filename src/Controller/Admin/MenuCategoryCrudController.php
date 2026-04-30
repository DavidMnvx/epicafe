<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MenuCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

final class MenuCategoryCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return MenuCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie menu')
            ->setEntityLabelInPlural('Catégories menu')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'slug'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvelle catégorie'));

        return $this->addPublishableBatchActions($actions);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Publié'))
            ->add(EntityFilter::new('parent', 'Catégorie parente'));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Nom')->onlyOnIndex();
        yield AssociationField::new('parent', 'Parente')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Formulaire =====
        yield FormField::addTab('Infos')
            ->setIcon('fa fa-circle-info')
            ->setHelp(
                'Les catégories organisent la carte du menu en 2 niveaux : '
                . '1) les 3 grandes sections principales (SNACK, BOISSONS, ALCOOL) — créées sans parent ; '
                . '2) les sous-catégories (ex : "Bières", "Softs", "Cafés") — liées à une catégorie parente. '
                . 'Les lignes de menu (plats/boissons) sont ensuite rattachées à une sous-catégorie.'
            );

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Nom affiché sur la carte. Pour les catégories principales, utilise SNACK, BOISSONS ou ALCOOL (en majuscules).')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Slug (URL)')
            ->setTargetFieldName('name')
            ->setColumns('col-md-6')
            ->setHelp('Généré automatiquement depuis le nom. Laisse vide sauf cas particulier.')
            ->onlyOnForms();

        yield AssociationField::new('parent', 'Catégorie parente')
            ->setHelp(
                '• LAISSE VIDE pour créer une catégorie principale (ex : SNACK, BOISSONS, ALCOOL). '
                . '• CHOISIS un parent pour créer une sous-catégorie (ex : "Bières" sous "BOISSONS").'
            )
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->onlyOnForms();

        yield IntegerField::new('position', 'Ordre d’affichage')
            ->setColumns('col-md-3')
            ->setHelp('Plus petit = affiché en premier. Permet d’ordonner les sous-catégories au sein d’une section.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3')
            ->setHelp('Décoche pour cacher la catégorie et tous ses éléments de la carte publique.')
            ->onlyOnForms();
    }
}
