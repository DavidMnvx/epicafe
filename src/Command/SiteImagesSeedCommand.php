<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SiteImage;
use App\Repository\SiteImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:site-images:seed',
    description: 'Crée ou met à jour les emplacements d’images éditables du site (bannières, photos des pages).',
)]
final class SiteImagesSeedCommand extends Command
{
    /**
     * Liste des emplacements d'images éditables via l'admin.
     * Ajoute ou retire des entrées ici puis relance la commande.
     */
    private const SLOTS = [
        [
            'slug'        => 'home_hero',
            'label'       => 'Accueil — image principale',
            'description' => 'Grande image en haut de la page d\'accueil. Format paysage 1920×1080 px, JPG ou WebP, < 500 Ko.',
            'fallback'    => 'images/Accueil-2.jpg',
        ],
        [
            'slug'        => 'home_shop_preview',
            'label'       => 'Accueil — aperçu boutique',
            'description' => 'Image de la section "boutique" sur la page d\'accueil. 1200×800 px, JPG/WebP.',
            'fallback'    => 'images/boutique-home.png',
        ],
        [
            'slug'        => 'banner_menu',
            'label'       => 'Bannière — page Menu',
            'description' => 'Image en haut de la page Menu. Format paysage 1920×600 px, JPG ou WebP, < 400 Ko.',
            'fallback'    => 'images/banners-menu.png',
        ],
        [
            'slug'        => 'banner_shop',
            'label'       => 'Bannière — page Boutique',
            'description' => 'Image en haut de la page Boutique. 1920×600 px, JPG ou WebP, < 400 Ko.',
            'fallback'    => 'images/banners-shop.png',
        ],
        [
            'slug'        => 'banner_contact',
            'label'       => 'Bannière — page Contact',
            'description' => 'Image en haut de la page Contact. 1920×600 px, JPG ou WebP, < 400 Ko.',
            'fallback'    => 'images/banners-contact.png',
        ],
        [
            'slug'        => 'banner_events',
            'label'       => 'Bannière — page Événements',
            'description' => 'Image en haut de la page Événements (aussi utilisée comme image par défaut d\'un événement sans visuel). 1920×600 px.',
            'fallback'    => 'images/banners-events.png',
        ],
        [
            'slug'        => 'banner_partners',
            'label'       => 'Bannière — page Partenaires',
            'description' => 'Image en haut de la page Partenaires. 1920×600 px.',
            'fallback'    => 'images/banners-partners.png',
        ],
        [
            'slug'        => 'banner_gallery',
            'label'       => 'Bannière — page Galerie',
            'description' => 'Image en haut de la page Galerie. 1920×600 px, JPG ou WebP, < 400 Ko.',
            'fallback'    => 'images/Le barroux-galerie.png',
        ],
        [
            'slug'        => 'contact_team_photo',
            'label'       => 'Contact — Photo de l\'équipe',
            'description' => 'Photo de l\'équipe affichée sur la page Contact, à côté du texte de présentation. Format paysage 1200×900 px (4:3), JPG ou WebP, < 500 Ko.',
            'fallback'    => 'images/equipe-epicafe.jpg',
        ],

        // ===== ACCUEIL — sections intermédiaires =====
        [
            'slug'        => 'home_intro_photo',
            'label'       => 'Accueil — Photo "Bienvenue"',
            'description' => 'Photo affichée à côté du texte de bienvenue (section "Bienvenue au cœur du village"). Format paysage 1200×900 px (4:3), JPG ou WebP, < 400 Ko. Idéal : photo d\'ambiance du lieu.',
            'fallback'    => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?q=80&w=1600&auto=format&fit=crop',
        ],
        [
            'slug'        => 'home_apero_1',
            'label'       => 'Accueil — Apéro 1 (image principale)',
            'description' => 'Première image du collage apéro (la plus grande). Format carré ou paysage 800×800 px, JPG/WebP/AVIF, < 300 Ko.',
            'fallback'    => 'images/apero-3.avif',
        ],
        [
            'slug'        => 'home_apero_2',
            'label'       => 'Accueil — Apéro 2 (image secondaire)',
            'description' => 'Deuxième image du collage apéro (en haut). 600×600 px, JPG/WebP/AVIF, < 200 Ko.',
            'fallback'    => 'images/apero-2.avif',
        ],
        [
            'slug'        => 'home_apero_3',
            'label'       => 'Accueil — Apéro 3 (image tertiaire)',
            'description' => 'Troisième image du collage apéro (en bas). 600×600 px, JPG/WebP/AVIF, < 200 Ko.',
            'fallback'    => 'images/apero-1.avif',
        ],
        [
            'slug'        => 'home_product_wine',
            'label'       => 'Accueil — Produits locaux : Vins',
            'description' => 'Image de la card "Vins du Ventoux" sur la home. Format carré ou paysage 800×600 px, JPG/PNG/WebP, < 250 Ko.',
            'fallback'    => 'images/Produits-locaux-vin.png',
        ],
        [
            'slug'        => 'home_product_terroir',
            'label'       => 'Accueil — Produits locaux : Terroir',
            'description' => 'Image de la card "Produits du terroir" sur la home (huile, miel, confitures…). 800×600 px, JPG/PNG/WebP, < 250 Ko.',
            'fallback'    => 'images/Produits-locaux-terroir.png',
        ],
        [
            'slug'        => 'home_product_cheese',
            'label'       => 'Accueil — Produits locaux : Fromages',
            'description' => 'Image de la card "Fromages & charcuterie" sur la home. 800×600 px, JPG/PNG/WebP, < 250 Ko.',
            'fallback'    => 'images/Produits-from-char.png',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteImageRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = 0;
        $updated = 0;

        foreach (self::SLOTS as $slot) {
            $entity = $this->repo->findOneBySlug($slot['slug']);

            if ($entity === null) {
                $entity = (new SiteImage())
                    ->setSlug($slot['slug']);
                $this->em->persist($entity);
                $created++;
            } else {
                $updated++;
            }

            $entity
                ->setLabel($slot['label'])
                ->setDescription($slot['description'])
                ->setFallbackPath($slot['fallback']);
        }

        $this->em->flush();

        $io->success(sprintf('Emplacements synchronisés : %d créé(s), %d mis à jour.', $created, $updated));

        return Command::SUCCESS;
    }
}
