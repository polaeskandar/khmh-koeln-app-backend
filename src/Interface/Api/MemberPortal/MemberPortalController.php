<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal;

use App\Interface\Api\MemberPortal\Response\MemberGroupListResponse;
use App\Interface\Api\MemberPortal\Response\MemberMeetingListResponse;
use App\Workflow\MemberPortal\ListMemberGroupsWorkflow;
use App\Workflow\MemberPortal\ListMemberMeetingsWorkflow;
use App\Workflow\MemberPortal\ListMemberServantsWorkflow;
use App\Workflow\MemberPortal\Model\GroupServant;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member')]
final class MemberPortalController extends AbstractController
{
    #[Route('/groups', name: 'api_member_groups', methods: ['GET'])]
    #[OA\Get(summary: 'Member groups.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberGroupListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function groups(#[CurrentUser] UserInterface $viewer, ListMemberGroupsWorkflow $listGroups): JsonResponse
    {
        return $this->json(MemberGroupListResponse::of($listGroups($viewer->getUserIdentifier())));
    }

    #[Route('/servants', name: 'api_member_servants', methods: ['GET'])]
    #[OA\Get(summary: 'Member servants.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['items'], properties: [
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(ref: new Model(type: GroupServant::class)),
            ),
        ]),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function servants(
        #[CurrentUser]
        UserInterface $viewer,
        ListMemberServantsWorkflow $listServants,
    ): JsonResponse {
        return $this->json(['items' => $listServants($viewer->getUserIdentifier())]);
    }

    #[Route('/meetings', name: 'api_member_meetings', methods: ['GET'])]
    #[OA\Get(summary: 'Member meetings.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberMeetingListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function meetings(
        #[CurrentUser]
        UserInterface $viewer,
        ListMemberMeetingsWorkflow $listMeetings,
    ): JsonResponse {
        return $this->json(MemberMeetingListResponse::of($listMeetings($viewer->getUserIdentifier())));
    }
}
