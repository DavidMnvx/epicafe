<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

final class EventCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }


    private const DAYS_OF_WEEK = [
        'Lundi'    => 1,
        'Mardi'    => 2,
        'Mercredi' => 3,
        'Jeudi'    => 4,
        'Vendredi' => 5,
        'Samedi'   => 6,
        'Dimanche' => 7,
    ];

    private const DISPLAY_MODES = [
        'Classique'       => 'classic',
        'Affiche (flyer)' => 'poster',
    ];

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
            ->setSearchFields(['title', 'description'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('duplicate', 'Dupliquer', 'fa fa-copy')
            ->linkToCrudAction('duplicateEntity');

        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_EDIT, $duplicate)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvel événement'));

        return $this->addPublishableBatchActions($actions);
    }

    public function duplicateEntity(EntityManagerInterface $em): Response
    {
        /** @var Event|null $source */
        $source = $this->getContext()?->getEntity()?->getInstance();

        if (!$source instanceof Event) {
            $this->addFlash('danger', 'Événement introuvable.');

            return $this->redirect($this->adminUrlGenerator->setAction(Action::INDEX)->generateUrl());
        }

        $clone = (clone $source)
            ->setTitle($source->getTitle() . ' (copie)')
            ->setSlug(null)
            ->setIsPublished(false)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($clone);
        $em->flush();

        $this->addFlash('success', 'Événement dupliqué.');

        return $this->redirect(
            $this->adminUrlGenerator
                ->setAction(Action::EDIT)
                ->setEntityId($clone->getId())
                ->generateUrl()
        );
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isPublished', 'Publié'))
            ->add(BooleanFilter::new('isRecurring', 'Permanent'))
            ->add(ChoiceFilter::new('displayMode', 'Affichage')->setChoices(self::DISPLAY_MODES))
            ->add(DateTimeFilter::new('startAt', 'Début'));
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        $this->prepareEvent($entity);
        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, $entity): void
    {
        $this->prepareEvent($entity);
        parent::updateEntity($em, $entity);
    }

    private function prepareEvent(Event $event): void
    {
        $this->normalizeDecimal($event->getMenuPrice(), [$event, 'setMenuPrice']);
        $this->normalizeDecimal($event->getProduct1Price(), [$event, 'setProduct1Price']);
        $this->normalizeDecimal($event->getProduct2Price(), [$event, 'setProduct2Price']);
        $this->normalizeDecimal($event->getProduct3Price(), [$event, 'setProduct3Price']);

        $event->ensureStartAtForRecurring();

        if (!$event->isRecurring() && $event->getStartAt() === null) {
            $event->setStartAt(new \DateTimeImmutable('+7 days'));
        }
        // updatedAt est géré par #[ORM\PreUpdate] sur Event
    }

    private function normalizeDecimal(?string $value, callable $setter): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $setter(str_replace(',', '.', $value));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();

        yield ImageField::new('imageFileName', 'Image')
            ->setBasePath('uploads/events')
            ->onlyOnIndex();

        yield TextField::new('title', 'Titre')->onlyOnIndex();

        yield ChoiceField::new('status', 'État')
            ->setChoices([
                'À venir'   => Event::STATUS_UPCOMING,
                'Passé'     => Event::STATUS_PAST,
                'Permanent' => Event::STATUS_RECURRING,
            ])
            ->renderAsBadges([
                Event::STATUS_UPCOMING  => 'success',
                Event::STATUS_PAST      => 'secondary',
                Event::STATUS_RECURRING => 'info',
            ])
            ->onlyOnIndex();

        yield DateTimeField::new('startAt', 'Début')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Onglet Informations =====
        yield FormField::addTab('Informations')
            ->setIcon('fa fa-circle-info')
            ->setHelp('Titre, description et visibilité de l’événement. C’est ce qui apparaît sur le site public.');

        yield TextField::new('title', 'Titre')
            ->setColumns('col-md-8')
            ->setRequired(true)
            ->setHelp('Le nom de l’événement tel qu’il sera affiché. Ex : "Soirée aïoli", "Concert acoustique".')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Slug (URL)')
            ->setTargetFieldName('title')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp('Généré automatiquement à partir du titre. Laisse vide sauf cas particulier.')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setHelp('Texte descriptif affiché sur la page de l’événement. Reste court et parlant.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3')
            ->setHelp('Décoche pour cacher l’événement du site sans le supprimer.')
            ->onlyOnForms();

        yield ChoiceField::new('displayMode', 'Mode d’affichage')
            ->setChoices(self::DISPLAY_MODES)
            ->setColumns('col-md-4')
            ->setHelp('"Classique" = carte avec texte. "Affiche" = grand visuel type flyer (idéal si tu as une belle image).')
            ->onlyOnForms();

        // ===== Onglet Dates =====
        yield FormField::addTab('Dates')
            ->setIcon('fa fa-calendar')
            ->setHelp('Pour un événement à une date précise. Pour un événement qui revient chaque semaine, utilise plutôt l’onglet "Permanent".');

        yield DateTimeField::new('startAt', 'Date & heure de début')
            ->setColumns('col-md-6')
            ->setFormTypeOption('required', false)
            ->setHelp('⚠ Laisse vide si l’événement est permanent (onglet Permanent).')
            ->onlyOnForms();

        yield DateTimeField::new('endAt', 'Date & heure de fin')
            ->setColumns('col-md-6')
            ->setFormTypeOption('required', false)
            ->setHelp('Optionnel. Si vide, l’événement bascule en "passé" dès que la date de début est dépassée.')
            ->onlyOnForms();

        // ===== Onglet Permanent =====
        yield FormField::addTab('Permanent')
            ->setIcon('fa fa-rotate')
            ->setHelp('Pour un événement qui revient chaque semaine au même jour/heure (ex : marché du mardi, jam-session du jeudi soir). La prochaine occurrence est calculée automatiquement.');

        yield BooleanField::new('isRecurring', 'Événement permanent')
            ->renderAsSwitch(false)
            ->setColumns('col-md-4')
            ->setHelp('Active pour transformer cet événement en rendez-vous hebdomadaire.')
            ->onlyOnForms();

        yield ChoiceField::new('recurringDayOfWeek', 'Jour de la semaine')
            ->setChoices(self::DAYS_OF_WEEK)
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp('Requis si "Événement permanent" est activé.')
            ->onlyOnForms();

        yield TimeField::new('recurringTime', 'Heure')
            ->setColumns('col-md-4')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('input', 'datetime_immutable')
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('with_seconds', false)
            ->setHelp('Format 24h (ex : 19:00). Requis si permanent.')
            ->onlyOnForms();

        // ===== Onglet Menu =====
        yield FormField::addTab('Menu')
            ->setIcon('fa fa-utensils')
            ->setHelp('Décris le menu/offre associée à l’événement. Tout est optionnel : remplis uniquement ce qui est pertinent. Tu peux mélanger menu classique (entrée/plat/dessert) ET produits individuels (ex : burgers).');

        yield FormField::addFieldset('Composition du menu')
            ->setHelp('Pour un menu type entrée/plat/dessert à prix fixe. Laisse vide si tu fais uniquement des produits individuels ci-dessous.')
            ->onlyOnForms();

        yield TextField::new('menuStarter', 'Entrée')
            ->setColumns('col-md-3')
            ->setHelp('Ex : "Salade de tomates"')
            ->onlyOnForms();
        yield TextField::new('menuMain', 'Plat')
            ->setColumns('col-md-3')
            ->setHelp('Ex : "Aïoli provençal"')
            ->onlyOnForms();
        yield TextField::new('menuDessert', 'Dessert')
            ->setColumns('col-md-3')
            ->setHelp('Ex : "Tarte aux fruits"')
            ->onlyOnForms();
        yield TextField::new('menuDessert2', 'Dessert (2) — option')
            ->setColumns('col-md-3')
            ->setHelp('Second dessert au choix, si applicable.')
            ->onlyOnForms();

        yield TextareaField::new('menu', 'Texte libre')
            ->setColumns('col-md-12')
            ->setHelp('Utile si le format entrée/plat/dessert ne colle pas. Ex : "Ce vendredi : aïoli maison servi avec dessert au choix".')
            ->onlyOnForms();

        yield MoneyField::new('menuPrice', 'Prix du menu')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-3')
            ->setHelp('Prix du menu complet. Accepte 19,00 ou 19.00.')
            ->onlyOnForms();

        yield FormField::addFieldset('Produits / Burgers')
            ->setHelp('Pour proposer jusqu’à 3 produits individuels (ex : 3 burgers différents). Chaque produit a son propre nom, prix et liste d’ingrédients.')
            ->onlyOnForms();

        yield from $this->buildProductFields(1);
        yield from $this->buildProductFields(2);
        yield from $this->buildProductFields(3);

        yield FormField::addFieldset('Accompagnement & note')
            ->setHelp('Informations complémentaires affichées sous le menu.')
            ->onlyOnForms();

        yield TextareaField::new('sideDish', 'Accompagnement')
            ->setColumns('col-md-6')
            ->setHelp('Servi avec les produits. Ex : "Pommes de terre grenailles, sauce fromagère".')
            ->onlyOnForms();

        yield TextareaField::new('offerNote', 'Note / texte libre')
            ->setColumns('col-md-6')
            ->setHelp('Ex : "Réservation conseillée", "Quantité limitée", "Végétarien possible sur demande".')
            ->onlyOnForms();

        // ===== Onglet Visuels =====
        yield FormField::addTab('Visuels')
            ->setIcon('fa fa-image')
            ->setHelp('Image de présentation de l’événement. Formats : JPG, PNG ou WebP. Taille recommandée : 1600×900 px (format paysage 16:9), poids < 500 Ko. Pour le mode "Affiche", privilégie le portrait 1200×1500 px.');

        yield ImageField::new('imageFileName', 'Image uploadée')
            ->setUploadDir('public/uploads/events')
            ->setBasePath('uploads/events')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Glisse ou sélectionne un fichier depuis ton ordinateur. 1600×900 px (paysage) ou 1200×1500 px (affiche/portrait). Max 2 Mo.')
            ->onlyOnForms();

        yield TextField::new('imageUrl', 'Image URL (fallback)')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Alternative : lien direct vers une image en ligne. Utilisé uniquement si aucune image n’est uploadée ci-dessus.')
            ->onlyOnForms();
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Field\FieldInterface>
     */
    private function buildProductFields(int $n): iterable
    {
        yield TextField::new("product{$n}Name", "Produit {$n} — nom")
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp($n === 1 ? 'Ex : "Burger Ventoux"' : 'Laisse vide si tu n’as pas de produit ' . $n . '.')
            ->onlyOnForms();

        yield MoneyField::new("product{$n}Price", "Produit {$n} — prix")
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setRequired(false)
            ->setHelp('Prix unitaire (accepte 12,50 ou 12.50).')
            ->onlyOnForms();

        yield TextareaField::new("product{$n}Ingredients", "Produit {$n} — ingrédients")
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Liste des ingrédients, séparés par des virgules. Ex : "Pain maison, steak haché 150g, tomme du Ventoux, oignons confits".')
            ->onlyOnForms();
    }
}
