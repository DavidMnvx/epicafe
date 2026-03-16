<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[AsCommand(
    name: 'app:build-static-demo',
    description: 'Génère une maquette HTML statique dans public/static-demo'
)]
class BuildStaticDemoCommand extends Command
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly Filesystem $fs
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectDir = dirname(__DIR__, 2);
        $publicDir  = $projectDir . '/public';
        $assetsDir  = $projectDir . '/assets';
        $outDir     = $publicDir . '/static-demo';

        // 1) Reset dossier de sortie
        $this->fs->remove($outDir);
        $this->fs->mkdir($outDir);

        // 2) Export pages FRONT (mets ici tes vraies URLs)
        $pages = [
            '/home'              => 'index.html',
            '/menu'              => 'menu/index.html',
            '/events/evenements' => 'events/index.html',
            '/galerie'           => 'gallery/index.html',
            '/partenaires'       => 'partners/index.html',
        ];

        foreach ($pages as $path => $file) {
            try {
                $request  = Request::create($path, 'GET');
                $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

                if ($response->getStatusCode() >= 400) {
                    $io->warning("$path -> erreur " . $response->getStatusCode());
                    continue;
                }

                $fullPath = $outDir . '/' . $file;
                $this->fs->mkdir(\dirname($fullPath));

                $html = $response->getContent() ?? '';

                // profondeur pour chemins relatifs
                $depth  = substr_count(trim($file, '/'), '/');
                $prefix = str_repeat('../', $depth);

                // réécrit les chemins pour file://
                $html = $this->rewriteHtmlAssetPaths($html, $prefix);

                $this->fs->dumpFile($fullPath, $html);
                $io->text("OK $path -> $file");
            } catch (\Throwable $e) {
                $io->warning("$path ignoré : " . $e->getMessage());
            }
        }

        // 3) Copier les assets depuis assets/ (et uploads si tu veux)
        $this->copyFirstExisting($io, [$assetsDir . '/styles', $publicDir . '/styles'], $outDir . '/styles');
        $this->copyFirstExisting($io, [$assetsDir . '/images', $publicDir . '/images'], $outDir . '/images');
        $this->copyFirstExisting($io, [$publicDir . '/uploads'], $outDir . '/uploads'); // optionnel

        // (optionnel) si tu as un build (vite/encore)
       $cssDirs = [$outDir . '/styles', $outDir . '/build'];

