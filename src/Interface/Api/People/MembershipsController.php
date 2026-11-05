<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Request\MoveMembershipRequest;
use App\Interface\Api\People\Request\PlacementRequest;
use App\Interface\Api\People\Response\AddedMembershipResponse;
use App\Workflow\People\AddMembershipWorkflow;
use App\Workflow\People\EndMembershipWorkflow;
use App\Workflow\People\MoveMembershipWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class MembershipsController extends AbstractController
{
    #[Route(
        '/api/people/{id}/memberships',
        name: 'api_people_memberships_add',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
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

    #[Route(
        '/api/memberships/{id}/move',
        name: 'api_memberships_move',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function move(
        string $id,
        #[MapRequestPayload]
        MoveMembershipRequest $request,
        MoveMembershipWorkflow $moveMembership,
    ): JsonResponse {
        return $this->json(new AddedMembershipResponse($moveMembership($id, $request->stageId)));
    }

    #[Route(
        '/api/memberships/{id}/end',
        name: 'api_memberships_end',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function end(string $id, EndMembershipWorkflow $endMembership): Response
    {
        $endMembership($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
