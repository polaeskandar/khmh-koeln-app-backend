<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test/problems')]
final readonly class ExceptionProbeController
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Route('/rule', methods: ['GET'])]
    public function rule(): never
    {
        throw new RuleViolation(
            'services.hasHistory',
            params: ['name' => 'Choir', 'count' => 12],
            extensions: [
                'candidates' => [(object) ['id' => '0192f3a4-7b1c-7d2e-8f3a-4b5c6d7e8f31', 'firstName' => 'Mina']],
            ],
        );
    }

    #[Route('/not-found', methods: ['GET'])]
    public function notFound(): never
    {
        throw NotFound::of('service');
    }

    #[Route('/entity-not-found', methods: ['GET'])]
    public function entityNotFound(): never
    {
        throw new NotFoundHttpException('notFound.service');
    }

    #[Route('/invalid-input', methods: ['GET'])]
    public function invalidInput(): never
    {
        throw new InvalidInput(['name' => ['code' => 'services.nameTaken', 'params' => ['name' => 'Choir']]]);
    }

    #[Route('/forbidden', methods: ['GET'])]
    public function forbidden(): never
    {
        throw new AccessDeniedHttpException('services.adminOnlyCreate');
    }

    #[Route('/denied', methods: ['GET'])]
    public function denied(): never
    {
        throw new AccessDeniedHttpException('Access Denied.');
    }

    #[Route('/crash', methods: ['GET'])]
    public function crash(): never
    {
        throw new RuntimeException('Secret detail of Mina Hanna');
    }

    #[Route('/unavailable', methods: ['GET'])]
    public function unavailable(): never
    {
        throw new ServiceUnavailableHttpException(30);
    }

    #[Route('/unique', methods: ['GET'])]
    public function unique(): Response
    {
        $this->connection->executeStatement('CREATE TEMPORARY TABLE problem_probe (id INT PRIMARY KEY)');
        $this->connection->executeStatement('INSERT INTO problem_probe (id) VALUES (1)');
        $this->connection->executeStatement('INSERT INTO problem_probe (id) VALUES (1)');

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
