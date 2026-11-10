<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Interface\Api\Organization\Response\ServiceServantsResponse;
use App\Workflow\Organization\ListServiceServantsWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/services')]
final class ServiceServantsController extends AbstractController
{
    #[Route('/{id}/servants', name: 'api_service_servants', methods: ['GET'])]
    #[OA\Get(summary: 'Service servants.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ServiceServantsResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function servants(string $id, ListServiceServantsWorkflow $listServants): JsonResponse
    {
        return $this->json(ServiceServantsResponse::of($listServants($id)));
    }
}
