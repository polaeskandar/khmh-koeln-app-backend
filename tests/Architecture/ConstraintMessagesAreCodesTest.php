<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\ConstraintAttributes;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use Symfony\Component\Validator\Constraint;

final class ConstraintMessagesAreCodesTest extends TestCase
{
    public function testEveryConstraintMessageIsACodeOfTheCatalog(): void
    {
        $unknown = self::constraints()
            ->map(self::messageArguments(...))
            ->map(static fn (array $messages): array => array_values(array_filter($messages, self::isUnknown(...))))
            ->filter(static fn (array $messages): bool => $messages !== []);

        self::assertSame([], $unknown->toArray(), 'Constraint messages must be codes listed in docs/api.md.');
    }

    public function testEveryConstraintWithMessagesSetsACode(): void
    {
        $silent = self::constraints()->filter(self::isSilent(...));

        self::assertSame([], $silent->getKeys(), 'These constraints would send Symfony\'s English message as a code.');
    }

    /**
     * @param ReflectionAttribute<Constraint> $attribute
     */
    private static function isSilent(ReflectionAttribute $attribute): bool
    {
        return self::messageArguments($attribute) === [] && self::hasMessages($attribute->newInstance());
    }

    /**
     * @return ArrayCollection<string, ReflectionAttribute<Constraint>>
     */
    private static function constraints(): ArrayCollection
    {
        return new ArrayCollection(iterator_to_array(ConstraintAttributes::onProperties()));
    }

    private static function isUnknown(string $message): bool
    {
        $catalog = (string) file_get_contents(dirname(__DIR__, levels: 2) . '/docs/api.md');

        return !str_contains($catalog, '| `' . $message . '` |');
    }

    /**
     * @param ReflectionAttribute<Constraint> $attribute
     *
     * @return list<string>
     */
    private static function messageArguments(ReflectionAttribute $attribute): array
    {
        $texts = array_filter($attribute->getArguments(), is_string(...));

        $messages = array_filter(
            $texts,
            static fn (int|string $name): bool => self::isMessage((string) $name),
            ARRAY_FILTER_USE_KEY,
        );

        return array_values($messages);
    }

    private static function hasMessages(Constraint $constraint): bool
    {
        return array_any(
            array_keys(get_object_vars($constraint)),
            static fn (int|string $name): bool => self::isMessage((string) $name),
        );
    }

    private static function isMessage(string $name): bool
    {
        return str_ends_with(strtolower($name), 'message');
    }
}
