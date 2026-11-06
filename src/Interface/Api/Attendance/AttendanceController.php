<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance;

use App\Interface\Api\Attendance\Request\RemovalRequest;
use App\Interface\Api\Attendance\Response\MarkResponse;
use App\Workflow\Attendance\MarkPresentWorkflow;
use App\Workflow\Attendance\RemoveMarkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/sessions/{sessionId}/attendance/{personId}')]
final class AttendanceController extends AbstractController
{
    #[Route('', name: 'api_attendance_mark', methods: ['PUT'])]
    public function mark(
        string $sessionId,
        string $personId,
        #[CurrentUser]
        UserInterface $user,
        MarkPresentWorkflow $markPresent,
    ): JsonResponse {
        $result = $markPresent($sessionId, $personId, $user->getUserIdentifier());

        return $this->json(
            MarkResponse::of($result->mark),
            status: $result->created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('', name: 'api_attendance_remove', methods: ['DELETE'])]
    public function remove(
        string $sessionId,
        string $personId,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        RemovalRequest $removal,
        RemoveMarkWorkflow $removeMark,
    ): Response {
        $removeMark($sessionId, $personId, $user->getUserIdentifier(), $removal->reason);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
