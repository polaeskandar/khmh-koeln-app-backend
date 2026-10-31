<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProbeMaterial
{
    public function __construct(
        #[Assert\Url(message: 'materials.url', requireTld: true)]
        public ?string $url = null,
    ) {
    }
}
