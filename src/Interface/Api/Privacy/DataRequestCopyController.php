<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Interface\Api\Privacy\Response\DataRequestRowResponse;
use App\Interface\Api\Privacy\Response\MemberDataResponse;
use App\Workflow\Privacy\CompleteCopyRequestWorkflow;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use App\Workflow\Privacy\ExportMemberActivityWorkflow;
use App\Workflow\Privacy\ExportMemberDataWorkflow;
use App\Workflow\Privacy\Model\CopySubject;
use App\Workflow\Privacy\ShowCopySubjectWorkflow;
use App\Workflow\Privacy\ShowDataRequestWorkflow;
use JsonSerializable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/data-requests/{id}')]
final class DataRequestCopyController extends AbstractController
{
    private const string FILE = 'khmk-data-copy.json';

    public function __construct(
        private readonly ExportAccountDataWorkflow $exportAccountData,
        private readonly ExportMemberDataWorkflow $exportMemberData,
        private readonly ExportMemberActivityWorkflow $exportMemberActivity,
    ) {
    }

    #[Route('/copy', name: 'api_data_requests_copy', methods: ['GET'])]
    #[OA\Get(summary: 'Data request copy.')]
    #[OA\Response(response: 200, description: 'The copy, as an attachment.', content: new OA\JsonContent(oneOf: [
        new OA\Schema(ref: new Model(type: AccountDataResponse::class)),
        new OA\Schema(ref: new Model(type: MemberDataResponse::class)),
    ]))]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function download(string $id, ShowCopySubjectWorkflow $showCopySubject): JsonResponse
    {
        $response = $this->json($this->documentOf($showCopySubject($id)));

        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            self::FILE,
        ));

        return $response;
    }

    #[Route('/complete', name: 'api_data_requests_complete', methods: ['POST'])]
    #[OA\Post(summary: 'Data request complete.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: DataRequestRowResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function complete(
        string $id,
        #[CurrentUser]
        UserInterface $viewer,
        CompleteCopyRequestWorkflow $completeCopyRequest,
        ShowDataRequestWorkflow $showDataRequest,
    ): JsonResponse {
        $completeCopyRequest($id, $viewer->getUserIdentifier());

        return $this->json(new DataRequestRowResponse($showDataRequest($id)->request));
    }

    private function documentOf(CopySubject $subject): ?JsonSerializable
    {
        return $subject->isMember()
            ? $this->memberCopy($subject->personId)
            : $this->accountCopy($subject->accountId ?? '');
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
