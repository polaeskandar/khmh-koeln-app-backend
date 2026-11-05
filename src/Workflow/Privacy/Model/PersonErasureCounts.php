<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Library\Strings\Model\Text;

final readonly class PersonErasureCounts
{
    public function __construct(
        public int $memberships,
        public int $attendance,
        public int $completions,
        public int $guardianLinks,
        public int $sessions,
    ) {
    }

    public function summary(): string
    {
        return Text::format(
            '%d memberships, %d marks, %d reports, %d guardian links deleted; %d sessions keep their totals.',
            $this->memberships,
            $this->attendance,
            $this->completions,
            $this->guardianLinks,
            $this->sessions,
        )->toString();
    }
}
