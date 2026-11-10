<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Controller;

use Nelmio\ApiDocBundle\Controller\DocumentationController;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/openapi.json', name: 'openapi', methods: ['GET'], env: ['dev', 'test'])]
final class OpenApiController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'nelmio_api_doc.controller.swagger_json')]
        private readonly DocumentationController $documentation,
    ) {
    }

    #[OA\Get(summary: 'OpenAPI document.', security: [])]
    #[OA\Response(response: 200, description: 'The result.', content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(ref: '#/components/responses/Problem', response: 'default')]
    public function __invoke(Request $request): JsonResponse
    {
        return ($this->documentation)($request);
    }
}
