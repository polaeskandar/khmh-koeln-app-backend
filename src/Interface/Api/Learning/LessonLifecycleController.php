<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Response\LessonArchiveResponse;
use App\Workflow\Learning\ArchiveLessonWorkflow;
use App\Workflow\Learning\DeleteLessonWorkflow;
use App\Workflow\Learning\RestoreLessonWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lessons/{id}')]
final class LessonLifecycleController extends AbstractController
{
    #[Route('/archive', name: 'api_lessons_archive', methods: ['POST'])]
    #[OA\Post(summary: 'Lessons archive.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LessonArchiveResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function archive(string $id, ArchiveLessonWorkflow $archiveLesson): JsonResponse
    {
        return $this->json(LessonArchiveResponse::of($archiveLesson($id)));
    }

    #[Route('/restore', name: 'api_lessons_restore', methods: ['POST'])]
    #[OA\Post(summary: 'Lessons restore.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['id', 'archivedAt', 'version'], properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'archivedAt', type: 'null'),
            new OA\Property(property: 'version', type: 'integer'),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function restore(string $id, RestoreLessonWorkflow $restoreLesson): JsonResponse
    {
        $restored = $restoreLesson($id);

        return $this->json(['id' => $restored->id, 'archivedAt' => null, 'version' => $restored->version]);
    }

    #[Route('', name: 'api_lessons_delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Lessons delete.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function delete(string $id, DeleteLessonWorkflow $deleteLesson): Response
    {
        $deleteLesson($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
