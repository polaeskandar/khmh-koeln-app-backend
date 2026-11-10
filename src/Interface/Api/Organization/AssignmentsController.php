<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Request\AssignmentRequest;
use App\Workflow\Organization\AssignStaffWorkflow;
use App\Workflow\Organization\Model\AssignmentView;
use App\Workflow\Organization\RemoveAssignmentWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class AssignmentsController extends AbstractController
{
    #[Route('/accounts/{id}/assignments', name: 'api_account_assignments_add', methods: ['POST'])]
    #[OA\Post(summary: 'Account assignments add.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: AssignmentView::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function add(
        string $id,
        #[MapRequestPayload]
        AssignmentRequest $assignment,
        AssignStaffWorkflow $assignStaff,
    ): JsonResponse {
        return $this->json(
            $assignStaff($id, $assignment->serviceId, $assignment->stageId),
            status: Response::HTTP_CREATED,
        );
    }

    #[Route('/assignments/{id}', name: 'api_assignments_remove', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Assignments remove.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function remove(string $id, RemoveAssignmentWorkflow $removeAssignment): Response
    {
        $removeAssignment($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
