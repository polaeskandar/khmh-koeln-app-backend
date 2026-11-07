<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Request\AttendanceCountsRequest;
use App\Interface\Api\Attendance\Response\AttendanceCountsResponse;
use App\Interface\Api\Attendance\Response\AttendanceOverviewResponse;
use App\Workflow\Attendance\ShowAttendanceCountsWorkflow;
use App\Workflow\Attendance\ShowAttendanceOverviewWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/attendance')]
final class AttendanceOverviewController extends AbstractController
{
    #[Route('/overview', name: 'api_attendance_overview', methods: ['GET'])]
    public function show(ShowAttendanceOverviewWorkflow $showAttendanceOverview): JsonResponse
    {
        return $this->json(new AttendanceOverviewResponse($showAttendanceOverview()));
    }

    #[Route('/counts', name: 'api_attendance_counts', methods: ['GET'])]
    public function counts(
        ShowAttendanceCountsWorkflow $showAttendanceCounts,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        AttendanceCountsRequest $query = new AttendanceCountsRequest(),
    ): JsonResponse {
        return $this->json(new AttendanceCountsResponse($showAttendanceCounts($query->sessionIds)));
    }
}
