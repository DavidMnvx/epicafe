<?php

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use App\Form\MenuItemVariantType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

final class MenuItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ligne menu')
            ->setEntityLabelInPlural('Menu – lignes')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Infos');

        yield IdField::new('id')->onlyOnIndex();

        yield AssociationField::new('category', 'Catégorie / Sous-catégorie')
            ->setColumns('col-md-6');

        yield TextField::new('name', 'Nom')->setColumns('col-md-6');

        yield TextareaField::new('description', 'Description (optionnel)')
            ->setColumns('col-md-12')
            ->hideOnIndex();

        yield TextField::new('unit', 'Unité (optionnel)')
            ->setHelp('Ex: "25 cl", "1 fruit (0,33cl)"')
            ->setColumns('col-md-4');

        // Prix simple (si pas de variantes)
        yield MoneyField::new('price', 'Prix (simple)')
            ->setCurrency('EUR')
            ->setStoredAsCents(false) // car on stocke en decimal string
            ->setColumns('col-md-2');

        yield IntegerField::new('position', 'Ordre')->setColumns('col-md-2');

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-2');

        yield FormField::addTab('Variantes');

        yield CollectionField::new('variants', 'Variantes (optionnel)')
            ->setEntryType(MenuItemVariantType::class)
            ->setEntryIsComplex(true)
            ->allowAdd()
            ->allowDelete()
            ->renderExpanded(false)
            ->setHelp('Ajoute des variantes pour avoir plusieurs prix (ex: 25cl/33cl/50cl). Si tu mets des variantes, le prix simple devient optionnel.');
    }
}