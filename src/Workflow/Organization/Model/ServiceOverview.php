<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use DateTimeInterface;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceOverview
{
    /**
     * @param list<StageSummary> $stages
     */
    public function __construct(
        public ServiceSummary $service,
        public array $stages,
        public ServiceAbilities $can,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(Service $service, ReadableCollection $stages, ServiceAbilities $can): self
    {
        return new self(
            new ServiceSummary(
                id: $service->id()->toRfc4122(),
                name: $service->name()->value,
                description: $service->description()->value,
                tone: $service->tone()->value,
                archivedAt: $service->archivedAt()?->format(DateTimeInterface::ATOM),
            ),
            $stages->map(self::stageOf(...))->getValues(),
            $can,
        );
    }

    private static function stageOf(Stage $stage): StageSummary
    {
        return new StageSummary(
            id: $stage->id()->toRfc4122(),
            name: $stage->name()->value,
            position: $stage->position(),
            archived: $stage->isArchived(),
        );
    }
}
