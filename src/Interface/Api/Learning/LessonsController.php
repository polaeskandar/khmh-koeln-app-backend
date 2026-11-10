<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\LessonListQueryRequest;
use App\Interface\Api\Learning\Request\LessonRequest;
use App\Interface\Api\Learning\Request\LessonStatusRequest;
use App\Interface\Api\Learning\Response\LessonDetailResponse;
use App\Interface\Api\Learning\Response\LessonListResponse;
use App\Interface\Api\Learning\Response\LessonStatusResponse;
use App\Interface\Api\Learning\Response\WrittenLessonResponse;
use App\Workflow\Learning\ChangeLessonStatusWorkflow;
use App\Workflow\Learning\CreateLessonWorkflow;
use App\Workflow\Learning\EditLessonWorkflow;
use App\Workflow\Learning\ListLessonsWorkflow;
use App\Workflow\Learning\ShowLessonWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class LessonsController extends AbstractController
{
    #[Route('/api/stages/{stageId}/lessons', name: 'api_lessons_list', methods: ['GET'])]
    #[OA\Get(summary: 'Lessons list.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LessonListResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function list(
        string $stageId,
        ListLessonsWorkflow $listLessons,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        LessonListQueryRequest $query = new LessonListQueryRequest(),
    ): JsonResponse {
        return $this->json(new LessonListResponse($listLessons($stageId, $query->tab, $query->q)));
    }

    #[Route('/api/stages/{stageId}/lessons', name: 'api_lessons_create', methods: ['POST'])]
    #[OA\Post(summary: 'Lessons create.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: WrittenLessonResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function create(
        string $stageId,
        #[MapRequestPayload]
        LessonRequest $lesson,
        CreateLessonWorkflow $createLesson,
    ): JsonResponse {
        $written = $createLesson($stageId, $lesson->lesson());

        return $this->json(WrittenLessonResponse::of($written), status: Response::HTTP_CREATED);
    }

    #[Route('/api/lessons/{id}', name: 'api_lessons_show', methods: ['GET'])]
    #[OA\Get(summary: 'Lessons show.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LessonDetailResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function show(string $id, ShowLessonWorkflow $showLesson): JsonResponse
    {
        return $this->json(new LessonDetailResponse($showLesson($id)));
    }

    #[Route('/api/lessons/{id}', name: 'api_lessons_edit', methods: ['PUT'])]
    #[OA\Put(summary: 'Lessons edit.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: WrittenLessonResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function edit(
        string $id,
        #[MapRequestPayload]
        LessonRequest $lesson,
        EditLessonWorkflow $editLesson,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?int $version = null,
    ): JsonResponse {
        return $this->json(WrittenLessonResponse::of($editLesson($id, $lesson->lesson(), $version)));
    }

    #[Route('/api/lessons/{id}/status', name: 'api_lessons_status', methods: ['POST'])]
    #[OA\Post(summary: 'Lessons status.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LessonStatusResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function changeStatus(
        string $id,
        #[MapRequestPayload]
        LessonStatusRequest $request,
        ChangeLessonStatusWorkflow $changeLessonStatus,
    ): JsonResponse {
        return $this->json(LessonStatusResponse::of($changeLessonStatus($id, $request->status ?? '')));
    }
}
