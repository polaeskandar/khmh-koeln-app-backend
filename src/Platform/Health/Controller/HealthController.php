<?php

declare(strict_types=1);

namespace App\Platform\Health\Controller;

use App\Platform\Health\Contract\DatabaseProbe;
use App\Platform\Health\Model\Readiness;
use App\Platform\Health\Response\LivenessResponse;
use App\Platform\Health\Response\ReadinessResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', methods: ['GET'])]
final class HealthController extends AbstractController
{
    private const int TIMEOUT_SECONDS = 2;

    #[Route('', name: 'health')]
    #[OA\Get(summary: 'Health.', security: [])]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: LivenessResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function live(): JsonResponse
    {
        return $this->json(new LivenessResponse());
    }

    #[Route('/ready', name: 'health_ready')]
    #[OA\Get(summary: 'Health ready.', security: [])]
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
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function ready(DatabaseProbe $database): JsonResponse
    {
        $available = $database->isAvailable(self::TIMEOUT_SECONDS) ? Readiness::OK : Readiness::UNAVAILABLE;
        $readiness = new Readiness(status: $available, checks: ['database' => $available]);
        $status = $readiness->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json(data: ReadinessResponse::of($readiness), status: $status);
    }
}
