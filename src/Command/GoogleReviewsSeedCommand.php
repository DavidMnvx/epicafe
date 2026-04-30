<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\GoogleReview;
use App\Repository\GoogleReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:google-reviews:seed',
    description: 'Crée 3 avis exemples pour démarrer (à remplacer par les vrais avis Google ensuite).',
)]
final class GoogleReviewsSeedCommand extends Command
{
    private const SAMPLES = [
        [
            'author' => 'Sophie M.',
            'rating' => 5,
            'text' => 'Lieu très convivial et chaleureux, l\'équipe est aux petits soins ! Les produits locaux sont excellents et la sélection de vins est top. On y revient avec plaisir.',
            'days_ago' => 14,
            'position' => 10,
        ],
        [
            'author' => 'Jean Dupont',
            'rating' => 5,
            'text' => 'Une vraie épicerie de village comme on en fait plus. Le pain est délicieux et l\'accueil parfait. À recommander absolument quand on passe au Barroux.',
            'days_ago' => 32,
            'position' => 20,
        ],
        [
            'author' => 'Marie L.',
            'rating' => 5,
            'text' => 'Soirée aïoli mémorable ! Tout était fait maison et les produits étaient frais. Ambiance familiale et conviviale, on s\'est régalés.',
            'days_ago' => 60,
            'position' => 30,
        ],
        [
            'author' => 'Thomas R.',
            'rating' => 4,
            'text' => 'Belle découverte au pied du Ventoux. Une jolie sélection de vins du coin, du miel local et un café honnête. L\'accueil est sympa, on prend le temps de discuter avec les patrons.',
            'days_ago' => 78,
            'position' => 40,
        ],
        [
            'author' => 'Émilie B.',
            'rating' => 5,
            'text' => 'On s\'arrête toujours ici quand on monte au Barroux. Le pain de Caromb, la cave à vins, les fromages affinés... tout est de qualité et l\'ambiance est chaleureuse. Une institution du village !',
            'days_ago' => 95,
            'position' => 50,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GoogleReviewRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Si la table contient déjà des avis, on n'en recrée pas (évite les doublons).
        $existing = $this->repo->count([]);
        if ($existing > 0) {
            $io->note(sprintf('%d avis déjà présents — rien à faire. (Lance avec une base vide pour seeder.)', $existing));
            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $created = 0;

        foreach (self::SAMPLES as $data) {
            $review = (new GoogleReview())
                ->setAuthor($data['author'])
                ->setRating($data['rating'])
                ->setText($data['text'])
                ->setReviewDate($now->modify('-' . $data['days_ago'] . ' days'))
                ->setPosition($data['position'])
                ->setIsPublished(true);

            $this->em->persist($review);
            $created++;
        }

        $this->em->flush();

        $io->success(sprintf('%d avis exemples créés. Va dans l\'admin pour les modifier ou en ajouter d\'autres.', $created));

        return Command::SUCCESS;
    }
}
