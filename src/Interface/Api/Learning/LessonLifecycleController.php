<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Response\LessonArchiveResponse;
use App\Workflow\Learning\ArchiveLessonWorkflow;
use App\Workflow\Learning\DeleteLessonWorkflow;
use App\Workflow\Learning\RestoreLessonWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lessons/{id}')]
final class LessonLifecycleController extends AbstractController
{
    #[Route('/archive', name: 'api_lessons_archive', methods: ['POST'])]
    public function archive(string $id, ArchiveLessonWorkflow $archiveLesson): JsonResponse
    {
        return $this->json(LessonArchiveResponse::of($archiveLesson($id)));
    }

    #[Route('/restore', name: 'api_lessons_restore', methods: ['POST'])]
    public function restore(string $id, RestoreLessonWorkflow $restoreLesson): JsonResponse
    {
        return $this->json(['id' => $restoreLesson($id), 'archivedAt' => null]);
    }

    #[Route('', name: 'api_lessons_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteLessonWorkflow $deleteLesson): Response
    {
        $deleteLesson($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
