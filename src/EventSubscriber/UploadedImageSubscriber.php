<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Event;
use App\Entity\Partner;
use App\Entity\ShopCategory;
use App\Entity\SiteImage;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Fait passer par le convertisseur toute image déposée dans un écran EasyAdmin.
 *
 * La galerie a son propre écran et appelle ImageProcessor directement ; ce
 * relais couvre les autres champs images du back-office, pour qu'une photo de
 * téléphone de 9 Mo ne se retrouve pas telle quelle sur le site — et qu'un PDF
 * déposé à la place d'une image devienne quand même une image.
 */
final class UploadedImageSubscriber implements EventSubscriberInterface
{
    /**
     * Champs de fichier par entité, avec le dossier où ils sont stockés.
     *
     * @var array<class-string, array{dir: string, fields: string[]}>
     */
    private const WATCHED = [
        Event::class => [
            'dir' => 'events',
            'fields' => ['imageFileName'],
        ],
        ShopCategory::class => [
            'dir' => 'shop',
            'fields' => ['imageFileName'],
        ],
        SiteImage::class => [
            'dir' => 'site',
            'fields' => ['fileName'],
        ],
        Partner::class => [
            'dir' => 'partners',
            'fields' => ['logoFileName', 'heroImageFileName', 'image2FileName', 'image3FileName'],
        ],
    ];

    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'onPersisted',
            AfterEntityUpdatedEvent::class => 'onUpdated',
        ];
    }

    public function onPersisted(AfterEntityPersistedEvent $event): void
    {
        $this->handle($event->getEntityInstance());
    }

    public function onUpdated(AfterEntityUpdatedEvent $event): void
    {
        $this->handle($event->getEntityInstance());
    }

    private function handle(mixed $entity): void
    {
        $config = self::WATCHED[$entity::class] ?? null;

        if ($config === null) {
            return;
        }

        $changed = false;

        foreach ($config['fields'] as $field) {
            $getter = 'get' . ucfirst($field);
            $setter = 'set' . ucfirst($field);

            if (!method_exists($entity, $getter) || !method_exists($entity, $setter)) {
                continue;
            }

            $fileName = $entity->$getter();

            if (!\is_string($fileName) || $fileName === '') {
                continue;
            }

            $directory = sprintf('%s/public/uploads/%s', $this->projectDir, $config['dir']);
            $path = $directory . '/' . $fileName;

            if (!is_file($path)) {
                continue;
            }

            try {
                $processed = basename($this->processor->process($path));
            } catch (\RuntimeException $exception) {
                // Le formulaire est déjà validé et l'entité enregistrée : on ne
                // peut plus refuser la saisie, on prévient et on laisse le
                // fichier d'origine en place.
                $this->logger->warning('Image non convertie', [
                    'entity' => $entity::class,
                    'field' => $field,
                    'file' => $fileName,
                    'error' => $exception->getMessage(),
                ]);

                $this->addFlash('warning', sprintf('« %s » : %s', $fileName, $exception->getMessage()));

                continue;
            }

            if ($processed !== $fileName) {
                $entity->$setter($processed);
                $changed = true;
            }
        }

        if ($changed) {
            $this->em->flush();
        }
    }

    private function addFlash(string $type, string $message): void
    {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }
}
