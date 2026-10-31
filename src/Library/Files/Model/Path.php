<?php

declare(strict_types=1);

namespace App\Library\Files\Model;

use Doctrine\Common\Collections\ArrayCollection;

final readonly class Path
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    /**
     * @return ArrayCollection<int, self>
     */
    public function directories(string $pattern): ArrayCollection
    {
        $found = glob($this->value . '/' . $pattern, GLOB_ONLYDIR);

        return new ArrayCollection($found === false ? [] : $found)->map(self::of(...));
    }

    /**
     * @param positive-int $levels
     */
    public function parent(int $levels = 1): self
    {
        return new self(dirname($this->value, $levels));
    }

    public function name(): string
    {
        return basename($this->value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
