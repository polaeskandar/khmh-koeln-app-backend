<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal;

use App\Interface\Api\ParentPortal\Request\ChildHomeworkQueryRequest;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkDetailResponse;
use App\Interface\Api\ParentPortal\Response\ChildHomeworkListResponse;
use App\Workflow\ParentPortal\ListChildHomeworkWorkflow;
use App\Workflow\ParentPortal\ShowChildHomeworkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/parent/children/{childId}/homework', requirements: ['childId' => Requirement::UUID], methods: ['GET'])]
final class ParentHomeworkController extends AbstractController
{
    #[Route('', name: 'api_parent_homework_list')]
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

    #[Route('/{homeworkId}', name: 'api_parent_homework_show', requirements: ['homeworkId' => Requirement::UUID])]
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
}
