<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ShopCategory;
use App\Repository\ShopCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

final class ShopCategoryCrudController extends AbstractCrudController
{
    use PublishableBatchActionsTrait;

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ShopCategoryRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ShopCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Article boutique')
            ->setEntityLabelInPlural('Articles boutique')
            ->setPageTitle(Crud::PAGE_INDEX, 'Articles de la boutique')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvel article boutique')
            ->setPageTitle(Crud::PAGE_EDIT, fn (ShopCategory $c) => 'Modifier : ' . $c->getName())
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name', 'description'])
            ->setPaginatorPageSize(24)
            ->overrideTemplate('crud/index', 'admin/shop_category/index.html.twig')
            ->overrideTemplate('crud/edit', 'admin/shop_category/edit.html.twig')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Chaque carte est un article de la page Boutique. Tu peux ajouter, modifier, supprimer, '
                . 'réorganiser (flèches ⬆⬇) et prévisualiser sans publier (icône œil). '
                . 'Le bouton "Aperçu" ouvre l\'article dans un nouvel onglet, même s\'il est en brouillon.'
            )
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $moveUp = Action::new('moveUp', 'Monter', 'fa fa-arrow-up')
            ->linkToCrudAction('moveUp')
            ->displayIf(fn (ShopCategory $c) => $this->repository->findPreviousByPosition($c) !== null);

        $moveDown = Action::new('moveDown', 'Descendre', 'fa fa-arrow-down')
            ->linkToCrudAction('moveDown')
            ->displayIf(fn (ShopCategory $c) => $this->repository->findNextByPosition($c) !== null);

        $previewArticle = Action::new('previewArticle', 'Aperçu', 'fa fa-eye')
            ->linkToUrl(fn (ShopCategory $c) => $this->urlGenerator->generate('shop_preview', ['slug' => $c->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener']);

        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $previewArticle)
            ->add(Crud::PAGE_INDEX, $moveUp)
            ->add(Crud::PAGE_INDEX, $moveDown)
            ->add(Crud::PAGE_EDIT, $previewArticle)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Nouvel article'));

        return $this->addPublishableBatchActions($actions);
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
        /** @var ShopCategory|null $current */
        $current = $this->getContext()?->getEntity()?->getInstance();

        if (!$current instanceof ShopCategory) {
            return $this->redirectToIndex();
        }

        $neighbour = $direction === 'previous'
            ? $this->repository->findPreviousByPosition($current)
            : $this->repository->findNextByPosition($current);

        if ($neighbour !== null) {
            $tmp = $current->getPosition();
            $current->setPosition($neighbour->getPosition());
            $neighbour->setPosition($tmp);
            $this->em->flush();
            $this->addFlash('success', sprintf('"%s" %s.', $current->getName(), $direction === 'previous' ? 'remontée' : 'descendue'));
        }

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setAction(Action::INDEX)->generateUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('isPublished', 'Publié'));
    }

    public function configureFields(string $pageName): iterable
    {
        // ===== Colonnes d'index =====
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('imageFileName', 'Image')
            ->setBasePath('uploads/shop')
            ->onlyOnIndex();
        yield TextField::new('name', 'Nom')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Publié')->onlyOnIndex();

        // ===== Onglet Infos =====
        yield FormField::addTab('Infos')
            ->setIcon('fa fa-circle-info')
            ->setHelp(
                'Chaque catégorie est une "carte" affichée sur la page Boutique du site. '
                . 'Elle représente une famille de produits (miel, bière locale, œufs, huile d’olive…). '
                . 'Tu peux en ajouter, en retirer et réorganiser l’ordre autant que tu veux.'
            );

        yield TextField::new('name', 'Nom')
            ->setRequired(true)
            ->setColumns('col-md-6')
            ->setHelp('Titre de la catégorie affiché sur la carte (ex : "Miel du Ventoux", "Bières locales").')
            ->onlyOnForms();

        yield SlugField::new('slug', 'Slug (URL)')
            ->setTargetFieldName('name')
            ->setRequired(false)
            ->setColumns('col-md-6')
            ->setHelp('Généré automatiquement à partir du nom. Laisse vide sauf cas particulier.')
            ->onlyOnForms();

        yield TextField::new('kicker', 'Accroche (petit texte au-dessus du titre)')
            ->setColumns('col-md-6')
            ->setHelp('Optionnel. Ex : "Produits locaux", "Sélection du chef". Laisse vide si tu n’en as pas besoin.')
            ->onlyOnForms();

        yield TextField::new('icon', 'Emoji (fallback si pas d’image)')
            ->setColumns('col-md-2')
            ->setHelp('Ex : 🍯, 🍷, 🥚. Affiché uniquement si aucune image n’est uploadée ci-dessous.')
            ->onlyOnForms();

        yield IntegerField::new('position', 'Ordre')
            ->setColumns('col-md-2')
            ->setHelp('Plus petit = affiché en premier.')
            ->onlyOnForms();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(false)
            ->setColumns('col-md-2')
            ->setHelp('Décoche pour cacher cette catégorie de la page Boutique.')
            ->onlyOnForms();

        yield TextareaField::new('description', 'Description')
            ->setColumns('col-md-12')
            ->setNumOfRows(8)
            ->setHelp(
                'Texte d’article (plusieurs paragraphes possibles). '
                . '⚠ Pour faire un nouveau paragraphe : laisse une ligne vide entre deux blocs de texte. '
                . 'Tu peux écrire 2-4 paragraphes pour bien décrire les producteurs, les saisons, les spécificités…'
            )
            ->onlyOnForms();

        yield TextareaField::new('highlights', 'Produits / points mis en avant')
            ->setColumns('col-md-12')
            ->setNumOfRows(8)
            ->setHelp(
                '⚠ Une ligne = un point ou produit, affiché en liste à puces sous la description. '
                . 'Exemple : '
                . '"Miel de lavande du Ventoux / Miel de garrigue / Huile d’olive bio / Confitures maison". '
                . 'Laisse vide si tu ne veux pas afficher de liste.'
            )
            ->onlyOnForms();

        // ===== Onglet Image =====
        yield FormField::addTab('Image')
            ->setIcon('fa fa-image')
            ->setHelp(
                'Deux façons de définir l’image de l’article : '
                . '1) Uploader un fichier depuis ton ordinateur (recommandé, plus fiable) — '
                . '2) Coller un lien (URL) vers une image en ligne (Unsplash, Pexels, ton drive, etc.). '
                . 'Si tu remplis les deux, le fichier uploadé est utilisé en priorité. '
                . 'Taille recommandée : 800×600 px (format 4:3), JPG ou WebP, poids < 300 Ko.'
            );

        yield ImageField::new('imageFileName', 'Image uploadée (recommandé)')
            ->setUploadDir('public/uploads/shop')
            ->setBasePath('uploads/shop')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setRequired(false)
            ->setColumns('col-md-8')
            ->setHelp('Glisse une photo ou sélectionne-la depuis ton ordinateur. 800×600 px recommandé, max 1 Mo. ⭐ Prioritaire sur l’URL si les deux sont renseignés.')
            ->onlyOnForms();

        yield UrlField::new('imageUrl', 'Image via URL (alternative)')
            ->setRequired(false)
            ->setColumns('col-md-12')
            ->setHelp(
                'Lien direct vers une image en ligne. Doit commencer par https:// et finir par .jpg, .png, .webp ou .avif. '
                . 'Exemple : https://images.unsplash.com/photo-XXXX. '
                . 'Utilisé seulement si aucun fichier n’est uploadé ci-dessus.'
            )
            ->onlyOnForms();

        // ===== Onglet Citation =====
        yield FormField::addTab('Citation (optionnel)')
            ->setIcon('fa fa-quote-right')
            ->setHelp(
                'Une citation peut être affichée juste après cet article sur la page Boutique. '
                . 'Elle apparaît en grand, en italique, séparée du fil par deux symboles ❝ et ❞. '
                . 'Tu peux laisser vide si tu ne veux pas de citation pour cet article. '
                . 'Idéal pour 1 ou 2 articles maximum, pas tous — sinon ça perd de l\'effet.'
            );

        yield TextareaField::new('pullQuote', 'Texte de la citation')
            ->setColumns('col-md-12')
            ->setNumOfRows(3)
            ->setRequired(false)
            ->setHelp(
                'Phrase courte et marquante (1 à 2 lignes). '
                . 'Inutile de mettre des guillemets : ils sont ajoutés automatiquement. '
                . 'Exemple : "On ne veut pas tout faire — on veut bien faire les choses qu\'on aime."'
            )
            ->onlyOnForms();

        yield TextField::new('pullQuoteAuthor', 'Auteur / source')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Optionnel. Ex : "Frédéric, gérant", "Une cliente fidèle". Si vide, on affiche "L\'esprit de l\'Épi-Café".')
            ->onlyOnForms();
    }
}
