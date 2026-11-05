<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning;

use App\Interface\Api\Learning\Request\HomeworkRequest;
use App\Interface\Api\Learning\Response\WrittenHomeworkResponse;
use App\Workflow\Learning\CreateHomeworkWorkflow;
use App\Workflow\Learning\EditHomeworkWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeworkController extends AbstractController
{
    #[Route(
        '/api/lessons/{lessonId}/homework',
        name: 'api_homework_create',
        requirements: ['lessonId' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function create(
        string $lessonId,
        #[CurrentUser]
        UserInterface $user,
        #[MapRequestPayload]
        HomeworkRequest $homework,
        CreateHomeworkWorkflow $createHomework,
    ): JsonResponse {
        $written = $createHomework($lessonId, $user->getUserIdentifier(), $homework->choice());

        return $this->json(WrittenHomeworkResponse::of($written), status: Response::HTTP_CREATED);
    }

    #[Route(
        '/api/homework/{id}',
        name: 'api_homework_edit',
        requirements: ['id' => Requirement::UUID],
        methods: ['PATCH'],
    )]
    public function edit(
        string $id,
        #[MapRequestPayload]
        HomeworkRequest $homework,
        EditHomeworkWorkflow $editHomework,
    ): JsonResponse {
        return $this->json(WrittenHomeworkResponse::of($editHomework($id, $homework->choice())));
    }
}
