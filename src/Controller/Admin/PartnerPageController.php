<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Repository\PartnerRepository;
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
 * Les partenaires en tuiles, groupés par type d'affichage.
 *
 * Avant : un tableau EasyAdmin où l'ordre se réglait en tapant un numéro —
 * jamais utilisé, les 9 partenaires étaient tous en position 0. Ici : mêmes
 * gestes que la galerie et le builder de carte — glisser pour ordonner, et
 * glisser d'une section à l'autre pour changer le type d'affichage.
 */
#[IsGranted('ROLE_ADMIN')]
final class PartnerPageController extends AbstractController
{
    /** Sections dans l'ordre d'importance sur le site public. */
    private const SECTIONS = [
        Partner::TYPE_PREMIUM => [
            'label' => 'Premium',
            'help' => 'Grand bloc avec photos et points forts, en haut de la page.',
        ],
        Partner::TYPE_PARTNER => [
            'label' => 'Partenaires',
            'help' => 'Carte classique avec photo et description.',
        ],
        Partner::TYPE_SECONDARY => [
            'label' => 'Secondaires',
            'help' => 'Simple vignette dans la liste complémentaire, en bas de page.',
        ],
    ];

    public function __construct(private readonly AdminUrlGenerator $adminUrl)
    {
    }

    // Voir GalleryPageController : sans ce défaut, le layout EasyAdmin n'a
    // pas son contexte et la page casse en accès direct.
    #[Route('/admin/partenaires', name: 'admin_partners', defaults: [
        EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class,
    ])]
    public function index(Request $request, PartnerRepository $repository, AdminContextEnsurer $context): Response
    {
        $context->ensure($request);

        $partners = $repository->findBy([], ['position' => 'ASC', 'id' => 'ASC']);

        $sections = [];
        foreach (self::SECTIONS as $type => $meta) {
            $sections[$type] = $meta + [
                'partners' => array_values(array_filter(
                    $partners,
                    static fn (Partner $p) => $p->getType() === $type
                )),
            ];
        }

        return $this->render('admin/partners/index.html.twig', [
            'sections' => $sections,
            'newUrl' => $this->crudUrl('new'),
        ]);
    }

    /**
     * Réordonne une section ; une tuile venue d'une autre section change de
     * type au passage — même contrat que le builder de carte : la liste
     * complète des ids reçue fait foi, les positions sont réécrites de 0 à n.
     */
    #[Route('/admin/partenaires/reorder', name: 'admin_partners_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        EntityManagerInterface $em,
        PartnerRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $type = (string) $request->request->get('type', '');

        if (!\array_key_exists($type, self::SECTIONS)) {
            return $this->json(['ok' => false, 'message' => 'Type inconnu.'], 400);
        }

        $ids = array_map('intval', $request->request->all('ids'));

        if ($ids === []) {
            return $this->json(['ok' => true, 'updated' => 0]);
        }

        $byId = [];
        foreach ($repository->findBy(['id' => $ids]) as $partner) {
            $byId[$partner->getId()] = $partner;
        }

        $position = 0;
        foreach ($ids as $id) {
            $partner = $byId[$id] ?? null;

            if ($partner === null) {
                continue;
            }

            $partner->setType($type);
            $partner->setPosition($position++);
        }

        $em->flush();

        return $this->json(['ok' => true, 'updated' => $position]);
    }

    #[Route('/admin/partenaires/update', name: 'admin_partners_update', methods: ['POST'])]
    public function update(
        Request $request,
        EntityManagerInterface $em,
        PartnerRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $partner = $repository->find((int) $request->request->get('id', 0));

        if (!$partner) {
            return $this->json(['ok' => false, 'message' => 'Partenaire introuvable.'], 404);
        }

        if ($request->request->has('isPublished')) {
            $partner->setIsPublished($request->request->get('isPublished') === '1');
        }

        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/admin/partenaires/delete', name: 'admin_partners_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        PartnerRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $partner = $repository->find((int) $request->request->get('id', 0));

        if (!$partner) {
            return $this->json(['ok' => false, 'message' => 'Partenaire introuvable.'], 404);
        }

        $em->remove($partner);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    /** URL EasyAdmin du formulaire (création, ou édition avec $entityId). */
    private function crudUrl(string $action, ?int $entityId = null): string
    {
        $url = $this->adminUrl
            ->setController(PartnerCrudController::class)
            ->setAction($action);

        if ($entityId !== null) {
            $url->setEntityId($entityId);
        }

        return $url->generateUrl();
    }

    private function csrfError(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid('partners', (string) $request->request->get('_token'))) {
            return null;
        }

        return $this->json(['ok' => false, 'message' => 'Session expirée, recharge la page.'], 403);
    }
}
