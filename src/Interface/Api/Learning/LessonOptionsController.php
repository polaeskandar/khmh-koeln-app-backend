<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonOptionsQueryRequest;
use App\Interface\Api\Learning\Response\LessonOptionsResponse;
use App\Workflow\Learning\ListLessonOptionsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class LessonOptionsController extends AbstractController
{
    #[Route('/api/stages/{stageId}/lesson-options', name: 'api_lesson_options', methods: ['GET'])]
    #[OA\Get(summary: 'Lesson options.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LessonOptionsResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function list(
        string $stageId,
        ListLessonOptionsWorkflow $listLessonOptions,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LessonOptionsQueryRequest $query = new LessonOptionsQueryRequest(),
    ): JsonResponse {
        return $this->json(LessonOptionsResponse::of($listLessonOptions($stageId, $query->lessonId)));
    }
}
