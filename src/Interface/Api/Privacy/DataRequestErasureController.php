<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\EraseRequestRequest;
use App\Interface\Api\Privacy\Response\DataRequestRowResponse;
use App\Interface\Api\Privacy\Response\ErasureReviewResponse;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\EraseRequestSubjectWorkflow;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\ErasureReview;
use App\Workflow\Privacy\ShowDataRequestWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Get(summary: 'Data request erasure review.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: ErasureReviewResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function review(
        string $id,
        #[CurrentUser]
        UserInterface $viewer,
        EraseRequestSubjectWorkflow $eraseRequestSubject,
        Transactions $transactions,
    ): JsonResponse {
        $review = $transactions->rehearse(static fn (): ErasureReview => $eraseRequestSubject(
            $id,
            $viewer->getUserIdentifier(),
            new ErasureChoice(clearNotes: false),
        ));

        return $this->json(ErasureReviewResponse::of($review));
    }

    #[Route('/erase', name: 'api_data_requests_erase', methods: ['POST'])]
    #[OA\Post(summary: 'Data request erase.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: DataRequestRowResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
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
