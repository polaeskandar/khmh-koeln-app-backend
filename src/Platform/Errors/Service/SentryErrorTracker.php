<?php

declare(strict_types=1);

namespace App\Platform\Errors\Service;

use App\Platform\Errors\Contract\ErrorTracker;
use Override;
use Sentry\State\HubInterface;
use Throwable;

final readonly class SentryErrorTracker implements ErrorTracker
{
    public function __construct(
        private HubInterface $hub,
    ) {
    }

    #[Override]
    public function capture(Throwable $error): void
    {
        if ($this->hub->getClient()?->getOptions()->getDsn() === null) {
            return;
        }

        $this->hub->captureException($error);
    }
}
