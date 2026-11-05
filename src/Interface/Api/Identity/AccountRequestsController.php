<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\AccountRequestListResponse;
use App\Workflow\Identity\DeclineAccountRequestWorkflow;
use App\Workflow\Identity\ListAccountRequestsWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/account-requests')]
final class AccountRequestsController extends AbstractController
{
    #[Route('', name: 'api_account_requests_list', methods: ['GET'])]
    public function list(ListAccountRequestsWorkflow $listAccountRequests): JsonResponse
    {
        return $this->json(AccountRequestListResponse::of($listAccountRequests()));
    }

    #[Route(
        '/{id}/decline',
        name: 'api_account_requests_decline',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function decline(string $id, DeclineAccountRequestWorkflow $declineAccountRequest): Response
    {
        $declineAccountRequest($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
