<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Request\CreateScheduleRequest;
use App\Interface\Api\Meetings\Request\ReasonRequest;
use App\Interface\Api\Meetings\Request\ScheduleChangesRequest;
use App\Interface\Api\Meetings\Response\CanceledOccurrenceResponse;
use App\Interface\Api\Meetings\Response\WrittenScheduleResponse;
use App\Workflow\Meetings\CancelOccurrenceWorkflow;
use App\Workflow\Meetings\CreateScheduleWorkflow;
use App\Workflow\Meetings\EditScheduleWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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

    #[Route('/{id}', name: 'api_schedules_edit', methods: ['PUT'])]
    public function edit(
        string $id,
        #[MapRequestPayload]
        ScheduleChangesRequest $changes,
        EditScheduleWorkflow $editSchedule,
    ): JsonResponse {
        return $this->json(WrittenScheduleResponse::of($editSchedule($id, $changes->changes())));
    }

    #[Route('/{id}/occurrences/{date}/cancel', name: 'api_schedules_cancel_occurrence', methods: ['POST'])]
    public function cancelOccurrence(
        string $id,
        string $date,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ReasonRequest $request,
        CancelOccurrenceWorkflow $cancelOccurrence,
    ): JsonResponse {
        $canceled = $cancelOccurrence($id, $date, $user->getUserIdentifier(), $request->reason);

        return $this->json(CanceledOccurrenceResponse::of($canceled));
    }
}
