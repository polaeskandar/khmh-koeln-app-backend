<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Response\MemberGroupListResponse;
use App\Workflow\People\ListMemberGroupsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member')]
final class MemberGroupsController extends AbstractController
{
    #[Route('/groups', name: 'api_member_groups', methods: ['GET'])]
    #[OA\Get(summary: 'Member groups.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberGroupListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function groups(#[CurrentUser] UserInterface $viewer, ListMemberGroupsWorkflow $listGroups): JsonResponse
    {
        return $this->json(MemberGroupListResponse::of($listGroups($viewer->getUserIdentifier())));
    }
}
