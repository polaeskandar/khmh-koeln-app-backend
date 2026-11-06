<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\OpenApi;

use App\Tests\Support\JsonBody;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

final readonly class OpenApiDocument
{
    private const string URI = 'https://api.khmk.test/openapi.json';

    private const string RESPONSES = '#/components/responses/';

    /**
     * @param array<array-key, mixed> $document
     */
    private function __construct(
        private array $document,
        private Validator $validator,
    ) {
    }

    public static function load(string $file): self
    {
        $document = ClosedSchemas::of(JsonBody::asArray(
            json_decode((string) file_get_contents($file), associative: true, flags: JSON_THROW_ON_ERROR),
            'openapi.json',
        ));

        $validator = new Validator();

        $validator->resolver()?->registerRaw(json_encode($document, JSON_THROW_ON_ERROR), self::URI);

        return new self($document, $validator);
    }

    public function response(string $path, string $method, int $status): DocumentedResponse
    {
        $operation = ['paths', $path, strtolower($method), 'responses'];
        $responses = $this->at($operation, $method . ' ' . $path);
        $keys = [...$operation, \array_key_exists($status, $responses) ? (string) $status : 'default'];
        $response = $this->at($keys, implode(' ', $keys));
        $reference = \array_key_exists('$ref', $response) && \is_string($response['$ref']) ? $response['$ref'] : null;

        if ($reference !== null) {
            $keys = ['components', 'responses', substr($reference, \strlen(self::RESPONSES))];
        }

        return new DocumentedResponse(Pointer::of($keys), $this->mediaTypes($keys));
    }

    public function violation(string $body, string $schemaPointer): ?ValidationError
    {
        return $this->validator
            ->validate(
                json_decode($body, flags: JSON_THROW_ON_ERROR),
                (object) ['$ref' => self::URI . '#/' . $schemaPointer],
            )
            ->error();
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function mediaTypes(array $keys): array
    {
        $response = $this->at($keys, implode(' ', $keys));

        return array_map(strval(...), array_keys(JsonBody::asArray($response['content'] ?? [], 'content')));
    }

    /**
     * @param list<string> $keys
     *
     * @return array<array-key, mixed>
     */
    private function at(array $keys, string $what): array
    {
        return JsonBody::asArray(JsonBody::valueAt($this->document, $keys, $what), $what);
    }
}
