<?php

declare(strict_types=1);

namespace App\Interface\Api\Health;

use App\Interface\Application\Health\Model\Readiness;
use App\Interface\Application\Health\UseCase\Query\CheckReadiness\CheckReadinessHandler;
use App\Interface\Application\Health\UseCase\Query\CheckReadiness\CheckReadinessQuery;
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
        return $this->json(['status' => Readiness::OK]);
    }

    #[Route('/ready', name: 'health_ready')]
    public function ready(CheckReadinessHandler $checkReadiness): JsonResponse
    {
        $readiness = $checkReadiness(new CheckReadinessQuery());

        return $this->json(data: $readiness, status: $this->getStatusCode($readiness));
    }

    public function getStatusCode(Readiness $readiness): int
    {
        return $readiness->status === Readiness::OK ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
