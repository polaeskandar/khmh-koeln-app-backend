<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Response\AttendanceBoardResponse;
use App\Workflow\Attendance\ShowAttendanceBoardWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class AttendanceBoardController extends AbstractController
{
    #[Route(
        '/api/sessions/{id}/attendance',
        name: 'api_attendance_board',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function show(string $id, ShowAttendanceBoardWorkflow $showAttendanceBoard): JsonResponse
    {
        return $this->json(new AttendanceBoardResponse($showAttendanceBoard($id)));
    }
}
