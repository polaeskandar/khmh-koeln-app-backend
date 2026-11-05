<?php

declare(strict_types=1);

namespace App\Interface\Api\Health;

use App\Interface\Api\Health\Response\LivenessResponse;
use App\Interface\Api\Health\Response\ReadinessResponse;
use App\Workflow\Health\CheckReadinessWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', methods: ['GET'])]
final class HealthController extends AbstractController
{
    #[Route('', name: 'health')]
    public function live(): JsonResponse
    {
        return $this->json(new LivenessResponse());
    }

    #[Route('/ready', name: 'health_ready')]
    public function ready(CheckReadinessWorkflow $checkReadiness): JsonResponse
    {
        $readiness = $checkReadiness();
        $status = $readiness->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json(data: ReadinessResponse::of($readiness), status: $status);
    }
}
