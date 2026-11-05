<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal;

use App\Interface\Api\MemberPortal\Response\MemberGroupListResponse;
use App\Interface\Api\MemberPortal\Response\MemberMeetingListResponse;
use App\Workflow\MemberPortal\ListMemberGroupsWorkflow;
use App\Workflow\MemberPortal\ListMemberMeetingsWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member')]
final class MemberPortalController extends AbstractController
{
    #[Route('/groups', name: 'api_member_groups', methods: ['GET'])]
    public function groups(#[CurrentUser] UserInterface $viewer, ListMemberGroupsWorkflow $listGroups): JsonResponse
    {
        return $this->json(MemberGroupListResponse::of($listGroups($viewer->getUserIdentifier())));
    }

    #[Route('/meetings', name: 'api_member_meetings', methods: ['GET'])]
    public function meetings(
        #[CurrentUser]
        UserInterface $viewer,
        ListMemberMeetingsWorkflow $listMeetings,
    ): JsonResponse {
        return $this->json(MemberMeetingListResponse::of($listMeetings($viewer->getUserIdentifier())));
    }
}
