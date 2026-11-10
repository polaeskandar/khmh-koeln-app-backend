<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Organization\ListServiceInsightsWorkflow;
use App\Workflow\Organization\ListServiceLeadersWorkflow;
use App\Workflow\Organization\Model\ServiceInsight;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceInsightsController extends AbstractController
{
    #[Route('/api/services/insights', name: 'api_services_insights', methods: ['GET'], priority: 1)]
    #[OA\Get(summary: 'Service insights.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['items'], properties: [
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(ref: new Model(type: ServiceInsight::class)),
            ),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function insights(
        ListServiceInsightsWorkflow $listServiceInsights,
        #[MapQueryParameter]
        string $status = 'active',
    ): JsonResponse {
        return $this->json(['items' => $listServiceInsights($status)]);
    }

    #[Route('/api/service-leaders', name: 'api_service_leaders', methods: ['GET'])]
    #[OA\Get(summary: 'Service leaders.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(required: ['items'], properties: [
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(ref: new Model(type: AccountReference::class)),
            ),
        ]),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function leaders(ListServiceLeadersWorkflow $listServiceLeaders): JsonResponse
    {
        return $this->json(['items' => $listServiceLeaders()]);
    }
}
