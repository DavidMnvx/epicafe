<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ImageProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Le convertisseur est la porte d'entrée de toutes les images du site : une
 * régression ici renvoie des photos de 9 Mo aux visiteurs, ou fait échouer un
 * envoi sans explication.
 */
final class ImageProcessorTest extends TestCase
{
    private ImageProcessor $processor;
    private string $workDir;

    protected function setUp(): void
    {
        $this->processor = new ImageProcessor(new NullLogger());
        $this->workDir = sys_get_temp_dir() . '/image-processor-' . uniqid();

        mkdir($this->workDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->workDir);
    }

    public function testOversizedImageIsScaledDown(): void
    {
        $path = $this->createPng(3000, 2000);

        $result = $this->processor->process($path);

        [$width] = getimagesize($result);

        self::assertSame(ImageProcessor::MAX_WIDTH, $width, 'La largeur doit être ramenée au maximum autorisé.');
    }

    public function testAspectRatioIsPreserved(): void
    {
        $path = $this->createPng(3000, 2000);

        [$width, $height] = getimagesize($this->processor->process($path));

        self::assertEqualsWithDelta(3 / 2, $width / $height, 0.01, 'Les proportions doivent être conservées.');
    }

    public function testPngIsConvertedToJpeg(): void
    {
        $result = $this->processor->process($this->createPng(800, 600));

        self::assertStringEndsWith('.jpg', $result);
        self::assertSame(IMAGETYPE_JPEG, getimagesize($result)[2]);
    }

    public function testTransparentPngGetsWhiteBackgroundNotBlack(): void
    {
        $path = $this->createPng(400, 300, transparent: true);

        $result = $this->processor->process($path);
        $image = imagecreatefromjpeg($result);

        // Un aplat transparent viré au noir est le symptôme classique d'une
        // conversion sans fond explicite.
        $corner = imagecolorsforindex($image, imagecolorat($image, 2, 2));
        imagedestroy($image);

        self::assertGreaterThan(200, $corner['red'], 'Le fond doit rester clair.');
        self::assertGreaterThan(200, $corner['green'], 'Le fond doit rester clair.');
        self::assertGreaterThan(200, $corner['blue'], 'Le fond doit rester clair.');
    }

    public function testSmallJpegIsLeftUntouched(): void
    {
        $path = $this->createJpeg(600, 400);
        $before = md5_file($path);

        $result = $this->processor->process($path);

        self::assertSame($path, $result);
        self::assertSame($before, md5_file($result), 'Une image déjà conforme ne doit pas être réencodée.');
    }

    public function testPdfIsConvertedToJpegAndSourceRemoved(): void
    {
        $path = $this->createPdf();

        $result = $this->processor->process($path);

        self::assertStringEndsWith('.jpg', $result);
        self::assertFileExists($result);
        self::assertFileDoesNotExist($path, 'Le PDF source doit être supprimé après conversion.');
        self::assertSame(IMAGETYPE_JPEG, getimagesize($result)[2]);
    }

    public function testNonImageIsRejectedWithAReadableMessage(): void
    {
        $path = $this->workDir . '/faux.jpg';
        file_put_contents($path, 'ceci n’est pas une image');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/pas une image exploitable/');

        $this->processor->process($path);
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->processor->process($this->workDir . '/absent.jpg');
    }

    private function createPng(int $width, int $height, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 60));
        }

        $path = $this->workDir . '/source.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function createJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 160, 140));

        $path = $this->workDir . '/source.jpg';
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return $path;
    }

    private function createPdf(): string
    {
        $path = $this->workDir . '/document.pdf';

        file_put_contents($path, "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R"
            . "/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            . "4 0 obj<</Length 54>>stream\nBT /F1 36 Tf 60 700 Td (Carte du jour) Tj ET\nendstream endobj\n"
            . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            . "trailer<</Root 1 0 R>>\n");

        return $path;
    }
}
