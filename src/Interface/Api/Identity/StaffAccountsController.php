<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\GiveAccountRoleRequest;
use App\Workflow\Identity\GiveAccountRoleWorkflow;
use App\Workflow\Identity\IssuePasswordLinkWorkflow;
use App\Workflow\Identity\ListStaffAccountsWorkflow;
use App\Workflow\Identity\TakeAccountRoleWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class StaffAccountsController extends AbstractController
{
    #[Route('/staff-accounts', name: 'api_staff_accounts', methods: ['GET'])]
    public function list(ListStaffAccountsWorkflow $listStaffAccounts): JsonResponse
    {
        return $this->json($listStaffAccounts());
    }

    #[Route(
        '/accounts/{id}/roles',
        name: 'api_account_roles_give',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function give(
        string $id,
        #[MapRequestPayload]
        GiveAccountRoleRequest $request,
        GiveAccountRoleWorkflow $giveAccountRole,
    ): JsonResponse {
        return $this->json(['role' => $giveAccountRole($id, $request->roleId ?? '')], status: Response::HTTP_CREATED);
    }

    #[Route(
        '/accounts/{id}/roles/{roleId}',
        name: 'api_account_roles_take',
        requirements: ['id' => Requirement::UUID, 'roleId' => Requirement::UUID],
        methods: ['DELETE'],
    )]
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

    #[Route(
        '/accounts/{id}/password-link',
        name: 'api_account_password_link',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function passwordLink(
        string $id,
        #[CurrentUser]
        UserInterface $actor,
        IssuePasswordLinkWorkflow $issuePasswordLink,
    ): JsonResponse {
        return $this->json($issuePasswordLink($id, $actor->getUserIdentifier()), status: Response::HTTP_CREATED);
    }
}
