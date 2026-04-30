<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use App\Form\MenuItemVariantType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

final class MenuItemCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ligne menu')
            ->setEntityLabelInPlural('Lignes menu')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'description'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvelle ligne'));

        return $this->addPublishableBatchActions($actions);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Publié'))
            ->add(EntityFilter::new('category', 'Catégorie'));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('category', 'Catégorie')->onlyOnIndex();
        yield TextField::new('name', 'Nom')->onlyOnIndex();
        yield MoneyField::new('price', 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Onglet Infos =====
        yield FormField::addTab('Infos')
            ->setIcon('fa fa-circle-info')
            ->setHelp(
                'Une "ligne" est un élément de la carte (un plat, une boisson, un café…). '
                . 'Elle appartient toujours à une catégorie ou sous-catégorie. '
                . 'Deux cas possibles pour le prix : un PRIX SIMPLE (une seule taille/format), '
                . 'ou PLUSIEURS VARIANTES (ex : bière 25cl / 33cl / 50cl) — voir l’onglet Variantes.'
            );

        yield AssociationField::new('category', 'Catégorie / sous-catégorie')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('Choisis la sous-catégorie à laquelle cet élément appartient (ex : "Bières" sous BOISSONS). Si la sous-catégorie n’existe pas, crée-la d’abord dans "Catégories menu".')
            ->onlyOnForms();

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Nom de l’élément tel qu’affiché sur la carte (ex : "Café expresso", "Burger Ventoux").')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setHelp('Optionnel. Courte description affichée sous le nom (ex : ingrédients, précisions).')
            ->onlyOnForms();

        yield TextField::new('unit', 'Unité / contenance')
            ->setHelp('Optionnel. Ex : "25 cl", "1 fruit (0,33cl)", "la part". Laisse vide si non pertinent ou si tu utilises des variantes.')
            ->setColumns('col-md-4')
            ->onlyOnForms();

        yield MoneyField::new('price', 'Prix simple')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setHelp('⚠ Remplis UNIQUEMENT si l’élément a un seul prix. Si plusieurs formats (ex : 25cl/50cl), laisse vide et utilise l’onglet Variantes.')
            ->onlyOnForms();

        yield IntegerField::new('position', 'Ordre')
            ->setColumns('col-md-2')
            ->setHelp('Plus petit = affiché en premier dans sa sous-catégorie.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-2')
            ->setHelp('Décoche pour cacher cet élément de la carte publique.')
            ->onlyOnForms();

        yield TextareaField::new('note', 'Note (optionnel)')
            ->setColumns('col-md-12')
            ->setHelp('Petite note affichée en italique sous la description (ex : "Sans gluten sur demande", "Selon arrivage").')
            ->onlyOnForms();

        // ===== Onglet Variantes =====
        yield FormField::addTab('Variantes')
            ->setIcon('fa fa-sliders')
            ->setHelp(
                'Utilise les variantes quand un élément a plusieurs tailles/formats avec des prix différents. '
                . 'Exemple typique : une bière en 25cl / 33cl / 50cl. '
                . 'Clique sur "+ Ajouter" puis renseigne un libellé (ex : "25 cl") et son prix. '
                . '⚠ Si tu utilises des variantes, laisse le "Prix simple" de l’onglet Infos VIDE.'
            );

        yield CollectionField::new('variants', 'Variantes')
            ->setEntryType(MenuItemVariantType::class)
            ->setEntryIsComplex(true)
            ->allowAdd()
            ->allowDelete()
            ->renderExpanded(false)
            ->setHelp('Chaque variante = un libellé (ex : "25 cl") + un prix. Ajoute autant de variantes que nécessaire.')
            ->onlyOnForms();
    }
}
