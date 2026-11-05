<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal;

use App\Interface\Api\MemberPortal\Response\MemberHomeworkDetailResponse;
use App\Interface\Api\ParentPortal\Request\ChildHomeworkQueryRequest;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkListResponse;
use App\Workflow\MemberPortal\ListMemberHomeworkWorkflow;
use App\Workflow\MemberPortal\ShowMemberHomeworkWorkflow;
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
    public function show(
        string $homeworkId,
        #[CurrentUser]
        UserInterface $viewer,
        ShowMemberHomeworkWorkflow $showMemberHomework,
    ): JsonResponse {
        $detail = $showMemberHomework($viewer->getUserIdentifier(), $homeworkId);

        return $this->json(new MemberHomeworkDetailResponse($detail));
    }
}
