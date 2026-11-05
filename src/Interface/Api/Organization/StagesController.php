<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Request\StageOrderRequest;
use App\Interface\Api\Organization\Request\StageRequest;
use App\Interface\Api\Organization\Response\StageListResponse;
use App\Interface\Api\Organization\Response\StageResponse;
use App\Workflow\Organization\AddStageWorkflow;
use App\Workflow\Organization\EditStageWorkflow;
use App\Workflow\Organization\ListStagesWorkflow;
use App\Workflow\Organization\ReorderStagesWorkflow;
use App\Workflow\Organization\ShowStageWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class StagesController extends AbstractController
{
    #[Route('/services/{id}/stages', name: 'api_stages_list', methods: ['GET'])]
    public function list(string $id, ListStagesWorkflow $listStages): JsonResponse
    {
        return $this->json(StageListResponse::of($listStages($id)));
    }

    #[Route('/services/{id}/stages/order', name: 'api_stages_reorder', methods: ['PUT'])]
    public function reorder(
        string $id,
        #[MapRequestPayload]
        StageOrderRequest $order,
        ReorderStagesWorkflow $reorderStages,
    ): JsonResponse {
        return $this->json(StageListResponse::of($reorderStages($id, $order->stageIds ?? [])));
    }

    #[Route('/services/{id}/stages', name: 'api_stages_add', methods: ['POST'])]
    public function add(string $id, #[MapRequestPayload] StageRequest $stage, AddStageWorkflow $addStage): JsonResponse
    {
        $added = $addStage($id, $stage->name ?? '', $stage->description);

        return $this->json(
            new StageResponse($added),
            status: Response::HTTP_CREATED,
            headers: ['Location' => $this->generateUrl('api_stages_show', ['id' => $added->stage->id])],
        );
    }

    #[Route('/stages/{id}', name: 'api_stages_show', methods: ['GET'])]
    public function show(string $id, ShowStageWorkflow $showStage): JsonResponse
    {
        return $this->json(new StageResponse($showStage($id)));
    }

    #[Route('/stages/{id}', name: 'api_stages_edit', methods: ['PUT'])]
    public function edit(
        string $id,
        #[MapRequestPayload]
        StageRequest $stage,
        EditStageWorkflow $editStage,
    ): JsonResponse {
        return $this->json(new StageResponse($editStage($id, $stage->name ?? '', $stage->description)));
    }
}
