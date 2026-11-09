<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use Symfony\Component\Uid\Uuid;

final readonly class ErasureOutcome
{
    /**
     * @param list<Mention> $mentions
     */
    public function __construct(
        private int $notes,
        private ?PersonErasureCounts $person,
        private ?ErasureCounts $account,
        public ?Uuid $accountId,
        public array $mentions,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function erased(): array
    {
        $person = $this->person;
        $account = $this->account;

        return [
            'person' => (int) ($person !== null),
            'memberships' => $person->memberships ?? 0,
            'attendance' => $person->attendance ?? 0,
            'completions' => $person->reports ?? 0,
            'guardianLinks' => ($person->guardianLinks ?? 0) + ($account->guardianLinks ?? 0),
            'account' => (int) ($account !== null),
            'roles' => $account->roles ?? 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function kept(): array
    {
        return [
            'sessionTotals' => $this->person->sessions ?? 0,
            'accountRows' => $this->account->rowsKept ?? 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [...$this->erased(), ...$this->kept(), 'notes' => $this->notes];
    }
}
