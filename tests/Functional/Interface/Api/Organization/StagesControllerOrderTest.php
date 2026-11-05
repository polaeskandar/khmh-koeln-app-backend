<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class StagesControllerOrderTest extends ApiTestCase
{
    public function testALeaderReversesTheActiveStagesAndTheArchivedFollow(): void
    {
        [$service, $first, $second, $third] = $this->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $reversed = [$third->id()->toRfc4122(), $second->id()->toRfc4122(), $first->id()->toRfc4122()];

        $this->reorder($service, $reversed);

        self::assertResponseIsSuccessful();
        self::assertSame(['3rd Class 0', '2nd Class 1', '1st Class 2', 'Old Class 3'], $this->order());
        $this->reorder($service, $reversed);
        self::assertSame(['3rd Class 0', '2nd Class 1', '1st Class 2', 'Old Class 3'], $this->order());

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->reorder($service, [$first->id()->toRfc4122(), $second->id()->toRfc4122(), $third->id()->toRfc4122()]);
        self::assertSame(['1st Class 0', '2nd Class 1', '3rd Class 2', 'Old Class 3'], $this->order());
    }

    #[DataProvider('staleOrders')]
    public function testAnythingButTheActiveStagesIsStale(string $case): void
    {
        [$service, $first, $second, $third, $archived] = $this->structure();
        $foreign = Structure::stage(Structure::service('Youth Meeting'), 'Youth 1');
        new Database(self::getContainer())->persisted($foreign->service(), $foreign);
        $ids = array_map(static fn (Stage $stage): string => $stage->id()->toRfc4122(), [$first, $second, $third]);

        $sent = match ($case) {
            'missing' => [$ids[0], $ids[1]],
            'unknown' => [$ids[0], $ids[1], Uuid::v7()->toRfc4122()],
            'foreign' => [$ids[0], $ids[1], $foreign->id()->toRfc4122()],
            'archived' => [...$ids, $archived->id()->toRfc4122()],
            'repeated' => [$ids[0], $ids[1], $ids[1]],
            default => [],
        };

        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->reorder($service, $sent);

        $this->assertProblem(409, 'stages.reorderStale');
        $this->jsonRequest('GET', '/api/services/' . $service->id()->toRfc4122() . '/stages');
        self::assertSame(['1st Class 0', '2nd Class 1', '3rd Class 2', 'Old Class 3'], $this->order());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function staleOrders(): iterable
    {
        foreach (['missing', 'unknown', 'foreign', 'archived', 'repeated', 'empty'] as $case) {
            yield $case => [$case];
        }
    }

    public function testAnEmptyOrderSuitsAServiceWithoutActiveStages(): void
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->reorder($service, []);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('stages', []);
    }

    public function testRefusalsAndInvalidBodies(): void
    {
        [$service, $first] = $this->structure();
        $archived = Structure::service('Old choir');
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($archived);

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->reorder($service, [$first->id()->toRfc4122()]);
            $this->assertProblem(403, 'forbidden');
            $this->browser()->getCookieJar()->clear();
        }

        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->reorder($archived, []);
        $this->assertProblem(409, 'services.archivedNoStageChanges', ['name' => 'Old choir']);
        $this->reorder($service, ['not-a-uuid']);

        self::assertSame(
            ['stageIds.0'],
            array_keys(JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, 'fieldErrors')),
        );

        $this->reorder($service, array_map(static fn (): string => Uuid::v7()->toRfc4122(), range(start: 1, end: 201)));
        $this->assertFieldError('stageIds', 'validation.tooMany', ['max' => 200]);
        $this->jsonRequest('PUT', '/api/services/' . Uuid::v7()->toRfc4122() . '/stages/order', ['stageIds' => []]);
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('PUT', '/api/services/not-a-service/stages/order', ['stageIds' => []]);
        $this->assertProblem(404, 'notFound.service');
    }

    /**
     * @param list<string> $ids
     */
    private function reorder(Service $service, array $ids): void
    {
        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122() . '/stages/order', [
            'stageIds' => $ids,
        ]);
    }

    /**
     * @return list<string>
     */
    private function order(): array
    {
        $active = array_map(
            static function (mixed $stage): string {
                $stage = JsonBody::asArray($stage, 'stage');

                $position = Integers::of($stage['position'] ?? null);

                return Type::of($stage['name'] ?? null)->toString() . ' ' . $position;
            },
            JsonBody::asArray($this->responseJson()['stages'] ?? null, 'stages'),
        );

        return array_values([...$active, ...$this->archivedPositions()]);
    }

    /**
     * @return list<string>
     */
    private function archivedPositions(): array
    {
        return array_map(
            static fn (mixed $row): string => Type::of($row)->toString(),
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn(
                    "SELECT name || ' ' || position FROM organization.stage WHERE archived_at IS NOT NULL ORDER BY position",
                ),
        );
    }

    /**
     * @return array{Service, Stage, Stage, Stage, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $first = Structure::stage($service, '1st Class', 0);
        $second = Structure::stage($service, '2nd Class', 1);
        $third = Structure::stage($service, '3rd Class', 2);
        $archived = Structure::stage($service, 'Old Class', 3);
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($service, $first, $second, $third, $archived);

        return [$service, $first, $second, $third, $archived];
    }
}
