<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GalleryPhoto;
use App\Repository\GalleryPhotoRepository;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * La galerie photo sur un seul écran.
 *
 * Avant : deux entrées de menu séparées — « Ajouter des photos » d'un côté,
 * « Toutes les photos » de l'autre — et un tableau de lignes de texte pour un
 * contenu purement visuel. Ici : les photos en tuiles, regroupées par mois,
 * avec l'ajout au même endroit que la consultation.
 */
#[IsGranted('ROLE_ADMIN')]
final class GalleryPageController extends AbstractController
{
    private const UPLOAD_SUBDIR = '/public/uploads/gallery';

    // Le layout EasyAdmin a besoin de son contexte, que le bundle ne construit
    // que pour ses propres routes. Sans ce défaut, la page rend une erreur dès
    // qu'on l'ouvre directement (favori, rechargement, lien collé) au lieu de
    // passer par le menu.
    #[Route('/admin/photos', name: 'admin_gallery', defaults: [
        EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class,
    ])]
    public function index(GalleryPhotoRepository $repository): Response
    {
        $photos = $repository->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/gallery/index.html.twig', [
            'months' => $this->groupByMonth($photos),
            'total' => \count($photos),
        ]);
    }

    /**
     * Reçoit un ou plusieurs fichiers et les normalise.
     *
     * Chaque fichier est traité indépendamment : un PDF illisible au milieu du
     * lot ne doit pas faire échouer les photos qui l'accompagnent.
     */
    #[Route('/admin/photos/upload', name: 'admin_gallery_upload_files', methods: ['POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        GalleryPhotoRepository $repository,
        SluggerInterface $slugger,
        ImageProcessor $processor,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        /** @var UploadedFile[] $files */
        $files = $request->files->all('files');

        if ($files === []) {
            return $this->json(['ok' => false, 'message' => 'Aucun fichier reçu.'], 400);
        }

        $directory = $this->getParameter('kernel.project_dir') . self::UPLOAD_SUBDIR;
        $takenAt = $this->parseDate($request->request->get('takenAt'));

        // Intitulé saisi dans la modale de validation ; à défaut, le nom du
        // fichier fera l'affaire.
        $customTitle = trim((string) $request->request->get('title', ''));

        // Les nouvelles photos passent devant : c'est le plus souvent ce qu'on
        // veut voir en premier après un import.
        $offset = 1 + (int) ($repository->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->getQuery()
            ->getSingleScalarResult() ?? -1);

        $added = [];
        $failed = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                $failed[] = ['name' => $file?->getClientOriginalName() ?? 'fichier', 'reason' => 'Transfert incomplet.'];
                continue;
            }

            $originalName = $file->getClientOriginalName();

            try {
                $storedName = $this->store($file, $directory, $slugger, $processor);
            } catch (\RuntimeException $exception) {
                $failed[] = ['name' => $originalName, 'reason' => $exception->getMessage()];
                continue;
            }

            $photo = new GalleryPhoto();
            $photo->setFileName($storedName);
            $photo->setTitle($customTitle !== '' ? $customTitle : pathinfo($originalName, PATHINFO_FILENAME));
            $photo->setTakenAt($takenAt);
            $photo->setIsPublished(true);
            $photo->setPosition($offset++);

            $em->persist($photo);
            $added[] = $photo;
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'added' => array_map(fn (GalleryPhoto $p) => [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'fileName' => $p->getFileName(),
                'month' => $this->monthKey($p),
                'monthLabel' => $this->monthLabel($p->getSortDate()),
            ], $added),
            'failed' => $failed,
        ]);
    }

    #[Route('/admin/photos/reorder', name: 'admin_gallery_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        EntityManagerInterface $em,
        GalleryPhotoRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $ids = array_map('intval', $request->request->all('ids'));

        if ($ids === []) {
            return $this->json(['ok' => true, 'updated' => 0]);
        }

        $byId = [];
        foreach ($repository->findBy(['id' => $ids]) as $photo) {
            $byId[$photo->getId()] = $photo;
        }

        $position = 0;
        foreach ($ids as $id) {
            $byId[$id]?->setPosition($position++);
        }

        $em->flush();

        return $this->json(['ok' => true, 'updated' => $position]);
    }

    #[Route('/admin/photos/update', name: 'admin_gallery_update', methods: ['POST'])]
    public function update(
        Request $request,
        EntityManagerInterface $em,
        GalleryPhotoRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $photo = $repository->find((int) $request->request->get('id', 0));

        if (!$photo) {
            return $this->json(['ok' => false, 'message' => 'Photo introuvable.'], 404);
        }

        if ($request->request->has('title')) {
            $photo->setTitle(trim((string) $request->request->get('title')));
        }

        if ($request->request->has('takenAt')) {
            $photo->setTakenAt($this->parseDate($request->request->get('takenAt')));
        }

        if ($request->request->has('isPublished')) {
            $photo->setIsPublished($request->request->get('isPublished') === '1');
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'month' => $this->monthKey($photo),
            'monthLabel' => $this->monthLabel($photo->getSortDate()),
        ]);
    }

    #[Route('/admin/photos/delete', name: 'admin_gallery_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        GalleryPhotoRepository $repository,
    ): JsonResponse {
        if ($error = $this->csrfError($request)) {
            return $error;
        }

        $photo = $repository->find((int) $request->request->get('id', 0));

        if (!$photo) {
            return $this->json(['ok' => false, 'message' => 'Photo introuvable.'], 404);
        }

        // Le fichier est supprimé par le hook PostRemove de l'entité.
        $em->remove($photo);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    /**
     * Déplace le fichier puis le fait normaliser (redimensionnement, JPEG,
     * conversion PDF). Retourne le nom de fichier finalement stocké.
     */
    private function store(
        UploadedFile $file,
        string $directory,
        SluggerInterface $slugger,
        ImageProcessor $processor,
    ): string {
        $safeName = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower();
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'jpg'));
        $temporaryName = sprintf('%s-%s.%s', $safeName ?: 'photo', uniqid(), $extension);

        $file->move($directory, $temporaryName);

        $finalPath = $processor->process($directory . '/' . $temporaryName);

        return basename($finalPath);
    }

    /**
     * @param GalleryPhoto[] $photos
     *
     * @return array<string, array{label: string, photos: GalleryPhoto[]}>
     */
    private function groupByMonth(array $photos): array
    {
        $months = [];

        foreach ($photos as $photo) {
            $key = $this->monthKey($photo);

            $months[$key] ??= [
                'label' => $this->monthLabel($photo->getSortDate()),
                'photos' => [],
            ];

            $months[$key]['photos'][] = $photo;
        }

        // Mois les plus récents en premier.
        krsort($months);

        return $months;
    }

    private function monthKey(GalleryPhoto $photo): string
    {
        return $photo->getSortDate()->format('Y-m');
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

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function csrfError(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid('gallery', (string) $request->request->get('_token'))) {
            return null;
        }

        return $this->json(['ok' => false, 'message' => 'Session expirée, recharge la page.'], 403);
    }
}
