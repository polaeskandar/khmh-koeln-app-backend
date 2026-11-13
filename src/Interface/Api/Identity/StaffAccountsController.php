<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Response\StaffAccountListResponse;
use App\Workflow\Identity\ListStaffAccountsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class StaffAccountsController extends AbstractController
{
    #[Route('/staff-accounts', name: 'api_staff_accounts', methods: ['GET'])]
    #[OA\Get(summary: 'Staff accounts.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: StaffAccountListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(ListStaffAccountsWorkflow $listStaffAccounts): JsonResponse
    {
        return $this->json(StaffAccountListResponse::of($listStaffAccounts()));
    }
}
