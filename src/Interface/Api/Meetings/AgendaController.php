<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings;

use App\Interface\Api\Meetings\Request\AgendaQueryRequest;
use App\Interface\Api\Meetings\Response\AgendaResponse;
use App\Workflow\Meetings\ShowAgendaWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class AgendaController extends AbstractController
{
    #[Route('/api/agenda', name: 'api_agenda', methods: ['GET'])]
    #[OA\Get(summary: 'Agenda.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AgendaResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function show(
        ShowAgendaWorkflow $showAgenda,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        AgendaQueryRequest $query = new AgendaQueryRequest(),
    ): JsonResponse {
        return $this->json(AgendaResponse::of($showAgenda($query->filter())));
    }
}
