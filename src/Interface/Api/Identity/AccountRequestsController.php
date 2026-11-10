<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\ApprovalRequest;
use App\Interface\Api\Identity\Response\AccountRequestListResponse;
use App\Interface\Api\Identity\Response\MemberCandidateListResponse;
use App\Workflow\Identity\ApproveAccountRequestWorkflow;
use App\Workflow\Identity\ApproveMemberRequestWorkflow;
use App\Workflow\Identity\DeclineAccountRequestWorkflow;
use App\Workflow\Identity\ListAccountRequestsWorkflow;
use App\Workflow\Identity\ListMemberCandidatesWorkflow;
use App\Workflow\Identity\Model\AccountSummary;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/account-requests')]
final class AccountRequestsController extends AbstractController
{
    #[Route('', name: 'api_account_requests_list', methods: ['GET'])]
    #[OA\Get(summary: 'Account requests list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AccountRequestListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(ListAccountRequestsWorkflow $listAccountRequests): JsonResponse
    {
        return $this->json(AccountRequestListResponse::of($listAccountRequests()));
    }

    #[Route('/member-candidates', name: 'api_account_requests_member_candidates', methods: ['GET'])]
    #[OA\Get(summary: 'Account requests member candidates.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberCandidateListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function memberCandidates(
        ListMemberCandidatesWorkflow $listMemberCandidates,
        #[MapQueryParameter]
        string $q = '',
    ): JsonResponse {
        return $this->json(MemberCandidateListResponse::of($listMemberCandidates($q)));
    }

    #[Route('/{id}/approve', name: 'api_account_requests_approve', methods: ['POST'])]
    #[OA\Post(summary: 'Account requests approve.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AccountSummary::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function approve(
        string $id,
        #[CurrentUser]
        UserInterface $actor,
        #[MapRequestPayload]
        ApprovalRequest $approval,
        ApproveAccountRequestWorkflow $approveAccountRequest,
        ApproveMemberRequestWorkflow $approveMemberRequest,
    ): JsonResponse {
        $actorId = $actor->getUserIdentifier();

        if ($approval->isMember()) {
            return $this->json($approveMemberRequest($id, $actorId, $approval->memberApproval()));
        }

        return $this->json($approveAccountRequest($id, $actorId, $approval->choice()));
    }

    #[Route('/{id}/decline', name: 'api_account_requests_decline', methods: ['POST'])]
    #[OA\Post(summary: 'Account requests decline.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function decline(string $id, DeclineAccountRequestWorkflow $declineAccountRequest): Response
    {
        $declineAccountRequest($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
