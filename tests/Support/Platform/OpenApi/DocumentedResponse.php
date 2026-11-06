<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\OpenApi;

final readonly class DocumentedResponse
{
    /**
     * @param list<string> $mediaTypes
     */
    public function __construct(
        public string $pointer,
        public array $mediaTypes,
    ) {
    }

    public function schemaOf(string $mediaType): string
    {
        return $this->pointer . '/' . Pointer::of(['content', $mediaType, 'schema']);
    }
}
