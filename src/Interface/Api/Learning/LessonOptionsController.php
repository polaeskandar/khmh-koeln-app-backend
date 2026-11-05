<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonOptionsQueryRequest;
use App\Interface\Api\Learning\Response\LessonOptionsResponse;
use App\Workflow\Learning\ListLessonOptionsWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class LessonOptionsController extends AbstractController
{
    #[Route(
        '/api/stages/{stageId}/lesson-options',
        name: 'api_lesson_options',
        requirements: ['stageId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function list(
        string $stageId,
        ListLessonOptionsWorkflow $listLessonOptions,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LessonOptionsQueryRequest $query = new LessonOptionsQueryRequest(),
    ): JsonResponse {
        return $this->json(LessonOptionsResponse::of($listLessonOptions($stageId, $query->lessonId)));
    }
}
