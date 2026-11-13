<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Request\RemovalRequest;
use App\Interface\Api\Scheduling\Response\MarkResponse;
use App\Workflow\Scheduling\MarkPresentWorkflow;
use App\Workflow\Scheduling\RemoveMarkWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Put(summary: 'Attendance mark.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MarkResponse::class)),
    )]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: MarkResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
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
    #[OA\Delete(summary: 'Attendance remove.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
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
