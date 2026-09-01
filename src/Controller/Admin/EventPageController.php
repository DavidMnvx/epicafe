<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\AdminContextEnsurer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Les événements sur un seul écran, rangés comme la galerie photo.
 *
 * En tête, les rendez-vous hebdomadaires (marché du mardi, jam du jeudi…),
 * toujours visibles. En dessous, un accordéon par mois — les événements datés
 * se classent tout seuls par leur date, il n'y a rien à ordonner à la main.
 */
#[IsGranted('ROLE_ADMIN')]
final class EventPageController extends AbstractController
{
    private const DAYS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
    ];

    public function __construct(private readonly AdminUrlGenerator $adminUrl)
    {
    }

    // Voir GalleryPageController : sans ce défaut, le layout EasyAdmin n'a
    // pas son contexte et la page casse en accès direct.
    #[Route('/admin/evenements', name: 'admin_events', defaults: [
        EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class,
    ])]
    public function index(Request $request, EventRepository $repository, AdminContextEnsurer $context): Response
    {
        $context->ensure($request);

        /** @var Event[] $events */
        $events = $repository->findAll();

        $recurring = [];
        $dated = [];
        $undated = [];

        foreach ($events as $event) {
            if ($event->isRecurring()) {
                $recurring[] = $event;
            } elseif ($event->getStartAt() !== null) {
                $dated[] = $event;
            } else {
                $undated[] = $event;
            }
        }

        // Hebdomadaires : dans l'ordre de la semaine, puis par heure.
        usort($recurring, static fn (Event $a, Event $b) =>
            [$a->getRecurringDayOfWeek() ?? 8, $a->getRecurringTime()?->format('H:i') ?? '99']
            <=> [$b->getRecurringDayOfWeek() ?? 8, $b->getRecurringTime()?->format('H:i') ?? '99']);

        // Datés : les plus récents d'abord, groupés par mois.
        usort($dated, static fn (Event $a, Event $b) => $b->getStartAt() <=> $a->getStartAt());

        $months = [];
        foreach ($dated as $event) {
            $key = $event->getStartAt()->format('Y-m');

            $months[$key] ??= [
                'label' => $this->monthLabel($event->getStartAt()),
                'events' => [],
            ];

            $months[$key]['events'][] = $event;
        }

        return $this->render('admin/events/index.html.twig', [
            'recurring' => $recurring,
            'months' => $months,
            'undated' => $undated,
            'days' => self::DAYS,
            'newUrl' => $this->crudUrl('new'),
        ]);
    }

    #[Route('/admin/evenements/update', name: 'admin_events_update', methods: ['POST'])]
    public function update(
        Request $request,
        EntityManagerInterface $em,
        EventRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $event = $repository->find((int) $request->request->get('id', 0));

        if (!$event) {
            return $this->json(['ok' => false, 'message' => 'Événement introuvable.'], 404);
        }

        if ($request->request->has('isPublished')) {
            $event->setIsPublished($request->request->get('isPublished') === '1');
        }

        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/admin/evenements/delete', name: 'admin_events_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        EventRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $event = $repository->find((int) $request->request->get('id', 0));

        if (!$event) {
            return $this->json(['ok' => false, 'message' => 'Événement introuvable.'], 404);
        }

        $em->remove($event);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    /** URL EasyAdmin du formulaire (création, ou édition avec $entityId). */
    private function crudUrl(string $action, ?int $entityId = null): string
    {
        $url = $this->adminUrl
            ->setController(EventCrudController::class)
            ->setAction($action);

        if ($entityId !== null) {
            $url->setEntityId($entityId);
        }

        return $url->generateUrl();
    }

    private function monthLabel(\DateTimeImmutable $date): string
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            null,
            'LLLL yyyy'
        );

        return ucfirst((string) $formatter->format($date));
    }

    private function csrfError(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid('events', (string) $request->request->get('_token'))) {
            return null;
        }

        return $this->json(['ok' => false, 'message' => 'Session expirée, recharge la page.'], 403);
    }
}
