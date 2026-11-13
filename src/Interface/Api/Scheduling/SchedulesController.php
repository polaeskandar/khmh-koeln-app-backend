<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Response\DatedScheduleResponse;
use App\Interface\Api\Scheduling\Response\ScheduleListResponse;
use App\Workflow\Scheduling\ListSchedulesWorkflow;
use App\Workflow\Scheduling\ShowScheduleWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/schedules', methods: ['GET'])]
final class SchedulesController extends AbstractController
{
    #[Route('', name: 'api_schedules_list')]
    #[OA\Get(summary: 'Schedules list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ScheduleListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(
        ListSchedulesWorkflow $listSchedules,
        #[MapQueryParameter]
        ?string $serviceId = null,
        #[MapQueryParameter]
        ?string $stageId = null,
    ): JsonResponse {
        return $this->json(ScheduleListResponse::of($listSchedules($serviceId, $stageId)));
    }

    #[Route('/{id}', name: 'api_schedules_show')]
    #[OA\Get(summary: 'Schedules show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: DatedScheduleResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function show(string $id, ShowScheduleWorkflow $showSchedule): JsonResponse
    {
        return $this->json(DatedScheduleResponse::of($showSchedule($id)));
    }
}
