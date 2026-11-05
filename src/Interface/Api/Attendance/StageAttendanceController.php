<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Response\AttendanceHistoryResponse;
use App\Workflow\Attendance\ShowStageAttendanceHistoryWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StageAttendanceController extends AbstractController
{
    #[Route('/api/stages/{id}/attendance-history', name: 'api_stages_attendance_history', methods: ['GET'])]
    public function history(string $id, ShowStageAttendanceHistoryWorkflow $showStageAttendanceHistory): JsonResponse
    {
        return $this->json(AttendanceHistoryResponse::of($showStageAttendanceHistory($id)));
    }
}
