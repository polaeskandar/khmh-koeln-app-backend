<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PrivacyInput
{
    public function __construct(
        #[Assert\NotNull(message: 'accounts.acceptPrivacy')]
        #[Assert\IsTrue(message: 'accounts.acceptPrivacy')]
        public ?bool $accepted = null,
        #[Assert\NotBlank(message: 'accounts.privacyNoticeOutdated')]
        public ?string $version = null,
    ) {
    }
}
