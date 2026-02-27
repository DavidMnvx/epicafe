<?php

namespace App\Controller\Admin;

use App\Entity\GalleryPhoto;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class GalleryUploadController extends AbstractController
{
    #[Route('/admin/gallery/upload', name: 'admin_gallery_upload')]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ?AdminContext $context = null, // ✅ deviens optionnel
    ): Response {

        if ($request->isMethod('POST')) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
            $files = $request->files->all('photos')['files'] ?? [];

            $takenAt = $request->request->get('takenAt') ?: null;
            $titlePrefix = trim((string) $request->request->get('titlePrefix', ''));

            $takenAtDt = $takenAt ? new \DateTimeImmutable($takenAt) : null;

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/gallery';

            foreach ($files as $file) {
                if (!$file) continue;

                $safeName = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $newName = $safeName . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'jpg');

                $file->move($uploadDir, $newName);

                $photo = new GalleryPhoto();
                $photo->setFileName($newName);
                $photo->setIsPublished(true);
                $photo->setTakenAt($takenAtDt);

                if ($titlePrefix !== '') {
                    $photo->setTitle($titlePrefix);
                }

                $em->persist($photo);
            }

            $em->flush();

            $this->addFlash('success', 'Photos importées ✅');

            return $this->redirectToRoute('admin_gallery_upload');
        }

        // ✅ on passe 'ea' seulement si on l’a
        return $this->render('admin/gallery_upload.html.twig', [
            'ea' => $context,
        ]);
    }
}