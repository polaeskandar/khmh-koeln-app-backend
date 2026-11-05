<?php

declare(strict_types=1);

namespace App\Interface\Api\People;

use App\Interface\Api\People\Response\DirectoryResponse;
use App\Workflow\People\SearchDirectoryWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/people/directory', methods: ['GET'])]
final class DirectoryController extends AbstractController
{
    #[Route('', name: 'api_people_directory')]
    public function search(SearchDirectoryWorkflow $searchDirectory, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        return $this->json(new DirectoryResponse($searchDirectory($q)));
    }
}
