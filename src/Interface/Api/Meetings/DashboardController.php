<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Response\AgendaResponse;
use App\Workflow\Meetings\Model\AgendaFilter;
use App\Workflow\Meetings\ShowAgendaWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const int DAYS_AHEAD = 7;

    #[Route('/api/dashboard/upcoming', name: 'api_dashboard_upcoming', methods: ['GET'])]
    public function upcoming(ShowAgendaWorkflow $showAgenda): JsonResponse
    {
        return $this->json(AgendaResponse::of($showAgenda(AgendaFilter::ahead(self::DAYS_AHEAD))));
    }
}
