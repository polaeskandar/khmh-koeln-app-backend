<?php

declare(strict_types=1);

namespace App\Platform\Problem\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class FieldError
{
    /**
     * @param ReadableCollection<string, string|int|float|bool|null> $params
     */
    public function __construct(
        public string $code,
        public ReadableCollection $params = new ArrayCollection(),
    ) {
    }

    public function toObject(): object
    {
        return (object) ['code' => $this->code, 'params' => (object) $this->params->toArray()];
    }
}
