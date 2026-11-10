<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Response\CopyLinkResponse;
use App\Workflow\Privacy\IssueCopyLinkWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DataRequestCopyLinkController extends AbstractController
{
    #[Route('/api/data-requests/{id}/copy-link', name: 'api_data_requests_copy_link', methods: ['POST'])]
    #[OA\Post(summary: 'Data request copy link.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(
        response: 201,
        description: 'Created.',
        content: new OA\JsonContent(ref: new Model(type: CopyLinkResponse::class)),
    )]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function issue(string $id, IssueCopyLinkWorkflow $issueCopyLink): JsonResponse
    {
        return $this->json(CopyLinkResponse::of($issueCopyLink($id)), status: Response::HTTP_CREATED);
    }
}
