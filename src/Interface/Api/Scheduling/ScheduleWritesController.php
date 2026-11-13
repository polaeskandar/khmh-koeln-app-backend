<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Request\CreateScheduleRequest;
use App\Interface\Api\Scheduling\Request\ReasonRequest;
use App\Interface\Api\Scheduling\Request\ScheduleChangesRequest;
use App\Interface\Api\Scheduling\Response\CanceledOccurrenceResponse;
use App\Interface\Api\Scheduling\Response\WrittenScheduleResponse;
use App\Workflow\Scheduling\CancelOccurrenceWorkflow;
use App\Workflow\Scheduling\CreateScheduleWorkflow;
use App\Workflow\Scheduling\EditScheduleWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Post(summary: 'Schedules create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: WrittenScheduleResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
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
    #[OA\Put(summary: 'Schedules edit.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: WrittenScheduleResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function edit(
        string $id,
        #[MapRequestPayload]
        ScheduleChangesRequest $changes,
        EditScheduleWorkflow $editSchedule,
    ): JsonResponse {
        return $this->json(WrittenScheduleResponse::of($editSchedule($id, $changes->changes())));
    }

    #[Route('/{id}/occurrences/{date}/cancel', name: 'api_schedules_cancel_occurrence', methods: ['POST'])]
    #[OA\Post(summary: 'Schedules cancel occurrence.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: CanceledOccurrenceResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
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
