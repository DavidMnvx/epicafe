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

        // ===== Onglet 1 : tout ce qu'il faut pour publier =====
        // Un événement courant (titre, date, photo) se saisit sans jamais
        // quitter cet onglet ; les deux autres sont facultatifs.
        yield FormField::addTab('L’essentiel')
            ->setIcon('fa fa-circle-info')
            ->setHelp('De quoi publier un événement : un titre, une date, une photo. Le reste est facultatif.');

        yield TextField::new('title', 'Titre')
            ->setColumns('col-md-8')
            ->setRequired(true)
            ->setHelp('Ex : "Soirée aïoli", "Concert acoustique".')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Visible sur le site')
            ->renderAsSwitch(false)
            ->setColumns('col-md-4')
            ->setHelp('Décoche pour préparer l’événement sans l’afficher.')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setHelp('Court et parlant : c’est le texte affiché sur la page de l’événement.')
            ->onlyOnForms();

        yield DateTimeField::new('startAt', 'Date & heure')
            ->setColumns('col-md-6')
            ->setFormTypeOption('required', false)
            ->setHelp('À laisser vide seulement pour un rendez-vous hebdomadaire (onglet Options).')
            ->onlyOnForms();

        yield DateTimeField::new('endAt', 'Fin (facultatif)')
            ->setColumns('col-md-6')
            ->setFormTypeOption('required', false)
            ->setHelp('Si vide, l’événement passe en "terminé" une fois la date de début dépassée.')
            ->onlyOnForms();

        yield ImageField::new('imageFileName', 'Photo')
            ->setUploadDir('public/uploads/events')
            ->setBasePath('uploads/events')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('JPG, PNG ou WebP. Idéalement 1600×900 px, moins de 500 Ko.')
            ->onlyOnForms();

        yield TextField::new('imageUrl', '…ou lien vers une image')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Utilisé seulement si aucune photo n’est envoyée ci-contre.')
            ->onlyOnForms();

        // ===== Onglet 2 : l'offre, uniquement si l'événement en a une =====
        yield FormField::addTab('Ce qu’on sert')
            ->setIcon('fa fa-utensils')
            ->setHelp('À remplir seulement si l’événement propose à manger. Sinon, passe ton chemin.');

        yield MoneyField::new('menuPrice', 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-3')
            ->setHelp('Prix global, si c’est une formule à prix fixe. Accepte 19,00 ou 19.00.')
            ->onlyOnForms();

        yield from $this->buildProductFields(1);
        yield from $this->buildProductFields(2);
        yield from $this->buildProductFields(3);

        yield TextareaField::new('sideDish', 'Accompagnement')
            ->setColumns('col-md-6')
            ->setHelp('Ex : "Pommes de terre grenailles, sauce fromagère".')
            ->onlyOnForms();

        yield TextareaField::new('offerNote', 'Note')
            ->setColumns('col-md-6')
            ->setHelp('Ex : "Réservation conseillée", "Quantité limitée".')
            ->onlyOnForms();

        // ===== Onglet 3 : réglages rarement touchés =====
        yield FormField::addTab('Options')
            ->setIcon('fa fa-sliders')
            ->setHelp('Rendez-vous hebdomadaire, présentation et adresse de la page. Réglages rarement nécessaires.');

        yield BooleanField::new('isRecurring', 'Rendez-vous hebdomadaire')
            ->renderAsSwitch(false)
            ->setColumns('col-md-4')
            ->setHelp('Pour un événement qui revient chaque semaine (marché du mardi, jam du jeudi).')
            ->onlyOnForms();

        yield ChoiceField::new('recurringDayOfWeek', 'Jour de la semaine')
            ->setChoices(self::DAYS_OF_WEEK)
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp('Requis si le rendez-vous est hebdomadaire.')
            ->onlyOnForms();

        yield TimeField::new('recurringTime', 'Heure')
            ->setColumns('col-md-4')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('input', 'datetime_immutable')
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('with_seconds', false)
            ->setHelp('Format 24h, ex : 19:00.')
            ->onlyOnForms();

        yield ChoiceField::new('displayMode', 'Présentation')
            ->setChoices(self::DISPLAY_MODES)
            ->setColumns('col-md-4')
            ->setHelp('"Classique" = carte avec texte. "Affiche" = grand visuel, si tu as une belle image.')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Adresse de la page')
            ->setTargetFieldName('title')
            ->setColumns('col-md-8')
            ->setRequired(false)
            ->setHelp('Générée automatiquement depuis le titre. À ne modifier qu’en cas de besoin précis.')
            ->onlyOnForms();
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Field\FieldInterface>
     */
    private function buildProductFields(int $n): iterable
    {
        yield FormField::addFieldset($n === 1 ? 'Plat 1' : 'Plat ' . $n . ' (facultatif)')
            ->onlyOnForms();

        yield TextField::new("product{$n}Name", 'Nom')
            ->setColumns('col-md-4')
            ->setRequired(false)
            ->setHelp($n === 1 ? 'Ex : "Burger Ventoux"' : 'Laisse vide s’il n’y a pas de plat ' . $n . '.')
            ->onlyOnForms();

        yield MoneyField::new("product{$n}Price", 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setColumns('col-md-2')
            ->setRequired(false)
            ->setHelp('Accepte 12,50 ou 12.50.')
            ->onlyOnForms();

        yield TextareaField::new("product{$n}Ingredients", 'Composition')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Ex : "Pain maison, steak haché 150 g, tomme du Ventoux, oignons confits".')
            ->onlyOnForms();
    }
}
