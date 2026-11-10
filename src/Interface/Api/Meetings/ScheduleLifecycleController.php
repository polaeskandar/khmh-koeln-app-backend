<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\WrittenScheduleResponse;
use App\Workflow\Meetings\ActivateScheduleWorkflow;
use App\Workflow\Meetings\DeactivateScheduleWorkflow;
use App\Workflow\Meetings\DeleteScheduleWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/schedules/{id}')]
final class ScheduleLifecycleController extends AbstractController
{
    #[Route('/activate', name: 'api_schedules_activate', methods: ['POST'])]
    #[OA\Post(summary: 'Schedules activate.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: WrittenScheduleResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function activate(string $id, ActivateScheduleWorkflow $activateSchedule): JsonResponse
    {
        return $this->json(WrittenScheduleResponse::of($activateSchedule($id)));
    }

    #[Route('/deactivate', name: 'api_schedules_deactivate', methods: ['POST'])]
    #[OA\Post(summary: 'Schedules deactivate.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['id'], properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function deactivate(string $id, DeactivateScheduleWorkflow $deactivateSchedule): JsonResponse
    {
        return $this->json(['id' => $deactivateSchedule($id)]);
    }

    #[Route('', name: 'api_schedules_delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Schedules delete.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function delete(string $id, DeleteScheduleWorkflow $deleteSchedule): Response
    {
        $deleteSchedule($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
