<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\EraseRequestRequest;
use App\Interface\Api\Privacy\Response\DataRequestRowResponse;
use App\Interface\Api\Privacy\Response\ErasureReviewResponse;
use App\Workflow\Privacy\EraseRequestSubjectWorkflow;
use App\Workflow\Privacy\ReviewRequestErasureWorkflow;
use App\Workflow\Privacy\ShowDataRequestWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/data-requests/{id}')]
final class DataRequestErasureController extends AbstractController
{
    #[Route('/erasure', name: 'api_data_requests_erasure_review', methods: ['GET'])]
    public function review(
        string $id,
        #[CurrentUser]
        UserInterface $viewer,
        ReviewRequestErasureWorkflow $reviewRequestErasure,
    ): JsonResponse {
        return $this->json(ErasureReviewResponse::of($reviewRequestErasure($id, $viewer->getUserIdentifier())));
    }

    #[Route('/erase', name: 'api_data_requests_erase', methods: ['POST'])]
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
