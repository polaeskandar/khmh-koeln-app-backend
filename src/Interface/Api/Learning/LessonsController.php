<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonListQueryRequest;
use App\Interface\Api\Learning\Request\LessonRequest;
use App\Interface\Api\Learning\Response\LessonListResponse;
use App\Interface\Api\Learning\Response\WrittenLessonResponse;
use App\Workflow\Learning\CreateLessonWorkflow;
use App\Workflow\Learning\EditLessonWorkflow;
use App\Workflow\Learning\ListLessonsWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class LessonsController extends AbstractController
{
    #[Route('/api/stages/{stageId}/lessons', name: 'api_lessons_list', methods: ['GET'])]
    public function list(
        string $stageId,
        ListLessonsWorkflow $listLessons,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LessonListQueryRequest $query = new LessonListQueryRequest(),
    ): JsonResponse {
        return $this->json(new LessonListResponse($listLessons($stageId, $query->tab, $query->q)));
    }

    #[Route('/api/stages/{stageId}/lessons', name: 'api_lessons_create', methods: ['POST'])]
    public function create(
        string $stageId,
        #[MapRequestPayload]
        LessonRequest $lesson,
        CreateLessonWorkflow $createLesson,
    ): JsonResponse {
        $written = $createLesson($stageId, $lesson->lesson());

        return $this->json(WrittenLessonResponse::of($written), status: Response::HTTP_CREATED);
    }

    #[Route('/api/lessons/{id}', name: 'api_lessons_edit', methods: ['PUT'])]
    public function edit(
        string $id,
        #[MapRequestPayload]
        LessonRequest $lesson,
        EditLessonWorkflow $editLesson,
    ): JsonResponse {
        return $this->json(WrittenLessonResponse::of($editLesson($id, $lesson->lesson())));
    }
}
