<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization;

use App\Workflow\Organization\ListServiceInsightsWorkflow;
use App\Workflow\Organization\ListServiceLeadersWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceInsightsController extends AbstractController
{
    #[Route('/api/services/insights', name: 'api_services_insights', methods: ['GET'], priority: 1)]
    public function insights(
        ListServiceInsightsWorkflow $listServiceInsights,
        #[MapQueryParameter]
        string $status = 'active',
    ): JsonResponse {
        return $this->json(['items' => $listServiceInsights($status)]);
    }

    #[Route('/api/service-leaders', name: 'api_service_leaders', methods: ['GET'])]
    public function leaders(ListServiceLeadersWorkflow $listServiceLeaders): JsonResponse
    {
        return $this->json(['items' => $listServiceLeaders()]);
    }
}
