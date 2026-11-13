<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;

final readonly class MemberAccounts
{
    public function __construct(
        private MemberAccountRepositoryInterface $members,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     * }> the linked accounts without a staff role
     */
    public function listed(): array
    {
        return $this->members->listed();
    }
}
