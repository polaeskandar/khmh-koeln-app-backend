<?php

declare(strict_types=1);

namespace App\Platform\Lifecycle\Contract;

use Symfony\Component\Workflow\WorkflowInterface;

interface Transitions
{
    public function apply(WorkflowInterface $lifecycle, object $subject, string $transition): bool;
}
