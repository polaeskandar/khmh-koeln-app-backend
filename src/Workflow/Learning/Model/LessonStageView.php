<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Organization\Domain\Entity\Stage;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;

final readonly class LessonStageView
{
    public function __construct(
        public ServiceReference $service,
        public StageSummary $stage,
        public bool $archived,
    ) {
    }

    public static function of(Stage $stage): self
    {
        $service = $stage->service();

        return new self(
            ServiceReference::of($service),
            StageSummary::of($stage),
            archived: $stage->isArchived() || $service->isArchived(),
        );
    }
}
