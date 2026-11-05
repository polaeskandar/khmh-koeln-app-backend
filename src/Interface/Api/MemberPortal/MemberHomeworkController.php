<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal;

use App\Interface\Api\MemberPortal\Response\MemberHomeworkDetailResponse;
use App\Interface\Api\ParentPortal\Request\ChildHomeworkQueryRequest;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkListResponse;
use App\Interface\Api\ParentPortal\Response\ReportResultResponse;
use App\Workflow\MemberPortal\ListMemberHomeworkWorkflow;
use App\Workflow\MemberPortal\ReportOwnHomeworkWorkflow;
use App\Workflow\MemberPortal\ShowMemberHomeworkWorkflow;
use App\Workflow\MemberPortal\UndoOwnReportWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/member/homework')]
final class MemberHomeworkController extends AbstractController
{
    #[Route('', name: 'api_member_homework_list', methods: ['GET'])]
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

    #[Route(
        '/{homeworkId}',
        name: 'api_member_homework_show',
        requirements: ['homeworkId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function show(
        string $homeworkId,
        #[CurrentUser]
        UserInterface $viewer,
        ShowMemberHomeworkWorkflow $showMemberHomework,
    ): JsonResponse {
        $detail = $showMemberHomework($viewer->getUserIdentifier(), $homeworkId);

        return $this->json(new MemberHomeworkDetailResponse($detail));
    }

    #[Route(
        '/{homeworkId}/completion',
        name: 'api_member_homework_report',
        requirements: ['homeworkId' => Requirement::UUID],
        methods: ['PUT'],
    )]
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

    #[Route(
        '/{homeworkId}/completion',
        name: 'api_member_homework_undo',
        requirements: ['homeworkId' => Requirement::UUID],
        methods: ['DELETE'],
    )]
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
