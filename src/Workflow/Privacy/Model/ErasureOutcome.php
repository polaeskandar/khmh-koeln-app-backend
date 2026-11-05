<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\ValueObject\AccountErased;
use App\Context\People\Domain\ValueObject\PersonErased;

final readonly class ErasureOutcome
{
    public const array KEPT = ['sessionTotals', 'accountRows'];

    public function __construct(
        private ?PersonErased $person,
        private ?AccountErased $account,
        private int $accountLinks,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $person = $this->person;
        $account = $this->account;

        return [
            'person' => $person === null ? 0 : 1,
            'memberships' => $person->people->memberships ?? 0,
            'attendance' => $person->elsewhere->marks ?? 0,
            'completions' => $person->elsewhere->reports ?? 0,
            'guardianLinks' => ($person->people->guardianLinks ?? 0) + $this->accountLinks,
            'sessionTotals' => $person->elsewhere->sessions ?? 0,
            'account' => $account === null ? 0 : 1,
            'roles' => $account->roles ?? 0,
            'accountRows' => $account->rowsKept ?? 0,
        ];
    }
}
