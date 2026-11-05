<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\MemberAccountListResponse;
use App\Workflow\Identity\ListMemberAccountsWorkflow;
use App\Workflow\Identity\RemoveMemberLinkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberAccountsController extends AbstractController
{
    #[Route('/api/member-accounts', name: 'api_member_accounts_list', methods: ['GET'])]
    public function list(ListMemberAccountsWorkflow $listMemberAccounts): JsonResponse
    {
        return $this->json(MemberAccountListResponse::of($listMemberAccounts()));
    }

    #[Route('/api/member-accounts/{id}/unlink', name: 'api_member_accounts_unlink', methods: ['POST'])]
    public function unlink(string $id, RemoveMemberLinkWorkflow $removeMemberLink): Response
    {
        $removeMemberLink($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
