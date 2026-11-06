<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Response\AttendanceBoardResponse;
use App\Interface\Api\Attendance\Response\AttendanceCorrectionsResponse;
use App\Workflow\Attendance\ListAttendanceCorrectionsWorkflow;
use App\Workflow\Attendance\ShowAttendanceBoardWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sessions/{id}/attendance')]
final class AttendanceBoardController extends AbstractController
{
    #[Route('', name: 'api_attendance_board', methods: ['GET'])]
    #[OA\Get(summary: 'Attendance board.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AttendanceBoardResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(string $id, ShowAttendanceBoardWorkflow $showAttendanceBoard): JsonResponse
    {
        return $this->json(new AttendanceBoardResponse($showAttendanceBoard($id)));
    }

    #[Route('/corrections', name: 'api_attendance_corrections', methods: ['GET'])]
    #[OA\Get(summary: 'Attendance corrections.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AttendanceCorrectionsResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function corrections(string $id, ListAttendanceCorrectionsWorkflow $listCorrections): JsonResponse
    {
        return $this->json(new AttendanceCorrectionsResponse($listCorrections($id)));
    }
}
