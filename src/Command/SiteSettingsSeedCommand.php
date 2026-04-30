<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:site-settings:seed',
    description: 'Crée ou synchronise les paramètres globaux du site (contact, social, fermeture, général).',
)]
final class SiteSettingsSeedCommand extends Command
{
    /**
     * Définition centralisée des paramètres disponibles dans l'admin.
     * Ajoute / retire des entrées ici puis relance la commande.
     *
     * @var array<int, array{
     *     key:string, label:string, description?:string, value?:string,
     *     type:string, group:string, position:int
     * }>
     */
    private const SETTINGS = [
        // ===== CONTACT =====
        [
            'key' => 'contact_phone',
            'label' => 'Téléphone',
            'description' => 'Numéro affiché dans le footer, la page contact et les boutons "Appeler".',
            'value' => '04 90 00 00 00',
            'type' => SiteSetting::TYPE_TEL,
            'group' => SiteSetting::GROUP_CONTACT,
            'position' => 10,
        ],
        [
            'key' => 'contact_email',
            'label' => 'Email',
            'description' => 'Email principal — affiché dans le footer et destinataire du formulaire de contact.',
            'value' => 'contact@epicafe.fr',
            'type' => SiteSetting::TYPE_EMAIL,
            'group' => SiteSetting::GROUP_CONTACT,
            'position' => 20,
        ],
        [
            'key' => 'contact_address',
            'label' => 'Adresse',
            'description' => 'Adresse postale complète, sur une ou plusieurs lignes.',
            'value' => "56 Chemin Neuf\n84330 Le Barroux",
            'type' => SiteSetting::TYPE_TEXTAREA,
            'group' => SiteSetting::GROUP_CONTACT,
            'position' => 30,
        ],
        [
            'key' => 'contact_hours_summary',
            'label' => 'Horaires (résumé)',
            'description' => 'Résumé des horaires en quelques lignes, affiché dans le footer.',
            'value' => "Lun-Mar-Jeu : 7h30 - 18h30\nVen-Sam : 7h30 - 21h30\nDim : 8h00 - 12h00\nFermé le mercredi",
            'type' => SiteSetting::TYPE_TEXTAREA,
            'group' => SiteSetting::GROUP_CONTACT,
            'position' => 40,
        ],

        // ===== RÉSEAUX SOCIAUX =====
        [
            'key' => 'social_facebook',
            'label' => 'Facebook (URL)',
            'description' => 'Lien complet vers la page Facebook (https://www.facebook.com/...). Laisse vide pour ne pas afficher.',
            'value' => '',
            'type' => SiteSetting::TYPE_URL,
            'group' => SiteSetting::GROUP_SOCIAL,
            'position' => 10,
        ],
        [
            'key' => 'social_instagram',
            'label' => 'Instagram (URL)',
            'description' => 'Lien complet vers le profil Instagram. Laisse vide pour ne pas afficher.',
            'value' => '',
            'type' => SiteSetting::TYPE_URL,
            'group' => SiteSetting::GROUP_SOCIAL,
            'position' => 20,
        ],
        [
            'key' => 'social_google_maps',
            'label' => 'Google Maps (URL)',
            'description' => 'Lien direct vers la fiche Google Maps. Utilisé pour le bouton "Itinéraire".',
            'value' => 'https://maps.google.com/?q=56+Chemin+Neuf+84330+Le+Barroux',
            'type' => SiteSetting::TYPE_URL,
            'group' => SiteSetting::GROUP_SOCIAL,
            'position' => 30,
        ],
        [
            'key' => 'social_google_reviews',
            'label' => 'Avis Google (URL)',
            'description' => 'Lien pour laisser un avis sur Google. Si renseigné, la section "avis clients" apparaît au bas de toutes les pages. La note moyenne et le nombre d\'avis s\'affichent automatiquement depuis le CRUD "Avis clients (Google)".',
            'value' => '',
            'type' => SiteSetting::TYPE_URL,
            'group' => SiteSetting::GROUP_SOCIAL,
            'position' => 40,
        ],

        // ===== VISIBILITÉ DES PAGES =====
        [
            'key' => 'nav_show_events',
            'label' => 'Page Événements',
            'description' => 'Affiche le lien "Événements" dans le menu (desktop + mobile). Décoche pour masquer la page sans la supprimer.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 10,
        ],
        [
            'key' => 'nav_show_gallery',
            'label' => 'Page Galerie photos',
            'description' => 'Affiche le lien "Galerie Photos" dans le menu.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 20,
        ],
        [
            'key' => 'nav_show_partners',
            'label' => 'Page Partenaires',
            'description' => 'Affiche le lien "Partenaires" dans le menu.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 30,
        ],
        [
            'key' => 'nav_show_shop',
            'label' => 'Page Boutique',
            'description' => 'Affiche le lien "Boutique" dans le menu.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 40,
        ],
        [
            'key' => 'nav_show_menu',
            'label' => 'Page Menu',
            'description' => 'Affiche le lien "Menu" dans la barre de navigation.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 50,
        ],
        [
            'key' => 'nav_show_contact',
            'label' => 'Page Contact',
            'description' => 'Affiche le lien "Contact" dans la barre de navigation.',
            'value' => '1',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_NAVIGATION,
            'position' => 60,
        ],

        // ===== BANDEAU FERMETURE EXCEPTIONNELLE =====
        [
            'key' => 'closure_active',
            'label' => 'Bandeau fermeture activé ?',
            'description' => 'Active/désactive l\'affichage d\'un bandeau d\'information en haut de toutes les pages.',
            'value' => '0',
            'type' => SiteSetting::TYPE_BOOLEAN,
            'group' => SiteSetting::GROUP_CLOSURE,
            'position' => 10,
        ],
        [
            'key' => 'closure_message',
            'label' => 'Message du bandeau',
            'description' => 'Texte affiché quand le bandeau est actif. Ex : "Fermeture exceptionnelle du 14 au 21 août — réouverture le 22".',
            'value' => 'Fermeture exceptionnelle du XX au XX — réouverture le XX.',
            'type' => SiteSetting::TYPE_TEXTAREA,
            'group' => SiteSetting::GROUP_CLOSURE,
            'position' => 20,
        ],

        // ===== GÉNÉRAL =====
        [
            'key' => 'site_tagline',
            'label' => 'Slogan du site',
            'description' => 'Phrase courte affichée en sous-titre du site. Utilisée dans les meta description.',
            'value' => 'Bar & Épicerie locale au pied du Mont Ventoux',
            'type' => SiteSetting::TYPE_TEXT,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 10,
        ],
        [
            'key' => 'site_owner_name',
            'label' => 'Nom du / des gérants',
            'description' => 'Affiché dans les mentions légales. Ex : "Frédéric & Isabelle Dupont".',
            'value' => '',
            'type' => SiteSetting::TYPE_TEXT,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 20,
        ],
        [
            'key' => 'site_legal_form',
            'label' => 'Forme juridique',
            'description' => 'Ex : SARL, SAS, EI, micro-entreprise. Utilisé dans les mentions légales.',
            'value' => '',
            'type' => SiteSetting::TYPE_TEXT,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 30,
        ],
        [
            'key' => 'site_siret',
            'label' => 'SIRET',
            'description' => 'Numéro SIRET de l\'établissement. Utilisé dans les mentions légales.',
            'value' => '',
            'type' => SiteSetting::TYPE_TEXT,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 40,
        ],
        [
            'key' => 'legal_hosting_name',
            'label' => 'Hébergeur (nom)',
            'description' => 'Nom de la société qui héberge le site. Obligatoire dans les mentions légales (LCEN). Ex : "OVH SAS", "Hetzner Online GmbH".',
            'value' => '',
            'type' => SiteSetting::TYPE_TEXT,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 50,
        ],
        [
            'key' => 'legal_hosting_address',
            'label' => 'Hébergeur (adresse)',
            'description' => 'Adresse postale complète de l\'hébergeur. Ex : "2 rue Kellermann, 59100 Roubaix, France".',
            'value' => '',
            'type' => SiteSetting::TYPE_TEXTAREA,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 60,
        ],
        [
            'key' => 'legal_hosting_phone',
            'label' => 'Hébergeur (téléphone)',
            'description' => 'Téléphone de l\'hébergeur (info légale).',
            'value' => '',
            'type' => SiteSetting::TYPE_TEL,
            'group' => SiteSetting::GROUP_GENERAL,
            'position' => 70,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteSettingRepository $repo,
    ) {
        parent::__construct();
    }

    /** Anciennes clés à nettoyer (paramètres retirés du système). */
    private const REMOVED_KEYS = ['google_rating', 'google_reviews_count'];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Nettoyage des clés obsolètes
        $removed = 0;
        foreach (self::REMOVED_KEYS as $oldKey) {
            $entity = $this->repo->findOneByKey($oldKey);
            if ($entity !== null) {
                $this->em->remove($entity);
                $removed++;
            }
        }
        if ($removed > 0) {
            $this->em->flush();
            $io->note(sprintf('%d paramètre(s) obsolète(s) supprimé(s).', $removed));
        }

        $created = 0;
        $updated = 0;

        foreach (self::SETTINGS as $data) {
            $entity = $this->repo->findOneByKey($data['key']);

            if ($entity === null) {
                $entity = (new SiteSetting())
                    ->setKey($data['key'])
                    ->setValue($data['value'] ?? null);
                $this->em->persist($entity);
                $created++;
            } else {
                $updated++;
            }

            // Métadonnées toujours synchronisées (label, description, type, group, position)
            // mais on ne réécrit JAMAIS la valeur si elle existe déjà.
            $entity
                ->setLabel($data['label'])
                ->setDescription($data['description'] ?? null)
                ->setType($data['type'])
                ->setGroupName($data['group'])
                ->setPosition($data['position']);
        }

        $this->em->flush();

        $io->success(sprintf('Paramètres : %d créé(s), %d mis à jour (méta uniquement, valeurs préservées).', $created, $updated));

        return Command::SUCCESS;
    }
}
