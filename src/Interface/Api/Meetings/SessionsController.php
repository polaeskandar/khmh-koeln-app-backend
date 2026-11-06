<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Request\ReasonRequest;
use App\Interface\Api\Meetings\Response\SessionDetailResponse;
use App\Interface\Api\Meetings\Response\SessionStateResponse;
use App\Workflow\Meetings\CancelSessionWorkflow;
use App\Workflow\Meetings\CloseSessionWorkflow;
use App\Workflow\Meetings\OpenSessionWorkflow;
use App\Workflow\Meetings\ReopenSessionWorkflow;
use App\Workflow\Meetings\ShowSessionWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/sessions')]
final class SessionsController extends AbstractController
{
    #[Route('/{id}', name: 'api_sessions_show', methods: ['GET'])]
    #[OA\Get(summary: 'Sessions show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: SessionDetailResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(string $id, ShowSessionWorkflow $showSession): JsonResponse
    {
        return $this->json(SessionDetailResponse::of($showSession($id)));
    }

    #[Route('/{id}/open', name: 'api_sessions_open', methods: ['POST'])]
    #[OA\Post(summary: 'Sessions open.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: SessionStateResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function open(string $id, #[CurrentUser] UserInterface $user, OpenSessionWorkflow $openSession): JsonResponse
    {
        return $this->json(SessionStateResponse::of($openSession($id, $user->getUserIdentifier())));
    }

    #[Route('/{id}/close', name: 'api_sessions_close', methods: ['POST'])]
    #[OA\Post(summary: 'Sessions close.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: SessionStateResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function close(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        CloseSessionWorkflow $closeSession,
    ): JsonResponse {
        return $this->json(SessionStateResponse::of($closeSession($id, $user->getUserIdentifier())));
    }

    #[Route('/{id}/reopen', name: 'api_sessions_reopen', methods: ['POST'])]
    #[OA\Post(summary: 'Sessions reopen.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: SessionStateResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function reopen(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ReasonRequest $request,
        ReopenSessionWorkflow $reopenSession,
    ): JsonResponse {
        return $this->json(SessionStateResponse::of($reopenSession($id, $user->getUserIdentifier(), $request->reason)));
    }

    #[Route('/{id}/cancel', name: 'api_sessions_cancel', methods: ['POST'])]
    #[OA\Post(summary: 'Sessions cancel.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: SessionStateResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function cancel(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ReasonRequest $request,
        CancelSessionWorkflow $cancelSession,
    ): JsonResponse {
        return $this->json(SessionStateResponse::of($cancelSession($id, $user->getUserIdentifier(), $request->reason)));
    }
}
