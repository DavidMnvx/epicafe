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
    #[Route('', name: 'event_index')]
    public function index(EventRepository $repo): Response
    {
        return $this->render('events/index.html.twig', [
            'upcomingEvents' => $repo->findUpcoming(),
            'pastEvents'     => $repo->findPast(),
            'recurringEvents'=> $repo->findBy([
                'isRecurring' => true,
                'isPublished' => true,
            ]),
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