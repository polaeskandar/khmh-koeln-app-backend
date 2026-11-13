<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Response\AttendanceHistoryResponse;
use App\Workflow\Scheduling\Model\AttendanceHistory;
use App\Workflow\Scheduling\ShowStageAttendanceHistoryWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StageAttendanceController extends AbstractController
{
    #[Route('/api/stages/{id}/attendance-history', name: 'api_stages_attendance_history', methods: ['GET'])]
    #[OA\Get(summary: 'Stages attendance history.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AttendanceHistory::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function history(string $id, ShowStageAttendanceHistoryWorkflow $showStageAttendanceHistory): JsonResponse
    {
        return $this->json(AttendanceHistoryResponse::of($showStageAttendanceHistory($id)));
    }
}
