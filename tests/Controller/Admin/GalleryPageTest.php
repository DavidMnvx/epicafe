<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\GalleryPhoto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le rendu de la page galerie du back-office.
 *
 * Les requêtes sont en lecture seule : le test se contente d'afficher la page
 * avec les données présentes en base.
 */
final class GalleryPageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $this->em->getRepository(User::class)->findOneBy([]);

        if ($admin === null) {
            self::markTestSkipped('Aucun compte administrateur en base.');
        }

        $this->client->loginUser($admin);
    }

    public function testPageLoads(): void
    {
        $crawler = $this->client->request('GET', '/admin/photos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Les photos');
    }

    public function testDropzoneIsPresent(): void
    {
        $crawler = $this->client->request('GET', '/admin/photos');

        self::assertCount(1, $crawler->filter('[data-dropzone]'), 'La zone de dépôt doit être présente.');
        self::assertCount(1, $crawler->filter('[data-file-input]'), 'Le sélecteur de fichiers doit être présent.');
    }

    public function testEveryPhotoIsRenderedAsATile(): void
    {
        $expected = $this->em->getRepository(GalleryPhoto::class)->count([]);

        $crawler = $this->client->request('GET', '/admin/photos');

        self::assertCount($expected, $crawler->filter('[data-photo]'), 'Chaque photo doit avoir sa tuile.');
        self::assertCount($expected, $crawler->filter('[data-photo] img'), 'Chaque tuile doit afficher sa vignette.');
        self::assertCount($expected, $crawler->filter('[data-photo] [data-handle]'), 'Chaque tuile doit avoir sa poignée.');
    }

    public function testTilesAreInsideTheGrid(): void
    {
        // Régression du 01/09 : un chevron manquant sur le <ul> faisait avaler
        // le premier <li> comme attributs — la « première tuile » devenait la
        // grille elle-même, étalée en pleine largeur.
        $crawler = $this->client->request('GET', '/admin/photos');

        self::assertCount(
            0,
            $crawler->filter('[data-photos][data-photo]'),
            'La grille ne doit pas porter les attributs d’une tuile (balise <ul> mal fermée).'
        );
        self::assertSame(
            $crawler->filter('[data-photo]')->count(),
            $crawler->filter('[data-photos] > [data-photo]')->count(),
            'Chaque tuile doit être un enfant direct de sa grille.'
        );
    }

    public function testPhotosAreGroupedByMonth(): void
    {
        /** @var GalleryPhoto[] $photos */
        $photos = $this->em->getRepository(GalleryPhoto::class)->findAll();

        $expectedMonths = [];
        foreach ($photos as $photo) {
            $expectedMonths[$photo->getSortDate()->format('Y-m')] = true;
        }

        $crawler = $this->client->request('GET', '/admin/photos');

        self::assertCount(
            \count($expectedMonths),
            $crawler->filter('[data-month]'),
            'Il doit y avoir une section par mois représenté.'
        );
    }

    public function testMonthsAreOrderedFromNewestToOldest(): void
    {
        $crawler = $this->client->request('GET', '/admin/photos');

        $months = $crawler->filter('[data-month]')->each(
            static fn ($node) => $node->attr('data-month')
        );

        $sorted = $months;
        rsort($sorted);

        self::assertSame($sorted, $months, 'Les mois doivent aller du plus récent au plus ancien.');
    }

    public function testWriteRoutesRejectMissingCsrfToken(): void
    {
        foreach (['/admin/photos/reorder', '/admin/photos/update', '/admin/photos/delete'] as $route) {
            $this->client->request('POST', $route);

            self::assertResponseStatusCodeSame(403, sprintf('%s doit exiger un jeton CSRF.', $route));
        }
    }
}
