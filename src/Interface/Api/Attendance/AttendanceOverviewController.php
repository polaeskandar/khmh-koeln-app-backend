<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Response\AttendanceOverviewResponse;
use App\Workflow\Attendance\ShowAttendanceOverviewWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AttendanceOverviewController extends AbstractController
{
    #[Route('/api/attendance/overview', name: 'api_attendance_overview', methods: ['GET'])]
    public function show(ShowAttendanceOverviewWorkflow $showAttendanceOverview): JsonResponse
    {
        return $this->json(new AttendanceOverviewResponse($showAttendanceOverview()));
    }
}
