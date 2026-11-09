<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Request\ParentAccessRequest;
use App\Interface\Api\People\Response\ParentAccessItemResponse;
use App\Interface\Api\People\Response\ParentAccessListResponse;
use App\Workflow\People\LinkGuardianWorkflow;
use App\Workflow\People\ListGuardianLinksWorkflow;
use App\Workflow\People\RevokeGuardianLinkWorkflow;
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
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(ListGuardianLinksWorkflow $listGuardianLinks): JsonResponse
    {
        return $this->json(ParentAccessListResponse::of($listGuardianLinks()));
    }

    #[Route('', name: 'api_guardian_links_give', methods: ['POST'])]
    #[OA\Post(summary: 'Guardian links give.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: ParentAccessItemResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function give(
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        ParentAccessRequest $request,
        LinkGuardianWorkflow $linkGuardian,
    ): JsonResponse {
        $item = $linkGuardian(
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
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function revoke(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        RevokeGuardianLinkWorkflow $revokeGuardianLink,
    ): Response {
        $revokeGuardianLink($id, $user->getUserIdentifier());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
