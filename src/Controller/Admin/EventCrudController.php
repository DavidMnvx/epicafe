<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;

final class EventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Événement')
            ->setEntityLabelInPlural('Événements')
            ->setDefaultSort(['startAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Event) {
            $this->prepareEventBeforeSave($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Event) {
            $this->prepareEventBeforeSave($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function prepareEventBeforeSave(Event $event): void
    {
        // 1) Normalise le prix : "19,00" => "19.00"
        if ($event->getMenuPrice() !== null) {
            $event->setMenuPrice(str_replace(',', '.', $event->getMenuPrice()));
        if ($event->getProduct1Price() !== null) {
            $event->setProduct1Price(str_replace(',', '.', $event->getProduct1Price()));
        }
        if ($event->getProduct2Price() !== null) {
            $event->setProduct2Price(str_replace(',', '.', $event->getProduct2Price()));
        }
        if ($event->getProduct3Price() !== null) {
            $event->setProduct3Price(str_replace(',', '.', $event->getProduct3Price()));
        }
        }

        // 2) Si permanent => startAt auto (et endAt nul)
        // (ta méthode dans l’entity est censée gérer le "prochain jour" + heure)
        $event->ensureStartAtForRecurring();

        // 3) Si NON permanent et startAt vide => on met une valeur par défaut (évite erreurs de formulaire)
        // (tu peux enlever ce bloc si tu veux forcer l’utilisateur à saisir une date)
        if (!$event->isRecurring() && $event->getStartAt() === null) {
            $event->setStartAt(new \DateTimeImmutable('+7 days'));
        }

        // 4) updatedAt si tu veux le tenir à jour ici (optionnel)
        $event->setUpdatedAt(new \DateTimeImmutable());
    }
    
    
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        /*
        ======================================
        ONGLET — INFOS
        ======================================
        */
        yield FormField::addTab('Informations');

        yield TextField::new('title', 'Titre')
            ->setColumns('col-md-8')
            ->setRequired(true);

        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->onlyOnForms();
            
        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->hideOnIndex();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3');

        yield ChoiceField::new('displayMode')
            ->setChoices([
            'Classique' => 'classic',
            'Affiche (flyer)' => 'poster',           
        ])
            ->renderExpanded(false);
        

        /*
        ======================================
        ONGLET — DATES (événement “normal”)
        ======================================
        */
        yield FormField::addTab('Dates');

        yield DateTimeField::new('startAt', 'Début')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setFormTypeOption('required', false)
            ->setHelp('Laisse vide si “Événement permanent” est activé.');

        yield DateTimeField::new('endAt', 'Fin')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setFormTypeOption('required', false)
            ->setHelp('Optionnel. Si vide : on considère l’événement passé après le début.');

        /*
        ======================================
        ONGLET — RÉCURRENCE (événement permanent)
        ======================================
        */
        yield FormField::addTab('Permanent');

        yield BooleanField::new('isRecurring', 'Événement permanent')
            ->renderAsSwitch(false)
            ->setColumns('col-md-4');

        yield ChoiceField::new('recurringDayOfWeek', 'Jour')
            ->setChoices([
                'Lundi' => 1,
                'Mardi' => 2,
                'Mercredi' => 3,
                'Jeudi' => 4,
                'Vendredi' => 5,
                'Samedi' => 6,
                'Dimanche' => 7,
            ])
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp('Uniquement si permanent.');

        yield TimeField::new('recurringTime', 'Heure')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('input', 'datetime_immutable')
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('with_seconds', false)
            ->setHelp('Ex : 19:00 (uniquement si permanent).');

        /*
        ======================================
        ONGLET — MENU
        ======================================
        */

       
        yield FormField::addTab('Menu');

        yield FormField::addFieldset('Menu');

        yield TextField::new('menuStarter', 'Entrée')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('menuMain', 'Plat')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('menuDessert', 'Dessert')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('menuDessert2', 'Dessert (2) - option')
            ->setColumns('col-md-4')
            ->hideOnIndex();

       

        yield TextareaField::new('menu', 'Texte libre (optionnel)')
            ->setColumns('col-md-12')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Ex: “Ce vendredi : aïoli / dessert maison…”');
        
        yield FormField::addFieldset('Prix du menu');

        yield MoneyField::new('menuPrice', 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-3')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Accepte 19,00 ou 19.00');


        yield FormField::addFieldset('Produits / Burgers');

        yield TextField::new('product1Name', 'Produit 1 - nom')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield MoneyField::new('product1Price', 'Produit 1 - prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextareaField::new('product1Ingredients', 'Produit 1 - ingrédients')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextField::new('product2Name', 'Produit 2 - nom')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield MoneyField::new('product2Price', 'Produit 2 - prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextareaField::new('product2Ingredients', 'Produit 2 - ingrédients')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextField::new('product3Name', 'Produit 3 - nom')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield MoneyField::new('product3Price', 'Produit 3 - prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextareaField::new('product3Ingredients', 'Produit 3 - ingrédients')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex();
        
        yield TextareaField::new('sideDish', 'Accompagnement')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Ex : Pommes de terre grenailles sauce fromagère');
        
        yield TextareaField::new('offerNote', 'Note / texte libre')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Ex : Réservation conseillée, quantité limitée, etc.');
        /*
        ======================================
        ONGLET — VISUELS
        ======================================
        */
        yield FormField::addTab('Visuels');

        yield ImageField::new('imageFileName', 'Image uploadée')
            ->setUploadDir('public/uploads/events')
            ->setBasePath('uploads/events')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex();

        yield TextField::new('imageUrl', 'Image URL (fallback)')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->hideOnIndex()
            ->setHelp('Utilisée si aucune image uploadée.');
    }
}