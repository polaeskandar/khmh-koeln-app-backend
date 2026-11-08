<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\ChildHomeworkQueryRequest;
use App\Interface\Api\Learning\Response\ChildHomeworkListResponse;
use App\Interface\Api\Learning\Response\MemberHomeworkDetailResponse;
use App\Interface\Api\Learning\Response\ReportResultResponse;
use App\Workflow\Learning\ListMemberHomeworkWorkflow;
use App\Workflow\Learning\ReportOwnHomeworkWorkflow;
use App\Workflow\Learning\ShowMemberHomeworkWorkflow;
use App\Workflow\Learning\UndoOwnReportWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member/homework')]
final class MemberHomeworkController extends AbstractController
{
    #[Route('', name: 'api_member_homework_list', methods: ['GET'])]
    #[OA\Get(summary: 'Member homework list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChildHomeworkListResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(
        #[CurrentUser]
        UserInterface $viewer,
        ListMemberHomeworkWorkflow $listMemberHomework,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ChildHomeworkQueryRequest $query = new ChildHomeworkQueryRequest(),
    ): JsonResponse {
        $list = $listMemberHomework($viewer->getUserIdentifier(), $query->state);

        return $this->json(ChildHomeworkListResponse::of($list));
    }

    #[Route('/{homeworkId}', name: 'api_member_homework_show', methods: ['GET'])]
    #[OA\Get(summary: 'Member homework show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: MemberHomeworkDetailResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function show(
        string $homeworkId,
        #[CurrentUser]
        UserInterface $viewer,
        ShowMemberHomeworkWorkflow $showMemberHomework,
    ): JsonResponse {
        $detail = $showMemberHomework($viewer->getUserIdentifier(), $homeworkId);

        return $this->json(new MemberHomeworkDetailResponse($detail));
    }

    #[Route('/{homeworkId}/completion', name: 'api_member_homework_report', methods: ['PUT'])]
    #[OA\Put(summary: 'Member homework report.')]
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
        string $homeworkId,
        #[CurrentUser]
        UserInterface $viewer,
        ReportOwnHomeworkWorkflow $reportOwnHomework,
    ): JsonResponse {
        $result = $reportOwnHomework($viewer->getUserIdentifier(), $homeworkId);

        return $this->json(
            new ReportResultResponse($result),
            status: $result->created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('/{homeworkId}/completion', name: 'api_member_homework_undo', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Member homework undo.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function undo(
        string $homeworkId,
        #[CurrentUser]
        UserInterface $viewer,
        UndoOwnReportWorkflow $undoOwnReport,
    ): Response {
        $undoOwnReport($viewer->getUserIdentifier(), $homeworkId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
