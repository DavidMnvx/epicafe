<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ShopCategory;
use App\Repository\ShopCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ShopController extends AbstractController
{
    #[Route('/boutique', name: 'shop_index')]
    public function index(ShopCategoryRepository $categories): Response
    {
        return $this->render('shop/index.html.twig', [
            'categories' => $categories->findAllPublishedOrdered(),
        ]);
    }

    /**
     * Aperçu admin d'un article unique de la boutique, même non publié.
     * Accessible uniquement aux admins (réservé à la prévisualisation depuis le CRUD).
     */
    #[Route('/admin/preview/boutique/{slug}', name: 'shop_preview')]
    #[IsGranted('ROLE_ADMIN')]
    public function preview(ShopCategory $category): Response
    {
        return $this->render('shop/preview.html.twig', [
            'category' => $category,
        ]);
    }
}
