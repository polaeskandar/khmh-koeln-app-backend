<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\DatedScheduleResponse;
use App\Interface\Api\Meetings\Response\ScheduleListResponse;
use App\Workflow\Meetings\ListSchedulesWorkflow;
use App\Workflow\Meetings\ShowScheduleWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/schedules', methods: ['GET'])]
final class SchedulesController extends AbstractController
{
    #[Route('', name: 'api_schedules_list')]
    public function list(
        ListSchedulesWorkflow $listSchedules,
        #[MapQueryParameter]
        ?string $serviceId = null,
        #[MapQueryParameter]
        ?string $stageId = null,
    ): JsonResponse {
        return $this->json(ScheduleListResponse::of($listSchedules($serviceId, $stageId)));
    }

    #[Route('/{id}', name: 'api_schedules_show', requirements: ['id' => Requirement::UUID])]
    public function show(string $id, ShowScheduleWorkflow $showSchedule): JsonResponse
    {
        return $this->json(DatedScheduleResponse::of($showSchedule($id)));
    }
}
