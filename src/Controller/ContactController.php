<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('contact/index.html.twig', [
            'pageTitle' => 'Contact & Accès',
            'pageSubtitle' => 'Nous trouver au cœur du Barroux',
            'contact' => [
                'address' => '56 Chemin Neuf, 84330 Le Barroux',
                'phone' => '04 90 12 34 56',
                'email' => 'contact@epicafe.fr',
                'mapsUrl' => 'https://www.google.com/maps?q=56+Chemin+Neuf+84330+Le+Barroux',
                'mapsEmbed' => 'https://www.google.com/maps?q=56%20Chemin%20Neuf%2C%2084330%20Le%20Barroux&output=embed',
            ],
            'hours' => [
                'Lundi - Mardi - Jeudi' => '7h30 – 18h30',
                'Vendredi - Samedi' => '7h30 – 21h30',
                'Dimanche' => '8h00 – 12h00',
                'Mercredi' => 'Fermé',
            ],
        ]);
    }
}