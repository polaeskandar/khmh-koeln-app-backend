<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\SessionDetailResponse;
use App\Workflow\Meetings\ShowSessionWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/sessions', requirements: ['id' => Requirement::UUID])]
final class SessionsController extends AbstractController
{
    #[Route('/{id}', name: 'api_sessions_show', methods: ['GET'])]
    public function show(string $id, ShowSessionWorkflow $showSession): JsonResponse
    {
        return $this->json(SessionDetailResponse::of($showSession($id)));
    }
}
