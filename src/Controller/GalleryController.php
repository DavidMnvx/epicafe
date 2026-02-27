<?php

namespace App\Controller;

use App\Repository\GalleryPhotoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GalleryController extends AbstractController
{
    #[Route('/galerie', name: 'gallery_index')]
    public function index(Request $request, GalleryPhotoRepository $repo): Response
    {
        $yearRaw = trim((string) $request->query->get('year', ''));
        $year = ctype_digit($yearRaw) ? (int) $yearRaw : null;
        $sort = $request->query->get('sort', 'new');
        if (!in_array($sort, ['new', 'old'], true)) {
            $sort = 'new';
        }

        $years = $repo->findAvailableYears();
        $photos = $repo->findPublished($year ?: null, $sort);

        return $this->render('gallery/index.html.twig', [
            'photos' => $photos,
            'years' => $years,
            'selectedYear' => $year ?: null,
            'selectedSort' => $sort,
        ]);
    }
}