foreach ($cssDirs as $dir) {
    if (!$this->fs->exists($dir)) {
        continue;
    }

    foreach (glob($dir . '/*.css') ?: [] as $cssFile) {
        $content = file_get_contents($cssFile) ?: '';

        // Absolus -> relatifs (les pages sont dans des sous-dossiers, donc on met ../)
        $content = preg_replace('~url\(\s*[\'"]?/images/~',        'url(../images/', $content) ?? $content;
        $content = preg_replace('~url\(\s*[\'"]?/assets/images/~', 'url(../images/', $content) ?? $content;
        $content = preg_replace('~url\(\s*[\'"]?/build/~',         'url(../build/',  $content) ?? $content;

        file_put_contents($cssFile, $content);
    }
}

        // 4) Fix CSS: url("/images/...") / url("/assets/images/...") => relatif
        $cssDirs = [$outDir . '/styles', $outDir . '/build'];

        foreach ($cssDirs as $dir) {
            if (!$this->fs->exists($dir)) {
                continue;
            }
        
            foreach (glob($dir . '/*.css') ?: [] as $cssFile) {
                $content = file_get_contents($cssFile) ?: '';
        
                // Absolus -> relatifs (les pages sont dans des sous-dossiers, donc on met ../)
                $content = preg_replace('~url\(\s*[\'"]?/images/~',        'url(../images/', $content) ?? $content;
                $content = preg_replace('~url\(\s*[\'"]?/assets/images/~', 'url(../images/', $content) ?? $content;
                $content = preg_replace('~url\(\s*[\'"]?/build/~',         'url(../build/',  $content) ?? $content;
        
                file_put_contents($cssFile, $content);
            }
        }

        $io->success('Maquette générée dans public/static-demo (assets copiés + chemins corrigés)');
        return Command::SUCCESS;
    }

    private function copyFirstExisting(SymfonyStyle $io, array $candidates, string $dest): void
    {
        foreach ($candidates as $from) {
            if ($this->fs->exists($from)) {
                $this->fs->mirror($from, $dest, null, ['override' => true, 'delete' => true]);
                $io->text('Assets copiés: ' . $from . ' -> ' . $dest);
                return;
            }
        }
        $io->warning('Assets introuvables: ' . implode(' OR ', $candidates));
    }

    private function rewriteHtmlAssetPaths(string $html, string $prefix): string
{
    // $prefix = "../" ou "" selon la profondeur
    // on veut des chemins relatifs dans le static-demo
    // ex: "../images/..."
    $p = $prefix;

    // 0) Corrige les cas "collés" (manque le slash)
    // imagesLogos/...  -> images/Logos/...
    $html = preg_replace('~\b(images)Logos/~', '$1/Logos', $html) ?? $html;
    // imagesapero-...  -> images/apero-... (ou adapte si tu as un sous-dossier)
    $html = preg_replace('~\b(images)apero~', '$1/apero', $html) ?? $html;

    // 1) Convertit les chemins absolus /images /uploads /build /scripts en relatifs
    $html = preg_replace('~(href|src)=([\'"])/images/~',  '$1=$2' . $p . 'images',  $html) ?? $html;
    $html = preg_replace('~(href|src)=([\'"])/uploads/~', '$1=$2' . $p . 'uploads', $html) ?? $html;
    $html = preg_replace('~(href|src)=([\'"])/build/~',   '$1=$2' . $p . 'build',   $html) ?? $html;
    $html = preg_replace('~(href|src)=([\'"])/scripts/~', '$1=$2' . $p . 'scripts', $html) ?? $html;

    // 2) Supporte assets/...
    $html = preg_replace('~(href|src)=([\'"])/assets/images/~',  '$1=$2' . $p . 'images',  $html) ?? $html;
    $html = preg_replace('~(href|src)=([\'"])/assets/styles/~',  '$1=$2' . $p . 'styles',  $html) ?? $html;
    $html = preg_replace('~(href|src)=([\'"])/assets/scripts/~', '$1=$2' . $p . 'scripts', $html) ?? $html;

    // 3) Important : ajoute le prefix sur les chemins relatifs simples
    // src="images/..." -> src="../images/..." (sur les sous-pages)
    $html = preg_replace('~(href|src)=([\'"])(images/|uploads/|build/|scripts/)~', '$1=$2' . $p . '$3', $html) ?? $html;

    // 4) Retire les hashes éventuels avant extension:  logo-ABC123.svg -> logo.svg
    // marche sur .png .jpg .jpeg .webp .avif .svg .gif
    $html = preg_replace('~(\.[a-zA-Z0-9_-]+)-[A-Za-z0-9]{5,}(\.(?:png|jpe?g|webp|avif|svg|gif))~', '$1$2', $html) ?? $html;

    // 5) Inline CSS: url('/uploads/...') url('/images/...') etc
    $html = preg_replace('~url\((["\']?)/images/~',  'url($1' . $p . 'images',  $html) ?? $html;
    $html = preg_replace('~url\((["\']?)/uploads/~', 'url($1' . $p . 'uploads', $html) ?? $html;

    // 6) Inline CSS avec url('/uploads/gallery/IMG-...-hash.jpg') -> url('../uploads/gallery/IMG-....jpg')
    $html = preg_replace('~(uploads/[^"\')]+)-[A-Za-z0-9]{5,}(\.(?:png|jpe?g|webp|avif|svg|gif))~', '$1$2', $html) ?? $html;

    return $html;
}

}