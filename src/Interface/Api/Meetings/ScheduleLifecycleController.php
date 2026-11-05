<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\WrittenScheduleResponse;
use App\Workflow\Meetings\ActivateScheduleWorkflow;
use App\Workflow\Meetings\DeactivateScheduleWorkflow;
use App\Workflow\Meetings\DeleteScheduleWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/schedules/{id}', requirements: ['id' => Requirement::UUID])]
final class ScheduleLifecycleController extends AbstractController
{
    #[Route('/activate', name: 'api_schedules_activate', methods: ['POST'])]
    public function activate(string $id, ActivateScheduleWorkflow $activateSchedule): JsonResponse
    {
        return $this->json(WrittenScheduleResponse::of($activateSchedule($id)));
    }

    #[Route('/deactivate', name: 'api_schedules_deactivate', methods: ['POST'])]
    public function deactivate(string $id, DeactivateScheduleWorkflow $deactivateSchedule): JsonResponse
    {
        return $this->json(['id' => $deactivateSchedule($id)]);
    }

    #[Route('', name: 'api_schedules_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteScheduleWorkflow $deleteSchedule): Response
    {
        $deleteSchedule($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
