<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Workflow\Attendance\ShowStageAttendanceHistoryWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class StageAttendanceController extends AbstractController
{
    #[Route(
        '/api/stages/{id}/attendance-history',
        name: 'api_stages_attendance_history',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function history(string $id, ShowStageAttendanceHistoryWorkflow $showStageAttendanceHistory): JsonResponse
    {
        return $this->json($showStageAttendanceHistory($id));
    }
}
