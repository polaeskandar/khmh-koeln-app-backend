<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Request\OneOffSessionRequest;
use App\Interface\Api\Meetings\Request\SessionDetailsRequest;
use App\Workflow\Meetings\CreateOneOffSessionWorkflow;
use App\Workflow\Meetings\RenameSessionWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/sessions')]
final class SessionWritesController extends AbstractController
{
    #[Route('', name: 'api_sessions_create', methods: ['POST'])]
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

    #[Route('/{id}', name: 'api_sessions_rename', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
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
