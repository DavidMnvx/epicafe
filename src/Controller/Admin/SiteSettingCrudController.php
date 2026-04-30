<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSetting;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class SiteSettingCrudController extends AbstractCrudController
{
    public const GROUP_LABELS = [
        SiteSetting::GROUP_CONTACT    => '📞 Contact',
        SiteSetting::GROUP_SOCIAL     => '🌐 Réseaux sociaux & Maps',
        SiteSetting::GROUP_NAVIGATION => '🧭 Visibilité des pages',
        SiteSetting::GROUP_CLOSURE    => '⚠ Fermeture exceptionnelle',
        SiteSetting::GROUP_GENERAL    => '⚙ Général & légal',
    ];

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paramètre du site')
            ->setEntityLabelInPlural('Paramètres du site')
            ->setPageTitle(Crud::PAGE_INDEX, 'Paramètres globaux')
            ->setPageTitle(Crud::PAGE_EDIT, fn (SiteSetting $s) => 'Modifier : ' . $s->getLabel())
            ->setSearchFields(['key', 'label', 'value'])
            ->setDefaultSort(['groupName' => 'ASC', 'position' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->overrideTemplate('crud/index', 'admin/site_setting/index.html.twig')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Tous les paramètres globaux du site (téléphone, email, réseaux sociaux, bandeau de fermeture, etc.). '
                . 'Modifie une valeur ici → elle se met à jour partout sur le site automatiquement. '
                . 'Les booléens (activations) se modifient en cliquant directement sur le switch.'
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $toggle = Action::new('toggle', 'Activer/Désactiver', 'fa fa-toggle-on')
            ->linkToCrudAction('toggleBoolean')
            ->displayIf(fn (SiteSetting $s) => $s->getType() === SiteSetting::TYPE_BOOLEAN);

        return $actions
            ->add(Crud::PAGE_INDEX, $toggle)
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    /**
     * Inverse la valeur d'un paramètre booléen depuis la grille (action liée).
     * On charge l'entité via l'ID fourni en query string (entityId) pour
     * éviter les problèmes d'initialisation du contexte CRUD.
     */
    public function toggleBoolean(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = $request?->query->get('entityId');

        if ($id !== null) {
            /** @var SiteSetting|null $setting */
            $setting = $this->em->getRepository(SiteSetting::class)->find($id);

            if ($setting instanceof SiteSetting && $setting->getType() === SiteSetting::TYPE_BOOLEAN) {
                $setting->setValue($setting->asBool() ? '0' : '1');
                $this->em->flush();
                $this->addFlash(
                    'success',
                    sprintf('"%s" %s.', $setting->getLabel(), $setting->asBool() ? 'activé' : 'désactivé')
                );
            }
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            ChoiceFilter::new('groupName', 'Catégorie')
                ->setChoices(array_flip(self::GROUP_LABELS))
        );
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Index =====
        yield ChoiceField::new('groupName', 'Catégorie')
            ->setChoices(array_flip(self::GROUP_LABELS))
            ->renderAsBadges([
                SiteSetting::GROUP_CONTACT    => 'success',
                SiteSetting::GROUP_SOCIAL     => 'info',
                SiteSetting::GROUP_NAVIGATION => 'primary',
                SiteSetting::GROUP_CLOSURE    => 'warning',
                SiteSetting::GROUP_GENERAL    => 'secondary',
            ])
            ->onlyOnIndex();

        yield TextField::new('label', 'Paramètre')->onlyOnIndex();
        yield TextField::new('key', 'Clé technique')->onlyOnIndex();

        yield Field::new('value', 'Valeur actuelle')
            ->onlyOnIndex()
            ->formatValue(function ($value, SiteSetting $entity) {
                if ($value === null || $value === '') {
                    return '<em style="color:#999;">— vide —</em>';
                }
                if ($entity->getType() === SiteSetting::TYPE_BOOLEAN) {
                    return $entity->asBool() ? '✅ Activé' : '⛔ Désactivé';
                }
                $str = (string) $value;
                return mb_strlen($str) > 60 ? mb_substr($str, 0, 60) . '…' : $str;
            });

        yield DateTimeField::new('updatedAt', 'Dernière modif.')->onlyOnIndex();

        // ===== Formulaire d'édition =====
        // Affiche la clé en lecture seule pour repère
        yield TextField::new('key', 'Clé technique')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Identifiant utilisé dans le code (non modifiable).')
            ->onlyOnForms();

        yield TextField::new('label', 'Libellé')
            ->setFormTypeOption('disabled', true)
            ->onlyOnForms();

        yield TextareaField::new('description', 'À quoi ça sert')
            ->setFormTypeOption('disabled', true)
            ->setNumOfRows(3)
            ->onlyOnForms();

        // Champ "valeur" — type adapté selon SiteSetting::type
        // (EasyAdmin ne le fait pas dynamiquement, on bascule via configureFields/PageName + entité)
        yield from $this->buildValueField($pageName);
    }

    /**
     * Renvoie le champ "valeur" adapté au type du paramètre édité.
     * Le type est lu depuis l'entité courante (disponible via getContext en PAGE_EDIT/PAGE_NEW).
     */
    private function buildValueField(string $pageName): iterable
    {
        if ($pageName !== Crud::PAGE_EDIT && $pageName !== Crud::PAGE_NEW) {
            return;
        }

        $entity = $this->getContext()?->getEntity()?->getInstance();
        $type = $entity instanceof SiteSetting ? $entity->getType() : SiteSetting::TYPE_TEXT;

        $field = match ($type) {
            SiteSetting::TYPE_BOOLEAN => ChoiceField::new('value', 'Activation')
                ->setChoices(['✅ Activé' => '1', '⛔ Désactivé' => '0'])
                ->renderExpanded(true)
                ->setHelp('Choisis "Activé" pour activer ce paramètre, "Désactivé" pour le couper.'),

            SiteSetting::TYPE_URL => UrlField::new('value', 'URL')
                ->setHelp('Lien complet, doit commencer par https://. Laisse vide pour ne rien afficher.'),

            SiteSetting::TYPE_EMAIL => EmailField::new('value', 'Email')
                ->setHelp('Adresse email valide (ex : contact@epicafe.fr).'),

            SiteSetting::TYPE_TEL => TelephoneField::new('value', 'Téléphone')
                ->setHelp('Format libre : "04 90 12 34 56" ou "+33 4 90 12 34 56".'),

            SiteSetting::TYPE_TEXTAREA => TextareaField::new('value', 'Valeur')
                ->setNumOfRows(5)
                ->setHelp('Tu peux faire plusieurs lignes (Entrée pour aller à la ligne).'),

            default => TextField::new('value', 'Valeur')
                ->setHelp('Texte court.'),
        };

        yield $field
            ->setRequired(false)
            ->onlyOnForms();
    }
}
