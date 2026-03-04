<?php

namespace App\Controller\Admin;

use App\Entity\MenuCategory;
use App\Entity\MenuItem;
use App\Entity\MenuItemVariant;
use App\Repository\MenuCategoryRepository;
use App\Repository\MenuItemRepository;
use App\Repository\MenuItemVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
final class MenuBuilderController extends AbstractController
{
    #[Route('/admin/menu/builder', name: 'admin_menu_builder')]
    public function index(
        MenuCategoryRepository $catRepo,
        MenuItemRepository $itemRepo
    ): Response {
        // Top categories (parent null)
        $roots = $catRepo->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()->getResult();

        // All items + category + variants (évite N+1)
        $items = $itemRepo->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')->addSelect('c')
            ->leftJoin('i.variants', 'v')->addSelect('v')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->addOrderBy('v.position', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->getQuery()->getResult();

        return $this->render('admin/menu/builder.html.twig', [
            'rootCategories' => $roots,
            'items' => $items,
            'csrf' => $this->getCsrfToken('menu_builder'),
        ]);
    }

    #[Route('/admin/menu/category/create', name: 'admin_menu_category_create', methods: ['POST'])]
    public function createCategory(
        Request $request,
        EntityManagerInterface $em,
        MenuCategoryRepository $catRepo,
        SluggerInterface $slugger,
    ): JsonResponse {
        $this->validateCsrf($request);

        $name = trim((string) $request->request->get('name', ''));
        $parentId = $request->request->get('parentId');

        if ($name === '') {
            return $this->json(['ok' => false, 'message' => 'Nom requis'], 400);
        }

        $cat = new MenuCategory();
        $cat->setName($name);
        $cat->setSlug(strtolower($slugger->slug($name)->toString()));

        if ($parentId) {
            $parent = $catRepo->find((int) $parentId);
            if ($parent) {
                $cat->setParent($parent);
            }
        }

        $em->persist($cat);
        $em->flush();

        return $this->json([
            'ok' => true,
            'id' => $cat->getId(),
            'name' => $cat->getName(),
            'parentId' => $cat->getParent()?->getId(),
        ]);
    }

    #[Route('/admin/menu/item/create', name: 'admin_menu_item_create', methods: ['POST'])]
    public function createItem(
        Request $request,
        EntityManagerInterface $em,
        MenuCategoryRepository $catRepo,
    ): JsonResponse {
        $this->validateCsrf($request);

        $categoryId = (int) $request->request->get('categoryId', 0);
        $name = trim((string) $request->request->get('name', ''));
        $unit = trim((string) $request->request->get('unit', ''));
        $price = trim((string) $request->request->get('price', ''));

        if ($categoryId <= 0 || $name === '') {
            return $this->json(['ok' => false, 'message' => 'Catégorie + nom requis'], 400);
        }

        $cat = $catRepo->find($categoryId);
        if (!$cat) {
            return $this->json(['ok' => false, 'message' => 'Catégorie introuvable'], 404);
        }

        $item = new MenuItem();
        $item->setCategory($cat);
        $item->setName($name);
        $item->setUnit($unit !== '' ? $unit : null);
        $item->setPrice($price !== '' ? $price : null);
        $item->setIsPublished(true);

        $em->persist($item);
        $em->flush();

        return $this->json(['ok' => true, 'id' => $item->getId()]);
    }

    #[Route('/admin/menu/item/update', name: 'admin_menu_item_update', methods: ['POST'])]
    public function updateItem(
        Request $request,
        EntityManagerInterface $em,
        MenuItemRepository $itemRepo,
        MenuCategoryRepository $catRepo,
    ): JsonResponse {
        $this->validateCsrf($request);

        $id = (int) $request->request->get('id', 0);
        $item = $itemRepo->find($id);
        if (!$item) return $this->json(['ok' => false, 'message' => 'Item introuvable'], 404);

        $categoryId = (int) $request->request->get('categoryId', 0);
        if ($categoryId > 0) {
            $cat = $catRepo->find($categoryId);
            if ($cat) $item->setCategory($cat);
        }

        $name = trim((string) $request->request->get('name', ''));
        $unit = trim((string) $request->request->get('unit', ''));
        $price = trim((string) $request->request->get('price', ''));
        $note = trim((string) $request->request->get('note', ''));
        $position = (int) $request->request->get('position', 0);
        $published = $request->request->get('isPublished', '1') === '1';

        if ($name !== '') $item->setName($name);
        $item->setUnit($unit !== '' ? $unit : null);
        $item->setPrice($price !== '' ? $price : null);
        $item->setNote($note !== '' ? $note : null);
        $item->setPosition($position);
        $item->setIsPublished($published);

        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/admin/menu/item/delete', name: 'admin_menu_item_delete', methods: ['POST'])]
    public function deleteItem(
        Request $request,
        EntityManagerInterface $em,
        MenuItemRepository $itemRepo,
    ): JsonResponse {
        $this->validateCsrf($request);

        $id = (int) $request->request->get('id', 0);
        $item = $itemRepo->find($id);
        if (!$item) return $this->json(['ok' => false], 404);

        $em->remove($item);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    // ===== Variants =====

    #[Route('/admin/menu/item/variant/create', name: 'admin_menu_variant_create', methods: ['POST'])]
    public function createVariant(
        Request $request,
        MenuItemRepository $itemRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->validateCsrf($request);

        $itemId = (int) $request->request->get('itemId', 0);
        $label = trim((string) $request->request->get('label', ''));
        $price = trim((string) $request->request->get('price', ''));

        if ($itemId <= 0 || $label === '') {
            return $this->json(['ok' => false, 'message' => 'Données invalides'], 400);
        }

        $item = $itemRepo->find($itemId);
        if (!$item) {
            return $this->json(['ok' => false, 'message' => 'Item introuvable'], 404);
        }

        $variant = new MenuItemVariant();
        $variant->setItem($item);
        $variant->setLabel($label);
        $variant->setPrice($price !== '' ? $price : null);
        $variant->setPosition(0);

        // si tu as le champ isPublished dans MenuItemVariant
        if (method_exists($variant, 'setIsPublished')) {
            $variant->setIsPublished(true);
        }

        $em->persist($variant);
        $em->flush();

        return $this->json([
            'ok' => true,
            'id' => $variant->getId(),
            'label' => $variant->getLabel(),
            'price' => $variant->getPrice()
        ]);
    }

    #[Route('/admin/menu/item/variant/update', name: 'admin_menu_variant_update', methods: ['POST'])]
    public function updateVariant(
        Request $request,
        MenuItemVariantRepository $variantRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->validateCsrf($request);

        $id = (int) $request->request->get('id', 0);
        $variant = $variantRepo->find($id);
        if (!$variant) {
            return $this->json(['ok' => false, 'message' => 'Variante introuvable'], 404);
        }

        $label = trim((string) $request->request->get('label', ''));
        $price = trim((string) $request->request->get('price', ''));
        $published = $request->request->get('isPublished', '1') === '1';

        if ($label !== '') $variant->setLabel($label);
        $variant->setPrice($price !== '' ? $price : null);

        if (method_exists($variant, 'setIsPublished')) {
            $variant->setIsPublished($published);
        }

        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/admin/menu/item/variant/delete', name: 'admin_menu_variant_delete', methods: ['POST'])]
    public function deleteVariant(
        Request $request,
        MenuItemVariantRepository $variantRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->validateCsrf($request);

        $id = (int) $request->request->get('id', 0);
        $variant = $variantRepo->find($id);
        if (!$variant) {
            return $this->json(['ok' => false, 'message' => 'Variante introuvable'], 404);
        }

        $em->remove($variant);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    // ===== Helpers =====

    private function validateCsrf(Request $request): void
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('menu_builder', $token)) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }
    }

    private function getCsrfToken(string $id): string
    {
        return $this->container->get('security.csrf.token_manager')->getToken($id)->getValue();
    }
}