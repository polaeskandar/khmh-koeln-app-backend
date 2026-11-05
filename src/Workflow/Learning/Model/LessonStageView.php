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
    ) {
    }

    public static function of(Stage $stage): self
    {
        return new self(ServiceReference::of($stage->service()), StageSummary::of($stage));
    }
}
