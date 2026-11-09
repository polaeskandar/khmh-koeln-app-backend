<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\GiveAccountRoleRequest;
use App\Interface\Api\Identity\Response\PasswordLinkResponse;
use App\Interface\Api\Identity\Response\RoleResponse;
use App\Interface\Api\Identity\Response\StaffAccountListResponse;
use App\Workflow\Identity\GiveRoleWorkflow;
use App\Workflow\Identity\ListStaffAccountsWorkflow;
use App\Workflow\Identity\Model\IssuedPasswordLink;
use App\Workflow\Identity\Model\RoleSummary;
use App\Workflow\Identity\Model\StaffAccountList;
use App\Workflow\Identity\SendPasswordLinkWorkflow;
use App\Workflow\Identity\TakeRoleWorkflow;
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
        content: new OA\JsonContent(ref: new Model(type: StaffAccountList::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
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
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function give(
        string $id,
        #[MapRequestPayload]
        GiveAccountRoleRequest $request,
        GiveRoleWorkflow $giveRole,
    ): JsonResponse {
        $role = RoleResponse::of($giveRole($id, $request->roleId ?? ''));

        return $this->json(['role' => $role], status: Response::HTTP_CREATED);
    }

    #[Route('/accounts/{id}/roles/{roleId}', name: 'api_account_roles_take', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Account roles take.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function take(
        string $id,
        string $roleId,
        #[CurrentUser]
        UserInterface $user,
        TakeRoleWorkflow $takeRole,
    ): Response {
        $takeRole($id, $roleId, $user->getUserIdentifier());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/accounts/{id}/password-link', name: 'api_account_password_link', methods: ['POST'])]
    #[OA\Post(summary: 'Account password link.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: IssuedPasswordLink::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function passwordLink(
        string $id,
        #[CurrentUser]
        UserInterface $user,
        SendPasswordLinkWorkflow $sendPasswordLink,
    ): JsonResponse {
        return $this->json(
            PasswordLinkResponse::of($sendPasswordLink($id, $user->getUserIdentifier())),
            status: Response::HTTP_CREATED,
        );
    }
}
