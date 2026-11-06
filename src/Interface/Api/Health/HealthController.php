<?php

declare(strict_types=1);

namespace App\Interface\Api\Health;

use App\Interface\Api\Health\Response\LivenessResponse;
use App\Interface\Api\Health\Response\ReadinessResponse;
use App\Workflow\Health\CheckReadinessWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', methods: ['GET'])]
final class HealthController extends AbstractController
{
    #[Route('', name: 'health')]
    #[OA\Get(security: [], summary: 'Health.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LivenessResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function live(): JsonResponse
    {
        return $this->json(new LivenessResponse());
    }

    #[Route('/ready', name: 'health_ready')]
    #[OA\Get(security: [], summary: 'Health ready.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ReadinessResponse::class)),
    )]
    #[OA\Response(
        response: 503,
        description: 'Not ready.',
        content: new OA\JsonContent(ref: new Model(type: ReadinessResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function ready(CheckReadinessWorkflow $checkReadiness): JsonResponse
    {
        $readiness = $checkReadiness();
        $status = $readiness->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json(data: ReadinessResponse::of($readiness), status: $status);
    }
}
