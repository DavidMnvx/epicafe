<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réglages du site sur une seule page.
 *
 * Le CRUD EasyAdmin sur SiteSetting obligeait à ouvrir un formulaire par
 * paramètre : pour corriger un téléphone et un horaire, il fallait deux
 * allers-retours dans une grille de 23 lignes techniques. Ici tout tient sur
 * un écran, groupé par thème, avec un seul bouton d'enregistrement.
 */
#[IsGranted('ROLE_ADMIN')]
final class SiteSettingsPageController extends AbstractController
{
    /** Ordre d'affichage : du plus souvent modifié au plus rare. */
    private const GROUPS = [
        SiteSetting::GROUP_CONTACT => [
            'label' => 'Coordonnées',
            'help' => 'Utilisées partout sur le site : pied de page, page contact, mentions légales.',
        ],
        SiteSetting::GROUP_CLOSURE => [
            'label' => 'Fermeture exceptionnelle',
            'help' => 'Affiche un bandeau en haut de toutes les pages. Pense à le désactiver à la réouverture.',
        ],
        SiteSetting::GROUP_NAVIGATION => [
            'label' => 'Pages visibles',
            'help' => 'Décoche une page pour la retirer du menu du site. Son contenu est conservé.',
        ],
        SiteSetting::GROUP_SOCIAL => [
            'label' => 'Réseaux sociaux & Maps',
            'help' => 'Laisse vide pour ne pas afficher le lien.',
        ],
        SiteSetting::GROUP_GENERAL => [
            'label' => 'Général & mentions légales',
            'help' => null,
        ],
    ];

    #[Route('/admin/reglages', name: 'admin_site_settings')]
    public function index(
        Request $request,
        SiteSettingRepository $repository,
        EntityManagerInterface $em,
    ): Response {
        /** @var SiteSetting[] $settings */
        $settings = $repository->findBy([], ['position' => 'ASC']);

        $builder = $this->createFormBuilder(null, ['attr' => ['novalidate' => 'novalidate']]);

        foreach ($settings as $setting) {
            $builder->add($this->fieldName($setting), $this->fieldType($setting), [
                'label' => $setting->getLabel(),
                'help' => $setting->getDescription(),
                'required' => false,
                'data' => $setting->getType() === SiteSetting::TYPE_BOOLEAN
                    ? $setting->asBool()
                    : $setting->getValue(),
                ...$this->fieldOptions($setting),
            ]);
        }

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($settings as $setting) {
                $submitted = $form->get($this->fieldName($setting))->getData();

                $setting->setValue(
                    $setting->getType() === SiteSetting::TYPE_BOOLEAN
                        ? ($submitted ? '1' : '0')
                        : (is_string($submitted) ? trim($submitted) : null)
                );
            }

            $em->flush();
            $this->addFlash('success', 'Réglages enregistrés.');

            return $this->redirectToRoute('admin_site_settings');
        }

        // Regroupement pour l'affichage, en respectant l'ordre de self::GROUPS.
        $groups = [];
        foreach (self::GROUPS as $key => $meta) {
            $inGroup = array_values(array_filter(
                $settings,
                static fn (SiteSetting $s) => $s->getGroupName() === $key
            ));

            if ($inGroup !== []) {
                $groups[$key] = $meta + ['settings' => $inGroup];
            }
        }

        // Un paramètre ajouté plus tard avec un groupe inconnu resterait
        // invisible : on le rattrape dans une section « Autres ».
        $known = array_keys(self::GROUPS);
        $orphans = array_values(array_filter(
            $settings,
            static fn (SiteSetting $s) => !in_array($s->getGroupName(), $known, true)
        ));

        if ($orphans !== []) {
            $groups['_other'] = ['label' => 'Autres', 'help' => null, 'settings' => $orphans];
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form,
            'groups' => $groups,
        ]);
    }

    private function fieldName(SiteSetting $setting): string
    {
        return 's' . $setting->getId();
    }

    /** @return class-string */
    private function fieldType(SiteSetting $setting): string
    {
        return match ($setting->getType()) {
            SiteSetting::TYPE_BOOLEAN => CheckboxType::class,
            SiteSetting::TYPE_EMAIL => EmailType::class,
            SiteSetting::TYPE_TEL => TelType::class,
            SiteSetting::TYPE_URL => UrlType::class,
            SiteSetting::TYPE_TEXTAREA => TextareaType::class,
            default => TextType::class,
        };
    }

    /** @return array<string, mixed> */
    private function fieldOptions(SiteSetting $setting): array
    {
        return match ($setting->getType()) {
            // Sans ça, saisir « epicafebarroux.fr » sans protocole est refusé.
            SiteSetting::TYPE_URL => ['default_protocol' => 'https'],
            SiteSetting::TYPE_TEXTAREA => ['attr' => ['rows' => 3]],
            default => [],
        };
    }
}
