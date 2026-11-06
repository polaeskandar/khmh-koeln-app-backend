<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Response\DirectoryResponse;
use App\Workflow\People\SearchDirectoryWorkflow;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/people/directory', methods: ['GET'])]
final class DirectoryController extends AbstractController
{
    #[Route('', name: 'api_people_directory')]
    #[OA\Get(summary: 'People directory.')]
    #[OA\Response(
        response: 200,
        description: 'The result.',
        content: new OA\JsonContent(ref: new Model(type: DirectoryResponse::class)),
    )]
    #[OA\Response(response: 'default', ref: '#/components/responses/Problem')]
    public function search(SearchDirectoryWorkflow $searchDirectory, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        return $this->json(new DirectoryResponse($searchDirectory($q)));
    }
}
