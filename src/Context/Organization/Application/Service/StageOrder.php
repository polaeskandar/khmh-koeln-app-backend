<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\Validation\ArchiveRule;
use App\Context\Organization\Domain\ValueObject\StageSequence;
use App\Library\Collections\Model\Collections;
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
    public function reorder(Uuid $serviceId, array $stageIds): Service
    {
        $sequence = StageSequence::of($stageIds);

        return $this->services->withWriteLock($serviceId, fn (Service $service): Service => $this->reorderIn(
            $service,
            $sequence,
        ));
    }

    /**
     * @param list<Stage> $stages
     */
    public function renumber(array $stages): void
    {
        foreach ($stages as $position => $stage) {
            $stage->moveTo($position);
            $this->stages->save($stage);
        }
    }

    private function reorderIn(Service $service, StageSequence $sequence): Service
    {
        ArchiveRule::ensureStagesChangeable($service);
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

    private static function idOf(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }

    private static function positionKey(Stage $stage): string
    {
        return Text::format('%06d %s', $stage->position(), $stage->id()->toRfc4122())->toString();
    }
}
