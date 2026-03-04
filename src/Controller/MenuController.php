<?php

namespace App\Controller;

use App\Repository\MenuCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/menu', name: 'menu')]
public function index(MenuCategoryRepository $catRepo): Response
{
    $rootCategories = $catRepo->createQueryBuilder('c')
        ->andWhere('c.parent IS NULL')
        ->orderBy('c.position', 'ASC')
        ->getQuery()
        ->getResult();

    return $this->render('menu/index.html.twig', [
        'rootCategories' => $rootCategories,
    ]);
}
    }
