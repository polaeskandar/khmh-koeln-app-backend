<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Request\MoveMembershipRequest;
use App\Interface\Api\People\Request\PlacementRequest;
use App\Interface\Api\People\Response\AddedMembershipResponse;
use App\Workflow\People\AddMembershipWorkflow;
use App\Workflow\People\EndMembershipWorkflow;
use App\Workflow\People\MoveMembershipWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class MembershipsController extends AbstractController
{
    #[Route('/api/people/{id}/memberships', name: 'api_people_memberships_add', methods: ['POST'])]
    #[OA\Post(summary: 'People memberships add.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: AddedMembershipResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function add(
        string $id,
        #[MapRequestPayload]
        PlacementRequest $placement,
        AddMembershipWorkflow $addMembership,
    ): JsonResponse {
        return $this->json(
            new AddedMembershipResponse($addMembership($id, $placement->choice())),
            status: Response::HTTP_CREATED,
        );
    }

    #[Route('/api/memberships/{id}/move', name: 'api_memberships_move', methods: ['POST'])]
    #[OA\Post(summary: 'Memberships move.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AddedMembershipResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function move(
        string $id,
        #[MapRequestPayload]
        MoveMembershipRequest $request,
        MoveMembershipWorkflow $moveMembership,
    ): JsonResponse {
        return $this->json(new AddedMembershipResponse($moveMembership($id, $request->stageId)));
    }

    #[Route('/api/memberships/{id}/end', name: 'api_memberships_end', methods: ['POST'])]
    #[OA\Post(summary: 'Memberships end.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function end(string $id, EndMembershipWorkflow $endMembership): Response
    {
        $endMembership($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
