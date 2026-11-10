<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\ErasureSchedules;
use App\Context\Identity\Application\Service\PasswordChecks;
use App\Context\Privacy\Application\Service\ScheduledErasures;
use App\Platform\Doctrine\Contract\Transactions;
use SensitiveParameter;

final readonly class CancelOwnErasureWorkflow
{
    public function __construct(
        private PasswordChecks $passwords,
        private ScheduledErasures $erasures,
        private ErasureSchedules $schedules,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $email, #[SensitiveParameter] string $password): bool
    {
        $accountId = $this->passwords->findAccountWith($email, $password);

        if ($accountId === null) {
            return false;
        }

        $this->transactions->run(function () use ($accountId): void {
            $this->erasures->withdraw($accountId);
            $this->schedules->cancel($accountId);
        });

        return true;
    }
}
