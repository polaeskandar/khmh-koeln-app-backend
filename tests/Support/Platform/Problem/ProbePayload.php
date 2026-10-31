<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProbePayload
{
    /**
     * @param list<ProbeMaterial> $materials
     */
    public function __construct(
        #[Assert\NotBlank(message: 'services.nameRequired')]
        #[Assert\Length(min: 2, max: 120, minMessage: 'accounts.passwordTooShort', maxMessage: 'validation.tooLong')]
        public ?string $name = null,
        public ?int $capacity = null,
        #[Assert\Valid]
        public array $materials = [],
    ) {
    }
}
