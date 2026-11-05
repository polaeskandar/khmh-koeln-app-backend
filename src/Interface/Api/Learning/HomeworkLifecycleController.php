<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Response\ChangedHomeworkResponse;
use App\Workflow\Learning\ArchiveHomeworkWorkflow;
use App\Workflow\Learning\DeleteHomeworkWorkflow;
use App\Workflow\Learning\RestoreHomeworkWorkflow;
use App\Workflow\Learning\UnpublishHomeworkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/homework/{id}', requirements: ['id' => Requirement::UUID])]
final class HomeworkLifecycleController extends AbstractController
{
    #[Route('/unpublish', name: 'api_homework_unpublish', methods: ['POST'])]
    public function unpublish(string $id, UnpublishHomeworkWorkflow $unpublishHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($unpublishHomework($id)));
    }

    #[Route('/archive', name: 'api_homework_archive', methods: ['POST'])]
    public function archive(string $id, ArchiveHomeworkWorkflow $archiveHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($archiveHomework($id)));
    }

    #[Route('/restore', name: 'api_homework_restore', methods: ['POST'])]
    public function restore(string $id, RestoreHomeworkWorkflow $restoreHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($restoreHomework($id)));
    }

    #[Route('', name: 'api_homework_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteHomeworkWorkflow $deleteHomework): Response
    {
        $deleteHomework($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
