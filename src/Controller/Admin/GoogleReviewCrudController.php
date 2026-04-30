<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class GoogleReviewCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly GoogleReviewRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return GoogleReview::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avis Google')
            ->setEntityLabelInPlural('Avis Google')
            ->setPageTitle(Crud::PAGE_INDEX, 'Avis affichés sur le site')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un nouvel avis')
            ->setPageTitle(Crud::PAGE_EDIT, fn (GoogleReview $r) => 'Modifier l\'avis de ' . $r->getAuthor())
            ->setSearchFields(['author', 'text'])
            ->setDefaultSort(['position' => 'ASC', 'createdAt' => 'DESC'])
            ->setPaginatorPageSize(24)
            ->overrideTemplate('crud/index', 'admin/google_review/index.html.twig')
            ->showEntityActionsInlined()
            ->setHelp(
                Crud::PAGE_INDEX,
                'Liste des avis affichés dans le carousel en bas de chaque page. '
                . 'Copie-colle ici les avis publiés sur Google pour les mettre en valeur sur le site. '
                . 'Tu peux les réordonner avec les flèches ⬆⬇, les masquer avec le switch "Publié", ou les supprimer.'
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        $moveUp = Action::new('moveUp', 'Monter', 'fa fa-arrow-up')
            ->linkToCrudAction('moveUp')
            ->displayIf(fn (GoogleReview $r) => $this->repository->findPreviousByPosition($r) !== null);

        $moveDown = Action::new('moveDown', 'Descendre', 'fa fa-arrow-down')
            ->linkToCrudAction('moveDown')
            ->displayIf(fn (GoogleReview $r) => $this->repository->findNextByPosition($r) !== null);

        $actions = $actions
            ->add(Crud::PAGE_INDEX, $moveUp)
            ->add(Crud::PAGE_INDEX, $moveDown)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvel avis'));

        return $this->addPublishableBatchActions($actions);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('isPublished', 'Publié'));
    }

    public function moveUp(): Response
    {
        return $this->swapWithNeighbour('previous');
    }

    public function moveDown(): Response
    {
        return $this->swapWithNeighbour('next');
    }

    private function swapWithNeighbour(string $direction): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $id = $request?->query->get('entityId');

        if ($id !== null) {
            /** @var GoogleReview|null $current */
            $current = $this->em->getRepository(GoogleReview::class)->find($id);

            if ($current instanceof GoogleReview) {
                $neighbour = $direction === 'previous'
                    ? $this->repository->findPreviousByPosition($current)
                    : $this->repository->findNextByPosition($current);

                if ($neighbour !== null) {
                    $tmp = $current->getPosition();
                    $current->setPosition($neighbour->getPosition());
                    $neighbour->setPosition($tmp);
                    $this->em->flush();
                    $this->addFlash('success', sprintf(
                        'Avis de "%s" %s.',
                        $current->getAuthor(),
                        $direction === 'previous' ? 'remonté' : 'descendu'
                    ));
                }
            }
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Index =====
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('author', 'Auteur')->onlyOnIndex();

        yield ChoiceField::new('rating', 'Note')
            ->setChoices([
                '★★★★★ (5)' => 5,
                '★★★★☆ (4)' => 4,
                '★★★☆☆ (3)' => 3,
                '★★☆☆☆ (2)' => 2,
                '★☆☆☆☆ (1)' => 1,
            ])
            ->onlyOnIndex();

        yield TextField::new('text', 'Avis')
            ->formatValue(function ($value) {
                $str = (string) $value;
                return mb_strlen($str) > 80 ? mb_substr($str, 0, 80) . '…' : $str;
            })
            ->onlyOnIndex();

        yield DateField::new('reviewDate', 'Date avis')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Formulaire =====
        yield TextField::new('author', 'Nom de l\'auteur')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Tel qu\'il apparaît sur Google. Ex : "Sophie M.", "Jean Dupont".')
            ->onlyOnForms();

        yield ChoiceField::new('rating', 'Note (sur 5)')
            ->setChoices([
                '★★★★★ — 5 étoiles' => 5,
                '★★★★☆ — 4 étoiles' => 4,
                '★★★☆☆ — 3 étoiles' => 3,
                '★★☆☆☆ — 2 étoiles' => 2,
                '★☆☆☆☆ — 1 étoile'  => 1,
            ])
            ->setColumns('col-md-3')
            ->setRequired(true)
            ->onlyOnForms();

        yield DateField::new('reviewDate', 'Date de l\'avis')
            ->setColumns('col-md-3')
            ->setRequired(false)
            ->setHelp('Optionnel — affichée sous l\'avis. Ex : "Il y a 2 mois".')
            ->onlyOnForms();

        yield TextareaField::new('text', 'Texte de l\'avis')
            ->setNumOfRows(5)
            ->setRequired(true)
            ->setColumns('col-md-12')
            ->setHelp('Copie-colle directement le texte depuis Google. Tu peux corriger des fautes mineures si besoin.')
            ->onlyOnForms();

        yield IntegerField::new('position', 'Ordre d\'affichage')
            ->setColumns('col-md-3')
            ->setHelp('Plus petit = affiché en premier dans le carousel.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié sur le site')
            ->renderAsSwitch(false)
            ->setColumns('col-md-3')
            ->setHelp('Décoche pour cacher cet avis sans le supprimer.')
            ->onlyOnForms();
    }
}
