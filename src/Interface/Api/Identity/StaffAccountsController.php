<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\GiveAccountRoleRequest;
use App\Interface\Api\Identity\Response\RoleResponse;
use App\Interface\Api\Identity\Response\StaffAccountListResponse;
use App\Workflow\Identity\GiveAccountRoleWorkflow;
use App\Workflow\Identity\ListStaffAccountsWorkflow;
use App\Workflow\Identity\Model\RoleSummary;
use App\Workflow\Identity\TakeAccountRoleWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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

    #[Route('/accounts/{id}/roles', name: 'api_account_roles_give', methods: ['POST'])]
    #[OA\Post(summary: 'Account roles give.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(required: ['role'], properties: [
            new OA\Property(property: 'role', ref: new Model(type: RoleSummary::class)),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function give(
        string $id,
        #[MapRequestPayload]
        GiveAccountRoleRequest $request,
        GiveAccountRoleWorkflow $giveAccountRole,
    ): JsonResponse {
        $role = RoleResponse::of($giveAccountRole($id, $request->roleId ?? ''));

        return $this->json(['role' => $role], status: Response::HTTP_CREATED);
    }

    #[Route('/accounts/{id}/roles/{roleId}', name: 'api_account_roles_take', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Account roles take.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function take(
        string $id,
        string $roleId,
        #[CurrentUser]
        UserInterface $actor,
        TakeAccountRoleWorkflow $takeAccountRole,
    ): Response {
        $takeAccountRole($id, $roleId, $actor->getUserIdentifier());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
