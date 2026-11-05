<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\GiveAccountRoleRequest;
use App\Interface\Api\Identity\Response\PasswordLinkResponse;
use App\Interface\Api\Identity\Response\RoleResponse;
use App\Interface\Api\Identity\Response\StaffAccountListResponse;
use App\Workflow\Identity\GiveAccountRoleWorkflow;
use App\Workflow\Identity\IssuePasswordLinkWorkflow;
use App\Workflow\Identity\ListStaffAccountsWorkflow;
use App\Workflow\Identity\TakeAccountRoleWorkflow;
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
    public function list(ListStaffAccountsWorkflow $listStaffAccounts): JsonResponse
    {
        return $this->json(StaffAccountListResponse::of($listStaffAccounts()));
    }

    #[Route('/accounts/{id}/roles', name: 'api_account_roles_give', methods: ['POST'])]
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

    #[Route('/accounts/{id}/password-link', name: 'api_account_password_link', methods: ['POST'])]
    public function passwordLink(
        string $id,
        #[CurrentUser]
        UserInterface $actor,
        IssuePasswordLinkWorkflow $issuePasswordLink,
    ): JsonResponse {
        return $this->json(
            PasswordLinkResponse::of($issuePasswordLink($id, $actor->getUserIdentifier())),
            status: Response::HTTP_CREATED,
        );
    }
}
