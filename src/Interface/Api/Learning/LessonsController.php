<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonListQueryRequest;
use App\Interface\Api\Learning\Request\LessonRequest;
use App\Interface\Api\Learning\Request\LessonStatusRequest;
use App\Interface\Api\Learning\Response\LessonListResponse;
use App\Interface\Api\Learning\Response\LessonStatusResponse;
use App\Interface\Api\Learning\Response\WrittenLessonResponse;
use App\Workflow\Learning\ChangeLessonStatusWorkflow;
use App\Workflow\Learning\CreateLessonWorkflow;
use App\Workflow\Learning\EditLessonWorkflow;
use App\Workflow\Learning\ListLessonsWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class LessonsController extends AbstractController
{
    #[Route(
        '/api/stages/{stageId}/lessons',
        name: 'api_lessons_list',
        requirements: ['stageId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function list(
        string $stageId,
        ListLessonsWorkflow $listLessons,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LessonListQueryRequest $query = new LessonListQueryRequest(),
    ): JsonResponse {
        return $this->json(new LessonListResponse($listLessons($stageId, $query->tab, $query->q)));
    }

    #[Route(
        '/api/stages/{stageId}/lessons',
        name: 'api_lessons_create',
        requirements: ['stageId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function create(
        string $stageId,
        #[MapRequestPayload]
        LessonRequest $lesson,
        CreateLessonWorkflow $createLesson,
    ): JsonResponse {
        $written = $createLesson($stageId, $lesson->lesson());

        return $this->json(WrittenLessonResponse::of($written), status: Response::HTTP_CREATED);
    }

    #[Route(
        '/api/lessons/{id}',
        name: 'api_lessons_edit',
        requirements: ['id' => Requirement::UUID],
        methods: ['PATCH'],
    )]
    public function edit(
        string $id,
        #[MapRequestPayload]
        LessonRequest $lesson,
        EditLessonWorkflow $editLesson,
    ): JsonResponse {
        return $this->json(WrittenLessonResponse::of($editLesson($id, $lesson->lesson())));
    }

    #[Route(
        '/api/lessons/{id}/status',
        name: 'api_lessons_status',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function changeStatus(
        string $id,
        #[MapRequestPayload]
        LessonStatusRequest $request,
        ChangeLessonStatusWorkflow $changeLessonStatus,
    ): JsonResponse {
        return $this->json(LessonStatusResponse::of($changeLessonStatus($id, $request->status ?? '')));
    }
}
