<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use App\Workflow\Privacy\Model\ErasureChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EraseRequestRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidType')]
        public ?bool $clearNotes = null,
    ) {
    }

    public function choice(): ErasureChoice
    {
        return new ErasureChoice($this->clearNotes === true);
    }
}
