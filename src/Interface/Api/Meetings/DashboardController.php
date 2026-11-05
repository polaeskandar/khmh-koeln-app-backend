<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\DashboardResponse;
use App\Workflow\Meetings\ShowDashboardWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function show(ShowDashboardWorkflow $showDashboard): JsonResponse
    {
        return $this->json(DashboardResponse::of($showDashboard()));
    }
}
