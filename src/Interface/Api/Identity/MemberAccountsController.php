<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\MemberAccountListResponse;
use App\Workflow\Identity\ListMemberAccountsWorkflow;
use App\Workflow\Identity\UnlinkMemberWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberAccountsController extends AbstractController
{
    #[Route('/api/member-accounts', name: 'api_member_accounts_list', methods: ['GET'])]
    #[OA\Get(summary: 'Member accounts list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberAccountListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(ListMemberAccountsWorkflow $listMemberAccounts): JsonResponse
    {
        return $this->json(MemberAccountListResponse::of($listMemberAccounts()));
    }

    #[Route('/api/member-accounts/{id}/unlink', name: 'api_member_accounts_unlink', methods: ['POST'])]
    #[OA\Post(summary: 'Member accounts unlink.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function unlink(string $id, UnlinkMemberWorkflow $unlinkMember): Response
    {
        $unlinkMember($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
