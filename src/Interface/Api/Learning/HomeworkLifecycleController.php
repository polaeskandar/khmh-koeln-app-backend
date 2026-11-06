<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Response\ChangedHomeworkResponse;
use App\Workflow\Learning\ArchiveHomeworkWorkflow;
use App\Workflow\Learning\DeleteHomeworkWorkflow;
use App\Workflow\Learning\RestoreHomeworkWorkflow;
use App\Workflow\Learning\UnpublishHomeworkWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/homework/{id}')]
final class HomeworkLifecycleController extends AbstractController
{
    #[Route('/unpublish', name: 'api_homework_unpublish', methods: ['POST'])]
    #[OA\Post(summary: 'Homework unpublish.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChangedHomeworkResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function unpublish(string $id, UnpublishHomeworkWorkflow $unpublishHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($unpublishHomework($id)));
    }

    #[Route('/archive', name: 'api_homework_archive', methods: ['POST'])]
    #[OA\Post(summary: 'Homework archive.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChangedHomeworkResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function archive(string $id, ArchiveHomeworkWorkflow $archiveHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($archiveHomework($id)));
    }

    #[Route('/restore', name: 'api_homework_restore', methods: ['POST'])]
    #[OA\Post(summary: 'Homework restore.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ChangedHomeworkResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function restore(string $id, RestoreHomeworkWorkflow $restoreHomework): JsonResponse
    {
        return $this->json(ChangedHomeworkResponse::of($restoreHomework($id)));
    }

    #[Route('', name: 'api_homework_delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Homework delete.')]
    #[OA\Response(response: 204, description: 'Done; no body.')]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function delete(string $id, DeleteHomeworkWorkflow $deleteHomework): Response
    {
        $deleteHomework($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
