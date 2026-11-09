<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\ErasureSchedules;
use App\Context\Identity\Application\Service\PasswordChecks;
use App\Context\Privacy\Application\Service\OwnErasureRequests;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\ScheduledOwnErasure;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class RequestOwnErasureWorkflow
{
    public function __construct(
        private PasswordChecks $passwords,
        private AccountLookup $accounts,
        private OwnErasureRequests $requests,
        private ErasureSchedules $schedules,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(
        string $accountId,
        #[SensitiveParameter]
        string $password,
        ?string $note,
    ): ?ScheduledOwnErasure {
        $id = Uuid::fromString($accountId);

        if (!$this->passwords->isOwn($id, $password)) {
            return null;
        }

        $requestedBy = ($this->accounts->account($id) ?? throw NotFound::of('account'))->profile()->displayName->value;

        return $this->transactions->run(function () use ($id, $requestedBy, $note): ScheduledOwnErasure {
            $scheduled = $this->requests->schedule($id, $requestedBy, $note);
            $this->schedules->schedule($id, LocalDate::of($scheduled->erasesOn));

            return ScheduledOwnErasure::of($scheduled);
        });
    }
}
