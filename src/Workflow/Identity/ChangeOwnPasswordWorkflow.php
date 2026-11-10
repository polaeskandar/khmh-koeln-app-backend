<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\PasswordChanges;
use App\Context\Identity\Domain\Interface\SessionRenewalInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class ChangeOwnPasswordWorkflow
{
    public function __construct(
        private PasswordChanges $passwords,
        private SessionRenewalInterface $session,
    ) {
    }

    public function __invoke(
        string $accountId,
        #[SensitiveParameter]
        string $current,
        #[SensitiveParameter]
        string $new,
    ): void {
        $this->passwords->changeOwn(Uuid::fromString($accountId), $current, $new);
        $this->session->renew();
    }
}
