<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\HomeworkRequest;
use App\Interface\Api\Learning\Response\ChangedHomeworkResponse;
use App\Interface\Api\Learning\Response\HomeworkDetailResponse;
use App\Interface\Api\Learning\Response\HomeworkListResponse;
use App\Interface\Api\Learning\Response\HomeworkProgressResponse;
use App\Interface\Api\Learning\Response\WrittenHomeworkResponse;
use App\Workflow\Learning\CreateHomeworkWorkflow;
use App\Workflow\Learning\EditHomeworkWorkflow;
use App\Workflow\Learning\ListHomeworkWorkflow;
use App\Workflow\Learning\PublishHomeworkWorkflow;
use App\Workflow\Learning\ShowHomeworkProgressWorkflow;
use App\Workflow\Learning\ShowHomeworkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeworkController extends AbstractController
{
    #[Route(
        '/api/stages/{stageId}/homework',
        name: 'api_homework_list',
        requirements: ['stageId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function list(string $stageId, ListHomeworkWorkflow $listHomework): JsonResponse
    {
        return $this->json(new HomeworkListResponse($listHomework($stageId)));
    }

    #[Route(
        '/api/lessons/{lessonId}/homework',
        name: 'api_homework_create',
        requirements: ['lessonId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function create(
        string $lessonId,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        HomeworkRequest $homework,
        CreateHomeworkWorkflow $createHomework,
    ): JsonResponse {
        $written = $createHomework($lessonId, $user->getUserIdentifier(), $homework->choice());

        return $this->json(WrittenHomeworkResponse::of($written), status: Response::HTTP_CREATED);
    }

    #[Route(
        '/api/homework/{id}',
        name: 'api_homework_show',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function show(string $id, ShowHomeworkWorkflow $showHomework): JsonResponse
    {
        return $this->json(new HomeworkDetailResponse($showHomework($id)));
    }

    #[Route(
        '/api/homework/{id}',
        name: 'api_homework_edit',
        requirements: ['id' => Requirement::UUID],
        methods: ['PATCH'],
    )]
    public function edit(
        string $id,
        #[MapRequestPayload]
        HomeworkRequest $homework,
        EditHomeworkWorkflow $editHomework,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?int $version = null,
    ): JsonResponse {
        return $this->json(WrittenHomeworkResponse::of($editHomework($id, $homework->choice(), $version)));
    }

    #[Route(
        '/api/homework/{id}/publish',
        name: 'api_homework_publish',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function publish(string $id, PublishHomeworkWorkflow $publishHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($publishHomework($id)));
    }

    #[Route(
        '/api/homework/{id}/progress',
        name: 'api_homework_progress',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function progress(string $id, ShowHomeworkProgressWorkflow $showHomeworkProgress): JsonResponse
    {
        return $this->json(new HomeworkProgressResponse($showHomeworkProgress($id)));
    }
}
