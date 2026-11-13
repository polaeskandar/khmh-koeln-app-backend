<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling;

use App\Interface\Api\Scheduling\Request\OneOffSessionRequest;
use App\Interface\Api\Scheduling\Request\SessionDetailsRequest;
use App\Workflow\Scheduling\CreateOneOffSessionWorkflow;
use App\Workflow\Scheduling\RenameSessionWorkflow;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/sessions')]
final class SessionWritesController extends AbstractController
{
    #[Route('', name: 'api_sessions_create', methods: ['POST'])]
    #[OA\Post(summary: 'Sessions create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(required: ['id'], properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function create(
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        OneOffSessionRequest $request,
        CreateOneOffSessionWorkflow $createOneOffSession,
    ): JsonResponse {
        $id = $createOneOffSession($request->oneOff(), $user->getUserIdentifier());

        return $this->json(['id' => $id], status: Response::HTTP_CREATED);
    }

    #[Route('/{id}/details', name: 'api_sessions_details', methods: ['PUT'])]
    #[OA\Put(summary: 'Session details.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['id'], properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function rename(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        SessionDetailsRequest $request,
        RenameSessionWorkflow $renameSession,
    ): JsonResponse {
        $renamed = $renameSession($id, $user->getUserIdentifier(), $request->title, $request->location);

        return $this->json(['id' => $renamed]);
    }
}
