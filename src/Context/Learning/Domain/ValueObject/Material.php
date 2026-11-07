<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class Material
{
    private function __construct(
        public string $label,
        public ?string $url,
    ) {
    }

    /**
     * @param array<array-key, string|null> $row
     */
    public static function fromRow(array $row): ?self
    {
        $label = InputText::of($row['label'] ?? null)->trimmed()->toStringOrNull();
        $url = InputText::of($row['url'] ?? null)->trimmed()->toStringOrNull();
        $shown = $label ?? $url;

        return $shown === null ? null : new self($shown, $url);
    }

    /**
     * @return array{url: string|null, label: string}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'label' => $this->label];
    }
}
