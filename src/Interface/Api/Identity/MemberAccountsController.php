<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\MemberAccountListResponse;
use App\Workflow\Identity\ListMemberAccountsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(ListMemberAccountsWorkflow $listMemberAccounts): JsonResponse
    {
        return $this->json(MemberAccountListResponse::of($listMemberAccounts()));
    }
}
