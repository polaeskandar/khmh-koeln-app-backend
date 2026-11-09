<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Request\DataCopyRequest;
use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Interface\Api\Privacy\Response\MemberDataResponse;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use App\Workflow\Privacy\ExportMemberActivityWorkflow;
use App\Workflow\Privacy\ExportMemberDataWorkflow;
use App\Workflow\Privacy\Model\CopySubject;
use App\Workflow\Privacy\UseCopyLinkWorkflow;
use JsonSerializable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DataCopiesController extends AbstractController
{
    private const string FILE = 'khmk-my-data.json';

    private const string INVALID = 'privacy.copyLinkInvalid';

    public function __construct(
        #[Autowire(service: 'limiter.data_copy')]
        private readonly RateLimiterFactoryInterface $downloads,
        private readonly Calendar $calendar,
        private readonly ExportAccountDataWorkflow $exportAccountData,
        private readonly ExportMemberDataWorkflow $exportMemberData,
        private readonly ExportMemberActivityWorkflow $exportMemberActivity,
    ) {
    }

    #[Route('/api/data-copies', name: 'api_data_copies', methods: ['POST'])]
    #[OA\Post(security: [], summary: 'Data copies.')]
    #[OA\Response(response: 200, description: 'The copy, as an attachment.', content: new OA\JsonContent(oneOf: [
        new OA\Schema(ref: new Model(type: AccountDataResponse::class)),
        new OA\Schema(ref: new Model(type: MemberDataResponse::class)),
    ]))]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function download(
        Request $request,
        #[MapRequestPayload]
        DataCopyRequest $copy,
        UseCopyLinkWorkflow $useCopyLink,
        Transactions $transactions,
    ): JsonResponse {
        $limit = $this->downloads->create($request->getClientIp() ?? '')->consume();

        if (!$limit->isAccepted()) {
            $seconds = $limit->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

            throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
        }

        $token = $copy->token ?? '';
        $document = $transactions->run(fn (): ?JsonSerializable => $this->documentOf($useCopyLink($token)));

        $response = $this->json($document ?? throw new UnprocessableEntityHttpException(self::INVALID));

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            self::FILE,
        ));

        return $response;
    }

    private function documentOf(?CopySubject $subject): ?JsonSerializable
    {
        if ($subject === null) {
            return null;
        }

        $document = $subject->isMember()
            ? $this->memberCopy($subject->personId)
            : $this->accountCopy($subject->accountId ?? '');

        return $document ?? throw new UnprocessableEntityHttpException(self::INVALID);
    }

    private function memberCopy(?string $personId): ?MemberDataResponse
    {
        return MemberDataResponse::of(($this->exportMemberData)($personId), ($this->exportMemberActivity)($personId));
    }

    private function accountCopy(string $accountId): ?AccountDataResponse
    {
        $account = ($this->exportAccountData)($accountId);

        return AccountDataResponse::of($account, $this->memberCopy($account?->memberRecordId()));
    }
}
