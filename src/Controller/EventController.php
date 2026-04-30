<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events')]
final class EventController extends AbstractController
{
    #[Route('/evenements', name: 'event_index')]
    public function index(EventRepository $repo): Response
    {
        $upcoming = $repo->findUpcoming();
        $recurring = $repo->findBy(['isRecurring' => true, 'isPublished' => true]);
        $past = $repo->findPast();

        // Événement vedette = le prochain à venir (non permanent)
        $featured = $upcoming[0] ?? null;
        $upcomingRest = array_slice($upcoming, 1);

        // Regroupement par mois (clé = "YYYY-MM")
        $byMonth = [];
        foreach ($upcomingRest as $event) {
            $start = $event->getStartAt();
            if ($start === null) {
                continue;
            }
            $key = $start->format('Y-m');
            $byMonth[$key][] = $event;
        }
        ksort($byMonth);

        return $this->render('events/index.html.twig', [
            'featured'         => $featured,
            'upcomingByMonth'  => $byMonth,
            'recurringEvents'  => $recurring,
            'pastEvents'       => array_slice($past, 0, 3),
            'pastTotal'        => count($past),
            'upcomingTotal'    => count($upcoming),
        ]);
    }

    #[Route('/evenements/{slug}', name: 'event_show')]
    public function show(string $slug, EventRepository $repo): Response
    {
        $event = $repo->findOneBy(['slug' => $slug, 'isPublished' => true]);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

    $displayStart = $event->isRecurring()
        ? $event->getNextOccurrence($now, new \DateTimeZone('Europe/Paris'))
        : $event->getStartAt();

    return $this->render('events/show.html.twig', [
        'event' => $event,
        'displayStart' => $displayStart,
    ]);
    }
}