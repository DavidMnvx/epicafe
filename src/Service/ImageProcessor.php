<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Normalise toute image déposée dans le back-office.
 *
 * Sans ce passage, une photo sortie d'un téléphone arrive à 9 Mo en 4032 px de
 * large et part telle quelle sur le site : c'est ce qui a fait ramer l'accueil
 * en mai. Et un PDF déposé à la place d'une image ne s'affiche tout simplement
 * pas — cas rencontré avec la carte envoyée par la gérante.
 *
 * En sortie, toujours un JPEG borné en largeur et en qualité.
 */
final class ImageProcessor
{
    /** Au-delà, on ne gagne rien à l'écran mais on paie le poids. */
    public const MAX_WIDTH = 1920;

    /** Compromis poids/qualité classique pour de la photo. */
    private const JPEG_QUALITY = 82;

    /** Résolution de rendu d'un PDF : 150 dpi donne ~1240 px pour un A4. */
    private const PDF_DPI = 150;

    /** En dessous, un JPEG aux bonnes dimensions n'a rien à gagner à être recompressé. */
    private const ACCEPTABLE_WEIGHT = 600 * 1024;

    private const PDF_EXTENSION = 'pdf';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Traite le fichier à l'emplacement donné, sur place.
     *
     * @param string $path chemin absolu du fichier déjà déposé sur le disque
     *
     * @return string chemin du fichier final — différent de $path si l'extension
     *                a changé (un .pdf devient un .jpg)
     *
     * @throws \RuntimeException si le fichier n'est pas exploitable
     */
    public function process(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Fichier introuvable : %s', $path));
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === self::PDF_EXTENSION) {
            $path = $this->convertPdfToJpeg($path);
        }

        return $this->normalize($path);
    }

    /**
     * Extrait la première page d'un PDF en JPEG, puis supprime le PDF.
     *
     * On ne garde que la première page : une carte ou une affiche tient sur une
     * page, et un document de 40 pages produirait 40 fichiers orphelins.
     */
    private function convertPdfToJpeg(string $pdfPath): string
    {
        $directory = \dirname($pdfPath);
        $basename = pathinfo($pdfPath, PATHINFO_FILENAME);

        // pdftoppm ajoute lui-même l'extension au préfixe qu'on lui donne.
        $prefix = $directory . '/' . $basename;

        $process = new Process([
            'pdftoppm',
            '-jpeg',
            '-r', (string) self::PDF_DPI,
            '-f', '1',
            '-l', '1',
            '-singlefile',
            $pdfPath,
            $prefix,
        ]);

        $process->setTimeout(60);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->logger->error('Conversion PDF échouée', [
                'file' => $pdfPath,
                'error' => $process->getErrorOutput(),
            ]);

            @unlink($pdfPath);

            throw new \RuntimeException(
                'Ce PDF n’a pas pu être converti en image. S’il est protégé par un mot de passe, '
                . 'enregistre-le en JPG avant de l’envoyer.',
                0,
                $exception
            );
        }

        $jpegPath = $prefix . '.jpg';

        if (!is_file($jpegPath)) {
            @unlink($pdfPath);

            throw new \RuntimeException('Ce PDF semble vide : aucune page à convertir.');
        }

        @unlink($pdfPath);

        return $jpegPath;
    }

    /**
     * Redimensionne si nécessaire et réencode en JPEG.
     *
     * Passe par GD, déjà présent dans l'image : pas de dépendance supplémentaire.
     */
    private function normalize(string $path): string
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new \RuntimeException(
                'Ce fichier n’est pas une image exploitable. Formats acceptés : JPG, PNG, WebP, PDF.'
            );
        }

        [$width, $height] = $info;

        // Une image déjà conforme est laissée telle quelle : chaque réencodage
        // JPEG dégrade un peu plus, et ce service peut être rappelé à chaque
        // enregistrement de la fiche.
        if ($this->isAlreadyClean($path, $info[2], $width)) {
            return $path;
        }

        $source = $this->createImage($path, $info[2]);

        if ($source === null) {
            throw new \RuntimeException('Format d’image non pris en charge.');
        }

        // Toujours réencoder, même sans redimensionnement : c'est ce qui
        // ramène un PNG de 2,4 Mo à quelques centaines de kilo-octets et
        // supprime au passage les métadonnées de l'appareil photo.
        $targetWidth = min($width, self::MAX_WIDTH);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        // Un PNG ou un WebP transparent virerait au noir sans fond explicite.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $finalPath = $this->jpegPathFor($path);

        if (!imagejpeg($canvas, $finalPath, self::JPEG_QUALITY)) {
            imagedestroy($canvas);
            imagedestroy($source);

            throw new \RuntimeException('L’image n’a pas pu être enregistrée.');
        }

        imagedestroy($canvas);
        imagedestroy($source);

        if ($finalPath !== $path) {
            @unlink($path);
        }

        return $finalPath;
    }

    /**
     * Vrai si le fichier est déjà un JPEG aux bonnes dimensions et d'un poids
     * raisonnable — auquel cas le retoucher ne ferait que perdre de la qualité.
     */
    private function isAlreadyClean(string $path, int $type, int $width): bool
    {
        return $type === IMAGETYPE_JPEG
            && $width <= self::MAX_WIDTH
            && filesize($path) <= self::ACCEPTABLE_WEIGHT;
    }

    private function createImage(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /** Le fichier final est toujours un .jpg, quel que soit le format d'entrée. */
    private function jpegPathFor(string $path): string
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'jpg') {
            return $path;
        }

        return \dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
    }
}
