<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les pages du back-office qui ne sont pas des CRUD EasyAdmin doivent
 * fonctionner en accès direct : mise en favori, rechargement, retour arrière,
 * lien collé dans un message. S'appuyer sur les paramètres d'URL ajoutés par
 * le menu les rendrait cassables au premier F5.
 */
final class CustomAdminPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pageProvider(): iterable
    {
        yield 'galerie' => ['/admin/photos'];
        yield 'réglages' => ['/admin/reglages'];
        yield 'builder de carte' => ['/admin/menu/builder'];
    }

    #[DataProvider('pageProvider')]
    public function testPageLoadsWhenOpenedDirectly(string $url): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = $em->getRepository(User::class)->findOneBy([]);

        if ($admin === null) {
            self::markTestSkipped('Aucun compte administrateur en base.');
        }

        $client->loginUser($admin);
        $client->request('GET', $url);

        self::assertResponseIsSuccessful(sprintf('%s doit répondre en accès direct.', $url));
    }
}
