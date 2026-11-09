<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\OwnErasureRequestRequest;
use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Interface\Api\Privacy\Response\MemberDataResponse;
use App\Interface\Api\Privacy\Response\OwnErasureStatusResponse;
use App\Interface\Api\Privacy\Response\ScheduledErasureResponse;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use App\Workflow\Privacy\ExportMemberActivityWorkflow;
use App\Workflow\Privacy\ExportMemberDataWorkflow;
use App\Workflow\Privacy\Model\OwnErasureStatus;
use App\Workflow\Privacy\RequestOwnErasureWorkflow;
use App\Workflow\Privacy\ShowOwnErasureRequestWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/me')]
final class OwnDataController extends AbstractController
{
    private const string FILE = 'khmk-my-data.json';

    public function __construct(
        #[Autowire(service: 'limiter.password_change')]
        private readonly RateLimiterFactoryInterface $passwordChecks,
        private readonly Calendar $calendar,
    ) {
    }

    #[Route('/personal-data', name: 'api_me_personal_data', methods: ['GET'])]
    #[OA\Get(summary: 'Me personal data.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: AccountDataResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function export(
        #[CurrentUser]
        UserInterface $viewer,
        ExportAccountDataWorkflow $exportAccountData,
        ExportMemberDataWorkflow $exportMemberData,
        ExportMemberActivityWorkflow $exportMemberActivity,
    ): JsonResponse {
        $account = $exportAccountData($viewer->getUserIdentifier());
        $recordId = $account?->memberRecordId();
        $memberRecord = MemberDataResponse::of($exportMemberData($recordId), $exportMemberActivity($recordId));
        $response = $this->json(AccountDataResponse::of($account, $memberRecord));

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            self::FILE,
        ));

        return $response;
    }

    #[Route('/erasure-request', name: 'api_me_erasure_request', methods: ['GET'])]
    #[OA\Get(summary: 'Me erasure request.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: OwnErasureStatus::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function erasureRequest(
        #[CurrentUser]
        UserInterface $viewer,
        ShowOwnErasureRequestWorkflow $showOwnErasureRequest,
    ): JsonResponse {
        return $this->json(OwnErasureStatusResponse::of($showOwnErasureRequest($viewer->getUserIdentifier())));
    }

    #[Route('/erasure-request', name: 'api_me_request_erasure', methods: ['POST'])]
    #[OA\Post(summary: 'Me request erasure.')]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: ScheduledErasureResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function requestErasure(
        #[CurrentUser]
        UserInterface $viewer,
        #[MapRequestPayload]
        OwnErasureRequestRequest $request,
        RequestOwnErasureWorkflow $requestOwnErasure,
    ): JsonResponse {
        $accountId = $viewer->getUserIdentifier();
        $attempts = $this->passwordChecks->create($accountId);
        $left = $attempts->consume(0);

        if ($left->getRemainingTokens() === 0) {
            $seconds = $left->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        $scheduled = $requestOwnErasure($accountId, $request->password ?? '', $request->note);

        if ($scheduled === null) {
            $attempts->consume();

            throw InvalidInput::field('password', 'accounts.currentPasswordWrong');
        }

        return $this->json(ScheduledErasureResponse::of($scheduled), status: Response::HTTP_CREATED);
    }
}
