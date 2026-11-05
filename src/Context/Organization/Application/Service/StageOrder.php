<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\StageSequence;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StageOrder
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
    ) {
    }

    /**
     * @param list<string> $stageIds
     */
    public function reorder(string $serviceId, array $stageIds): Service
    {
        $id = Uuid::isValid($serviceId) ? Uuid::fromString($serviceId) : throw NotFound::of('service');
        $sequence = StageSequence::of($stageIds);

        return $this->services->withStructureLock($id, fn (Service $service): Service => $this->reorderIn(
            $service,
            $sequence,
        ));
    }

    private function reorderIn(Service $service, StageSequence $sequence): Service
    {
        StageChecks::ensureOpen($service);
        $all = $this->stages->ofService($service);

        $active = Collections::firstPerKey(
            $all->filter(static fn (Stage $stage): bool => !$stage->isArchived()),
            keyOf: self::idOf(...),
        );

        $sequence->ensureCovers($active->getKeys());

        $archived = Collections::sortedBy(
            $all->filter(static fn (Stage $stage): bool => $stage->isArchived()),
            keyOf: self::positionKey(...),
        );

        $reordered = Collections::mapPresent(new ArrayCollection($sequence->stageIds), $active->get(...));
        $this->renumber([...$reordered->getValues(), ...$archived]);

        return $service;
    }

    /**
     * @param list<Stage> $stages
     */
    private function renumber(array $stages): void
    {
        foreach ($stages as $position => $stage) {
            $stage->moveTo($position);
            $this->stages->save($stage);
        }
    }

    private static function idOf(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }

    private static function positionKey(Stage $stage): string
    {
        return Text::format('%06d %s', $stage->position(), $stage->id()->toRfc4122())->toString();
    }
}
