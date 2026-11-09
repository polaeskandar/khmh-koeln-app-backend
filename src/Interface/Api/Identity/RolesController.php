<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity;

use App\Interface\Api\Identity\Request\RoleRequest;
use App\Interface\Api\Identity\Response\RoleDetailsResponse;
use App\Interface\Api\Identity\Response\RoleListResponse;
use App\Workflow\Identity\CreateRoleWorkflow;
use App\Workflow\Identity\DeleteRoleWorkflow;
use App\Workflow\Identity\EditRoleWorkflow;
use App\Workflow\Identity\ListRolesWorkflow;
use App\Workflow\Identity\ShowRoleWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/roles')]
final class RolesController extends AbstractController
{
    #[Route('', name: 'api_roles_list', methods: ['GET'])]
    #[OA\Get(summary: 'Roles list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: RoleListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(ListRolesWorkflow $listRoles): JsonResponse
    {
        return $this->json(RoleListResponse::of($listRoles()));
    }

    #[Route('/{id}', name: 'api_roles_show', methods: ['GET'])]
    #[OA\Get(summary: 'Roles show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: RoleDetailsResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(string $id, ShowRoleWorkflow $showRole): JsonResponse
    {
        return $this->json(new RoleDetailsResponse($showRole($id)));
    }

    #[Route('', name: 'api_roles_create', methods: ['POST'])]
    #[OA\Post(summary: 'Roles create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(required: ['id'], properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function create(#[MapRequestPayload] RoleRequest $role, CreateRoleWorkflow $createRole): JsonResponse
    {
        $id = $createRole($role->name ?? '', $role->permissionChoices());

        return $this->json(
            ['id' => $id],
            status: Response::HTTP_CREATED,
            headers: ['Location' => $this->generateUrl('api_roles_show', ['id' => $id])],
        );
    }

    #[Route('/{id}', name: 'api_roles_edit', methods: ['PUT'])]
    #[OA\Put(summary: 'Roles edit.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: RoleDetailsResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function edit(string $id, #[MapRequestPayload] RoleRequest $role, EditRoleWorkflow $editRole): JsonResponse
    {
        return $this->json(new RoleDetailsResponse($editRole($id, $role->name ?? '', $role->permissionChoices())));
    }

    #[Route('/{id}', name: 'api_roles_delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Roles delete.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function delete(string $id, DeleteRoleWorkflow $deleteRole): Response
    {
        $deleteRole($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
