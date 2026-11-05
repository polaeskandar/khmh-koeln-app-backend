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
use App\Workflow\Organization\ShowServiceWorkflow;
use App\Workflow\Organization\ShowStageWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api', requirements: ['id' => Requirement::UUID])]
final class StructureLifecycleController extends AbstractController
{
    public function __construct(
        private readonly ShowServiceWorkflow $showService,
        private readonly ShowStageWorkflow $showStage,
    ) {
    }

    #[Route('/services/{id}/archive', name: 'api_services_archive', methods: ['POST'])]
    public function archiveService(string $id, ArchiveServiceWorkflow $archiveService): JsonResponse
    {
        $archiveService($id);

        return $this->json(new ServiceResponse(($this->showService)($id)));
    }

    #[Route('/services/{id}/restore', name: 'api_services_restore', methods: ['POST'])]
    public function restoreService(string $id, RestoreServiceWorkflow $restoreService): JsonResponse
    {
        $restoreService($id);

        return $this->json(new ServiceResponse(($this->showService)($id)));
    }

    #[Route('/stages/{id}/archive', name: 'api_stages_archive', methods: ['POST'])]
    public function archiveStage(string $id, ArchiveStageWorkflow $archiveStage): JsonResponse
    {
        $archiveStage($id);

        return $this->json(new StageResponse(($this->showStage)($id)));
    }

    #[Route('/services/{id}', name: 'api_services_delete', methods: ['DELETE'])]
    public function deleteService(string $id, DeleteServiceWorkflow $deleteService): Response
    {
        $deleteService($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/stages/{id}', name: 'api_stages_delete', methods: ['DELETE'])]
    public function deleteStage(string $id, DeleteStageWorkflow $deleteStage): Response
    {
        $deleteStage($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/stages/{id}/restore', name: 'api_stages_restore', methods: ['POST'])]
    public function restoreStage(string $id, RestoreStageWorkflow $restoreStage): JsonResponse
    {
        $restoreStage($id);

        return $this->json(new StageResponse(($this->showStage)($id)));
    }
}
