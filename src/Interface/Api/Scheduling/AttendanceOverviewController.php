<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Request\AttendanceCountsRequest;
use App\Interface\Api\Scheduling\Response\AttendanceCountsResponse;
use App\Interface\Api\Scheduling\Response\AttendanceOverviewResponse;
use App\Workflow\Scheduling\ShowAttendanceCountsWorkflow;
use App\Workflow\Scheduling\ShowAttendanceOverviewWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/attendance')]
final class AttendanceOverviewController extends AbstractController
{
    #[Route('/overview', name: 'api_attendance_overview', methods: ['GET'])]
    #[OA\Get(summary: 'Attendance overview.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AttendanceOverviewResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function show(ShowAttendanceOverviewWorkflow $showAttendanceOverview): JsonResponse
    {
        return $this->json(new AttendanceOverviewResponse($showAttendanceOverview()));
    }

    #[Route('/counts', name: 'api_attendance_counts', methods: ['GET'])]
    #[OA\Get(summary: 'Attendance counts.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AttendanceCountsResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function counts(
        ShowAttendanceCountsWorkflow $showAttendanceCounts,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        AttendanceCountsRequest $query = new AttendanceCountsRequest(),
    ): JsonResponse {
        return $this->json(new AttendanceCountsResponse($showAttendanceCounts($query->sessionIds)));
    }
}
