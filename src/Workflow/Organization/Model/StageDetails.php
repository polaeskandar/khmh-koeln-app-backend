<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class StageDetails
{
    public function __construct(
        public StageEntry $stage,
        public bool $archived,
        public ServiceReference $service,
    ) {
    }

    /**
     * @param ReadableCollection<string, int> $members
     */
    public static function of(Stage $stage, ReadableCollection $members): self
    {
        return new self(
            StageEntry::of($stage, $members->get($stage->id()->toRfc4122()) ?? 0),
            $stage->isArchived(),
            ServiceReference::of($stage->service()),
        );
    }
}
