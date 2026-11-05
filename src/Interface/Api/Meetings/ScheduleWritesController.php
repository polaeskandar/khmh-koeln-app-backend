<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Request\CreateScheduleRequest;
use App\Interface\Api\Meetings\Request\ScheduleChangesRequest;
use App\Interface\Api\Meetings\Response\WrittenScheduleResponse;
use App\Workflow\Meetings\CreateScheduleWorkflow;
use App\Workflow\Meetings\EditScheduleWorkflow;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/schedules')]
final class ScheduleWritesController extends AbstractController
{
    #[Route('', name: 'api_schedules_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload]
        CreateScheduleRequest $schedule,
        CreateScheduleWorkflow $createSchedule,
    ): JsonResponse {
        $written = $createSchedule($schedule->schedule());

        return $this->json(WrittenScheduleResponse::of($written), status: Response::HTTP_CREATED, headers: [
            'Location' => $this->generateUrl('api_schedules_show', ['id' => $written->id]),
        ]);
    }

    #[Route('/{id}', name: 'api_schedules_edit', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function edit(
        string $id,
        Request $request,
        #[MapRequestPayload]
        ScheduleChangesRequest $changes,
        EditScheduleWorkflow $editSchedule,
    ): JsonResponse {
        $endTimeSent = new ArrayCollection($request->getPayload()->all('times'))->containsKey('endTime');

        return $this->json(WrittenScheduleResponse::of($editSchedule($id, $changes->changes($endTimeSent))));
    }
}
