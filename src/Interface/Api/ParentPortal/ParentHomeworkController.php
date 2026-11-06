<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal;

use App\Interface\Api\ParentPortal\Request\ChildHomeworkQueryRequest;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkDetailResponse;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkListResponse;
use App\Interface\Api\ParentPortal\Response\ReportResultResponse;
use App\Workflow\ParentPortal\ListChildHomeworkWorkflow;
use App\Workflow\ParentPortal\ReportHomeworkDoneWorkflow;
use App\Workflow\ParentPortal\ShowChildHomeworkWorkflow;
use App\Workflow\ParentPortal\UndoHomeworkReportWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/parent/children/{childId}/homework')]
final class ParentHomeworkController extends AbstractController
{
    #[Route('', name: 'api_parent_homework_list', methods: ['GET'])]
    #[OA\Get(summary: 'Parent homework list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChildHomeworkListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(
        string $childId,
        #[CurrentUser]
        UserInterface $user,
        ListChildHomeworkWorkflow $listChildHomework,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ChildHomeworkQueryRequest $query = new ChildHomeworkQueryRequest(),
    ): JsonResponse {
        $list = $listChildHomework($user->getUserIdentifier(), $childId, $query->state);

        return $this->json(ChildHomeworkListResponse::of($list));
    }

    #[Route('/{homeworkId}', name: 'api_parent_homework_show', methods: ['GET'])]
    #[OA\Get(summary: 'Parent homework show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChildHomeworkDetailResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(
        string $childId,
        string $homeworkId,
        #[CurrentUser]
        UserInterface $user,
        ShowChildHomeworkWorkflow $showChildHomework,
    ): JsonResponse {
        $detail = $showChildHomework($user->getUserIdentifier(), $childId, $homeworkId);

        return $this->json(new ChildHomeworkDetailResponse($detail));
    }

    #[Route('/{homeworkId}/completion', name: 'api_parent_homework_report', methods: ['PUT'])]
    #[OA\Put(summary: 'Parent homework report.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ReportResultResponse::class)),
    )]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: ReportResultResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function report(
        string $childId,
        string $homeworkId,
        #[CurrentUser]
        UserInterface $user,
        ReportHomeworkDoneWorkflow $reportHomeworkDone,
    ): JsonResponse {
        $result = $reportHomeworkDone($user->getUserIdentifier(), $childId, $homeworkId);

        return $this->json(
            new ReportResultResponse($result),
            status: $result->created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('/{homeworkId}/completion', name: 'api_parent_homework_undo', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Parent homework undo.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function undo(
        string $childId,
        string $homeworkId,
        #[CurrentUser]
        UserInterface $user,
        UndoHomeworkReportWorkflow $undoHomeworkReport,
    ): Response {
        $undoHomeworkReport($user->getUserIdentifier(), $childId, $homeworkId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
