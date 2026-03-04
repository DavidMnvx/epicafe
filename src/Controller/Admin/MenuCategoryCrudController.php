<?php

namespace App\Controller\Admin;

use App\Entity\MenuCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class MenuCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie menu')
            ->setEntityLabelInPlural('Catégories menu')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Infos');

        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Nom')->setColumns('col-md-6');

        
        yield AssociationField::new('parent', 'Catégorie parente')
            ->setHelp('Vide = catégorie principale. Sinon = sous-catégorie.')
            ->setColumns('col-md-6')
            ->setRequired(false);

        yield IntegerField::new('position', 'Ordre')->setColumns('col-md-2');
        yield BooleanField::new('isPublished', 'Publié')->renderAsSwitch(false)->setColumns('col-md-2');
    }
}