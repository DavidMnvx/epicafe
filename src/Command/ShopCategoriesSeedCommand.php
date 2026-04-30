<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ShopCategory;
use App\Repository\ShopCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:shop-categories:seed',
    description: 'Pré-remplit 4 articles thématiques pour la page Boutique. Idempotent par défaut. Utilise --reset pour repartir de zéro.',
)]
final class ShopCategoriesSeedCommand extends Command
{
    /**
     * 4 grands thèmes — chacun est un article éditorial sur la page Boutique.
     * Chaque ligne du champ "highlights" devient une puce visible sous la description.
     */
    private const THEMES = [
        [
            'slug'        => 'produits-locaux',
            'name'        => 'Produits locaux & terroir',
            'kicker'      => 'Épicerie de village',
            'icon'        => '🌿',
            'position'    => 10,
            'description' => <<<TXT
            À l'Épi-Café, le terroir n'est pas un argument marketing — c'est simplement la matière première de la boutique. La plupart de nos producteurs habitent à quelques kilomètres : on les voit passer, on goûte avant d'acheter, on adapte la sélection avec les saisons.

            Le miel vient des ruchers du Ventoux, l'huile d'olive d'un moulin du coin, les œufs d'une ferme voisine, et les légumes des maraîchers qu'on connaît par leur prénom. Tout n'est pas disponible toute l'année — c'est plutôt bon signe : ça veut dire qu'on n'achète pas n'importe quoi, n'importe quand, n'importe où.

            On privilégie ce qui a du goût, ce qui se conserve bien, et ce dont on est fiers de parler aux clients. Quand un produit nous déçoit, on le retire ; quand un nouveau producteur arrive et qu'il fait quelque chose de juste, on essaie, on goûte, on partage.

            Les rayons changent au fil des arrivages : un dimanche soir, ce ne sera pas exactement la même offre qu'un mardi matin. Posez la question au comptoir, on aime raconter l'origine de ce qu'on vend — et souvent, c'est la meilleure publicité qu'on puisse leur faire.
            TXT,
            'highlights'  => <<<TXT
            Miel de lavande et de garrigue du Ventoux
            Huile d'olive extra-vierge (moulins provençaux)
            Œufs fermiers de poules élevées en plein air
            Légumes et fruits de saison selon arrivages
            Confitures et conserves artisanales
            Tapenades, olives et tartinables du terroir
            TXT,
        ],
        [
            'slug'             => 'cave-et-vins',
            'name'             => 'Cave & vins du Ventoux',
            'kicker'           => 'Vignerons du coin',
            'icon'             => '🍷',
            'position'         => 20,
            'pullQuote'        => 'On ne veut pas tout faire — on veut bien faire les choses qu\'on aime, avec des producteurs qu\'on connaît.',
            'pullQuoteAuthor'  => "L'esprit de l'Épi-Café",
            'description' => <<<TXT
            La cave de l'Épi-Café est petite mais soignée. On a choisi de ne pas tout proposer pour pouvoir vraiment défendre chaque bouteille. Les vins viennent d'abord du Ventoux et des appellations voisines — Gigondas, Cairanne, Rasteau, Vacqueyras — avec quelques curiosités au-delà du département quand un domaine nous tape dans l'œil.

            On travaille en direct avec plusieurs vignerons de la région. Ce sont eux qui nous présentent leurs cuvées, on les goûte ensemble, et on garde celles qu'on a envie de défendre. Résultat : une carte qui bouge, qui suit les millésimes et les coups de cœur, et qui ressemble à ceux qui la composent plutôt qu'à un catalogue.

            Côté bières, une rotation de brasseries artisanales locales — blondes désaltérantes pour la terrasse, ambrées de caractère pour les soirs frais, IPA pour les amateurs de houblon. On essaie d'avoir toujours une nouveauté ou une saisonnière à proposer.

            Pour l'apéro, des pastis de Provence, vermouths, et quelques spiritueux choisis pour les classiques comme pour les curieux. Demandez conseil au comptoir, on saura vous orienter selon le repas, la saison ou simplement l'envie du moment.
            TXT,
            'highlights'  => <<<TXT
            Rouges, rosés et blancs du Ventoux
            Cuvées de domaines voisins (Gigondas, Rasteau…)
            Bières artisanales brassées à quelques kilomètres
            Pastis, apéritifs et spiritueux de Provence
            Idées cadeaux et coffrets sur demande
            TXT,
        ],
        [
            'slug'        => 'fromages-charcuterie',
            'name'        => 'Fromages & charcuterie',
            'kicker'      => 'À partager',
            'icon'        => '🧀',
            'position'    => 30,
            'description' => <<<TXT
            Le rayon fromages et charcuterie, c'est un peu le cœur convivial de la boutique. Que ce soit pour un pique-nique improvisé sous un olivier, un apéro entre amis sur la terrasse, ou une planche à emporter le soir après une journée bien remplie, on essaie d'avoir toujours de quoi composer une belle assiette.

            Les fromages viennent surtout de producteurs régionaux : chèvres frais et affinés des fermes voisines, tommes des Alpilles ou du Vaucluse, bleus de caractère, pâtes pressées qui se conservent. L'offre tourne avec les saisons d'affinage et les disponibilités fermières — il y a des semaines où certains fromages sont là, d'autres non, c'est la vie d'un commerce honnête.

            Côté charcuterie, on mise sur le sec et le bon : saucissons artisanaux travaillés à l'ancienne, jambons de pays, pâtés et terrines préparés en petites quantités. Rien d'industriel, tout vient de petites maisons qui prennent le temps de bien faire.

            Pour les apéritifs entre amis ou les repas plus formels, on prépare des plateaux sur commande — comptez 24h à l'avance pour qu'on puisse adapter la composition à vos goûts, votre nombre d'invités, et ce qu'on a de meilleur sous la main au moment voulu.
            TXT,
            'highlights'  => <<<TXT
            Fromages de chèvre des producteurs voisins
            Tommes, bleus et pâtes pressées de la région
            Charcuteries sèches (saucisson, jambon de pays)
            Pâtés, terrines et rillettes artisanales
            Plateaux apéritifs à commander 24h à l'avance
            TXT,
        ],
        [
            'slug'        => 'pain-et-depot',
            'name'        => 'Pain, viennoiseries & dépôts',
            'kicker'      => 'Chaque matin',
            'icon'        => '🥖',
            'position'    => 40,
            'description' => <<<TXT
            La boutique est aussi un dépôt de pain et viennoiseries pour le village. Chaque matin, on reçoit les baguettes, pains spéciaux et viennoiseries de la boulangerie Le Beffroi à Caromb — pour que les habitants du Barroux n'aient pas à descendre tous les jours hors du village pour leur baguette.

            On garde aussi l'épicerie essentielle pour dépanner : pâtes, riz, conserves, sucre, farine, produits ménagers, journaux locaux. Pas un supermarché, juste de quoi ne pas redescendre faire ses courses pour un détail un dimanche soir ou un jour férié.

            Et puis il y a tout ce qui fait d'un commerce de village un vrai service : Relais Poste pour les colis Colissimo et les recommandés, dépôt presse, dépannage du quotidien. Pratique, sans avoir à prendre la voiture.

            Au final, c'est ce qu'on aime dans le rôle d'épicier de village : être ce point d'arrêt simple et utile entre la boulangerie, la poste et le café. Un commerce qui rend service, et qui se souvient des prénoms.
            TXT,
            'highlights'  => <<<TXT
            Pain frais de la boulangerie Le Beffroi (Caromb)
            Viennoiseries du matin (croissants, pains au chocolat…)
            Épicerie essentielle (sucre, farine, pâtes, conserves)
            Point presse (journaux et magazines locaux)
            Relais Poste : colis, recommandés, affranchissement
            Produits ménagers et dépannage du quotidien
            TXT,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShopCategoryRepository $repo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'reset',
            null,
            InputOption::VALUE_NONE,
            'Supprime TOUTES les catégories boutique existantes avant de recréer les 4 thèmes par défaut. À utiliser si tu veux repartir de zéro.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('reset')) {
            if (!$io->confirm('Confirmer la suppression de TOUTES les catégories boutique existantes ?', false)) {
                $io->warning('Annulé.');
                return Command::SUCCESS;
            }

            $deleted = $this->em->createQuery('DELETE FROM ' . ShopCategory::class . ' c')->execute();
            $io->note(sprintf('%d catégorie(s) supprimée(s).', $deleted));
        }

        $created = 0;
        $skipped = 0;

        foreach (self::THEMES as $data) {
            if ($this->repo->findOneBy(['slug' => $data['slug']]) !== null) {
                $skipped++;
                continue;
            }

            $category = (new ShopCategory())
                ->setSlug($data['slug'])
                ->setName($data['name'])
                ->setKicker($data['kicker'])
                ->setIcon($data['icon'])
                ->setPosition($data['position'])
                ->setDescription($data['description'])
                ->setHighlights($data['highlights']);

            if (!empty($data['pullQuote'])) {
                $category->setPullQuote($data['pullQuote']);
            }
            if (!empty($data['pullQuoteAuthor'])) {
                $category->setPullQuoteAuthor($data['pullQuoteAuthor']);
            }

            $this->em->persist($category);
            $created++;
        }

        $this->em->flush();

        $io->success(sprintf('Thèmes boutique : %d créé(s), %d déjà présent(s).', $created, $skipped));

        return Command::SUCCESS;
    }
}
