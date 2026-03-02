<?php

namespace App\Controller;

use App\Repository\PartnerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PartnerController extends AbstractController
{
    #[Route('/partenaires', name: 'partner_index')]
    public function index(PartnerRepository $repo): Response
    {
        return $this->render('partners/index.html.twig', [
            'partners' => $repo->findPublishedOrdered(),
        ]);
    }
}