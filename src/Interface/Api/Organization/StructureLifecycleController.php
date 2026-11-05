<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Response\ServiceResponse;
use App\Interface\Api\Organization\Response\StageResponse;
use App\Workflow\Organization\ArchiveServiceWorkflow;
use App\Workflow\Organization\ArchiveStageWorkflow;
use App\Workflow\Organization\DeleteServiceWorkflow;
use App\Workflow\Organization\DeleteStageWorkflow;
use App\Workflow\Organization\RestoreServiceWorkflow;
use App\Workflow\Organization\RestoreStageWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class StructureLifecycleController extends AbstractController
{
    #[Route('/services/{id}/archive', name: 'api_services_archive', methods: ['POST'])]
    public function archiveService(string $id, ArchiveServiceWorkflow $archiveService): JsonResponse
    {
        return $this->json(new ServiceResponse($archiveService($id)));
    }

    #[Route('/services/{id}/restore', name: 'api_services_restore', methods: ['POST'])]
    public function restoreService(string $id, RestoreServiceWorkflow $restoreService): JsonResponse
    {
        return $this->json(new ServiceResponse($restoreService($id)));
    }

    #[Route('/services/{id}', name: 'api_services_delete', methods: ['DELETE'])]
    public function deleteService(string $id, DeleteServiceWorkflow $deleteService): Response
    {
        $deleteService($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/stages/{id}/archive', name: 'api_stages_archive', methods: ['POST'])]
    public function archiveStage(string $id, ArchiveStageWorkflow $archiveStage): JsonResponse
    {
        return $this->json(new StageResponse($archiveStage($id)));
    }

    #[Route('/stages/{id}/restore', name: 'api_stages_restore', methods: ['POST'])]
    public function restoreStage(string $id, RestoreStageWorkflow $restoreStage): JsonResponse
    {
        return $this->json(new StageResponse($restoreStage($id)));
    }

    #[Route('/stages/{id}', name: 'api_stages_delete', methods: ['DELETE'])]
    public function deleteStage(string $id, DeleteStageWorkflow $deleteStage): Response
    {
        $deleteStage($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
