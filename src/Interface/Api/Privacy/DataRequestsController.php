<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\EraseRequestRequest;
use App\Interface\Api\Privacy\Request\RecordDataRequestRequest;
use App\Interface\Api\Privacy\Response\DataRequestDetailResponse;
use App\Interface\Api\Privacy\Response\DataRequestListResponse;
use App\Interface\Api\Privacy\Response\DataRequestRowResponse;
use App\Workflow\Privacy\EraseRequestSubjectWorkflow;
use App\Workflow\Privacy\ListDataRequestsWorkflow;
use App\Workflow\Privacy\RecordDataRequestWorkflow;
use App\Workflow\Privacy\ReviewRequestErasureWorkflow;
use App\Workflow\Privacy\ShowDataRequestWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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

    #[Route('/{id}', name: 'api_data_requests_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        string $id,
        #[CurrentUser]
        UserInterface $viewer,
        ShowDataRequestWorkflow $showDataRequest,
        ReviewRequestErasureWorkflow $reviewRequestErasure,
    ): JsonResponse {
        $detail = $showDataRequest($id);

        return $this->json(
            new DataRequestDetailResponse($detail, $reviewRequestErasure($id, $viewer->getUserIdentifier())),
        );
    }

    #[Route(
        '/{id}/erase',
        name: 'api_data_requests_erase',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    public function erase(
        string $id,
        #[CurrentUser]
        UserInterface $viewer,
        #[MapRequestPayload]
        EraseRequestRequest $request,
        EraseRequestSubjectWorkflow $eraseRequestSubject,
        ShowDataRequestWorkflow $showDataRequest,
    ): JsonResponse {
        $eraseRequestSubject($id, $viewer->getUserIdentifier(), $request->choice());

        return $this->json(new DataRequestRowResponse($showDataRequest($id)->request));
    }
}
