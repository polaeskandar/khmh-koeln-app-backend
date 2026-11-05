<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonRequest;
use App\Interface\Api\Learning\Response\WrittenLessonResponse;
use App\Workflow\Learning\CreateLessonWorkflow;
use App\Workflow\Learning\EditLessonWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class LessonsController extends AbstractController
{
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
}
