<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\OwnErasureRequestRequest;
use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Interface\Api\Privacy\Response\OwnErasureRequestResponse;
use App\Interface\Api\Privacy\Response\OwnErasureStatusResponse;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use App\Workflow\Privacy\RequestOwnErasureWorkflow;
use App\Workflow\Privacy\ShowOwnErasureRequestWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me')]
final class OwnDataController extends AbstractController
{
    private const string FILE = 'khmk-my-data.json';

    #[Route('/personal-data', name: 'api_me_personal_data', methods: ['GET'])]
    public function export(
        #[CurrentUser]
        UserInterface $viewer,
        ExportAccountDataWorkflow $exportAccountData,
    ): JsonResponse {
        $response = $this->json(AccountDataResponse::of($exportAccountData($viewer->getUserIdentifier())));

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            self::FILE,
        ));

        return $response;
    }

    #[Route('/erasure-request', name: 'api_me_erasure_request', methods: ['GET'])]
    public function erasureRequest(
        #[CurrentUser]
        UserInterface $viewer,
        ShowOwnErasureRequestWorkflow $showOwnErasureRequest,
    ): JsonResponse {
        return $this->json(OwnErasureStatusResponse::of($showOwnErasureRequest($viewer->getUserIdentifier())));
    }

    #[Route('/erasure-request', name: 'api_me_request_erasure', methods: ['POST'])]
    public function requestErasure(
        #[CurrentUser]
        UserInterface $viewer,
        #[MapRequestPayload]
        OwnErasureRequestRequest $request,
        RequestOwnErasureWorkflow $requestOwnErasure,
    ): JsonResponse {
        return $this->json(
            OwnErasureRequestResponse::of($requestOwnErasure($viewer->getUserIdentifier(), $request->note)),
            status: Response::HTTP_CREATED,
        );
    }
}
