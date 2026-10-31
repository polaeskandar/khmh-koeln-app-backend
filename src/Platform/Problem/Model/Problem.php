<?php

declare(strict_types=1);

namespace App\Platform\Problem\Model;

use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class Problem
{
    private const string CODE_PATTERN = '/^[a-z][a-zA-Z]*(\.[a-z][a-zA-Z]*)*$/';

    /**
     * @param ReadableCollection<string, string|int|float|bool|null> $params
     * @param ReadableCollection<string, FieldError> $fieldErrors
     * @param ReadableCollection<string, object|list<object>> $extensions
     */
    public function __construct(
        public int $status,
        public string $code,
        public ReadableCollection $params = new ArrayCollection(),
        public ReadableCollection $fieldErrors = new ArrayCollection(),
        public ReadableCollection $extensions = new ArrayCollection(),
    ) {
    }

    public static function isCode(string $candidate): bool
    {
        return Text::of($candidate)->matches(self::CODE_PATTERN);
    }

    /**
     * @return array<string, int|string|object|list<object>>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'params' => (object) $this->params->toArray(),
            ...$this->fieldErrorsMember(),
            ...$this->extensions->toArray(),
        ];
    }

    /**
     * @return array<string, object>
     */
    private function fieldErrorsMember(): array
    {
        if ($this->fieldErrors->isEmpty()) {
            return [];
        }

        return [
            'fieldErrors' => (object) $this->fieldErrors->map(
                static fn (FieldError $error): object => $error->toObject(),
            )->toArray(),
        ];
    }
}
