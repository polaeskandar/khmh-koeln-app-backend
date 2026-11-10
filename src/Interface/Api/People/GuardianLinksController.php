<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Request\ParentAccessRequest;
use App\Interface\Api\People\Response\ParentAccessItemResponse;
use App\Interface\Api\People\Response\ParentAccessListResponse;
use App\Workflow\People\GiveParentAccessWorkflow;
use App\Workflow\People\ListParentAccessWorkflow;
use App\Workflow\People\RevokeParentAccessWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/guardian-links')]
final class GuardianLinksController extends AbstractController
{
    #[Route('', name: 'api_guardian_links_list', methods: ['GET'])]
    #[OA\Get(summary: 'Guardian links list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ParentAccessListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(ListParentAccessWorkflow $listParentAccess): JsonResponse
    {
        return $this->json(ParentAccessListResponse::of($listParentAccess()));
    }

    #[Route('', name: 'api_guardian_links_give', methods: ['POST'])]
    #[OA\Post(summary: 'Guardian links give.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: ParentAccessItemResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function give(
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ParentAccessRequest $request,
        GiveParentAccessWorkflow $giveParentAccess,
    ): JsonResponse {
        $item = $giveParentAccess(
            $request->userId ?? '',
            $request->personId ?? '',
            $request->relationship,
            $user->getUserIdentifier(),
        );

        return $this->json(new ParentAccessItemResponse($item), status: Response::HTTP_CREATED);
    }

    #[Route('/{id}/revoke', name: 'api_guardian_links_revoke', methods: ['POST'])]
    #[OA\Post(summary: 'Guardian links revoke.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function revoke(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        RevokeParentAccessWorkflow $revokeParentAccess,
    ): Response {
        $revokeParentAccess($id, $user->getUserIdentifier());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
