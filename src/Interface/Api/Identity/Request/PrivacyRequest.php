<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PrivacyRequest
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
