<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\RecordDataRequestRequest;
use App\Interface\Api\Privacy\Response\DataRequestDetailResponse;
use App\Interface\Api\Privacy\Response\DataRequestListResponse;
use App\Workflow\Privacy\ListDataRequestsWorkflow;
use App\Workflow\Privacy\RecordDataRequestWorkflow;
use App\Workflow\Privacy\ShowDataRequestWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/data-requests')]
final class DataRequestsController extends AbstractController
{
    #[Route('', name: 'api_data_requests_record', methods: ['POST'])]
    public function record(
        #[MapRequestPayload]
        RecordDataRequestRequest $request,
        RecordDataRequestWorkflow $recordDataRequest,
    ): JsonResponse {
        return $this->json(['id' => $recordDataRequest($request->details())], status: Response::HTTP_CREATED);
    }

    #[Route('', name: 'api_data_requests_list', methods: ['GET'])]
    public function list(ListDataRequestsWorkflow $listDataRequests): JsonResponse
    {
        return $this->json(DataRequestListResponse::of($listDataRequests()));
    }

    #[Route('/{id}', name: 'api_data_requests_show', methods: ['GET'])]
    public function show(string $id, ShowDataRequestWorkflow $showDataRequest): JsonResponse
    {
        return $this->json(DataRequestDetailResponse::of($showDataRequest($id)));
    }
}
