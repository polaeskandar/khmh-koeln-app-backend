<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Response\AttendanceBoardResponse;
use App\Interface\Api\Attendance\Response\AttendanceCorrectionsResponse;
use App\Workflow\Attendance\ListAttendanceCorrectionsWorkflow;
use App\Workflow\Attendance\ShowAttendanceBoardWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sessions/{id}/attendance')]
final class AttendanceBoardController extends AbstractController
{
    #[Route('', name: 'api_attendance_board', methods: ['GET'])]
    public function show(string $id, ShowAttendanceBoardWorkflow $showAttendanceBoard): JsonResponse
    {
        return $this->json(new AttendanceBoardResponse($showAttendanceBoard($id)));
    }

    #[Route('/corrections', name: 'api_attendance_corrections', methods: ['GET'])]
    public function corrections(string $id, ListAttendanceCorrectionsWorkflow $listCorrections): JsonResponse
    {
        return $this->json(new AttendanceCorrectionsResponse($listCorrections($id)));
    }
}
