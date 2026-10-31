<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test/problems')]
final readonly class PayloadProbeController
{
    #[Route('/payload', methods: ['POST'])]
    public function payload(#[MapRequestPayload] ProbePayload $payload): JsonResponse
    {
        return new JsonResponse(['name' => $payload->name]);
    }

    #[Route('/schedule', methods: ['POST'])]
    public function schedule(#[MapRequestPayload] ProbeSchedule $schedule): JsonResponse
    {
        return new JsonResponse(['date' => $schedule->date, 'time' => $schedule->time]);
    }
}
