<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class MemberLinks
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function link(
        string $accountId,
        string $personId,
        string $ageCheck = 'sixteenOrOlder',
        ?string $note = null,
    ): void {
        new Database($this->container)
            ->connection()
            ->update(
                'identity.app_user',
                ['person_id' => $personId, 'age_check' => $ageCheck, 'guardian_consent_note' => $note],
                ['id' => $accountId],
            );
    }
}
