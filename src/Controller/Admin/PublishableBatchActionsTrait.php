<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batch actions pour entités exposant setIsPublished(bool).
 *
 * À utiliser dans un CRUD controller :
 *   - appeler addPublishableBatchActions($actions) dans configureActions()
 *   - les routes de batch (batchPublish, batchUnpublish) sont autocâblées
 */
trait PublishableBatchActionsTrait
{
    private function addPublishableBatchActions(Actions $actions): Actions
    {
        return $actions
            ->addBatchAction(
                Action::new('batchPublish', 'Publier', 'fa fa-eye')
                    ->linkToCrudAction('batchPublish')
                    ->addCssClass('btn btn-outline-success')
            )
            ->addBatchAction(
                Action::new('batchUnpublish', 'Dépublier', 'fa fa-eye-slash')
                    ->linkToCrudAction('batchUnpublish')
                    ->addCssClass('btn btn-outline-secondary')
            );
    }

    public function batchPublish(BatchActionDto $dto, EntityManagerInterface $em): Response
    {
        return $this->togglePublishedBatch($dto, $em, true);
    }

    public function batchUnpublish(BatchActionDto $dto, EntityManagerInterface $em): Response
    {
        return $this->togglePublishedBatch($dto, $em, false);
    }

    private function togglePublishedBatch(BatchActionDto $dto, EntityManagerInterface $em, bool $published): Response
    {
        $fqcn = $dto->getEntityFqcn();

        foreach ($dto->getEntityIds() as $id) {
            $entity = $em->find($fqcn, $id);
            if ($entity !== null && method_exists($entity, 'setIsPublished')) {
                $entity->setIsPublished($published);
            }
        }

        $em->flush();

        return $this->redirect($dto->getReferrerUrl());
    }
}
