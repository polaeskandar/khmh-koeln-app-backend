<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\MemberMeetingListResponse;
use App\Workflow\Meetings\ListMemberMeetingsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member')]
final class MemberMeetingsController extends AbstractController
{
    #[Route('/meetings', name: 'api_member_meetings', methods: ['GET'])]
    #[OA\Get(summary: 'Member meetings.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberMeetingListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(#[CurrentUser] UserInterface $user, ListMemberMeetingsWorkflow $listMeetings): JsonResponse
    {
        return $this->json(MemberMeetingListResponse::of($listMeetings($user->getUserIdentifier())));
    }
}
