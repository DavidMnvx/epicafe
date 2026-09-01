<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Service\MathCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\GalleryPhotoRepository;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
public function index(
    EventRepository $repo,
    GalleryPhotoRepository $galleryRepo,
    MathCaptcha $captcha
): Response
{
    $latestPhotos = $galleryRepo->findPublished(null, 'new', 4);

    return $this->render('home/index.html.twig', [
        'upcomingEvents'  => $repo->findUpcoming(3),
        'recurringEvents' => $repo->findRecurring(1),
        'latestPhotos'    => $latestPhotos,
        'captchaQuestion' => $captcha->generate(),
    ]);
}
}