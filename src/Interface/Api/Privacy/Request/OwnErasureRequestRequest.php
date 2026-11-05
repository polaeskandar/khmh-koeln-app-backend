<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OwnErasureRequestRequest
{
    public function __construct(
        #[Assert\Length(max: 2000, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $note = null,
    ) {
    }
}
