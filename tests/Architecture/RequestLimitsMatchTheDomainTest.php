<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\People\Application\Service\PersonDirectory;
use App\Interface\Api\Identity\Request\AccountRequest;
use App\Interface\Api\Identity\Request\ForgotPasswordRequest;
use App\Interface\Api\Identity\Request\MemberApprovalRequest;
use App\Interface\Api\People\Request\PeopleQueryRequest;
use App\Library\Problem\Exception\InvalidInput;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Length;

final class RequestLimitsMatchTheDomainTest extends KernelTestCase
{
    /**
     * @param class-string $request
     * @param Closure(string): mixed $check
     * @param Closure(int): string $textOf
     */
    #[DataProvider('limits')]
    public function testTheRequestDeclaresTheLimitTheDomainChecks(
        string $request,
        string $field,
        Closure $check,
        Closure $textOf,
    ): void {
        $length = self::lengthOf($request, $field);
        $max = $length->max;
        self::assertNotNull($max, $field);

        self::assertSame([], self::fieldErrorsOf($check, $textOf($max)), $field);

        self::assertSame(
            [$field => ['code' => $length->maxMessage, 'params' => ['max' => $max]]],
            self::fieldErrorsOf($check, $textOf($max + 1)),
        );

        $min = $length->min;

        if ($min === null) {
            return;
        }

        self::assertSame([], self::fieldErrorsOf($check, $textOf($min)), $field);

        self::assertSame(
            [$field => ['code' => $length->minMessage, 'params' => ['min' => $min]]],
            self::fieldErrorsOf($check, $textOf($min - 1)),
        );
    }

    /**
     * @return iterable<string, array{class-string, string, Closure(string): mixed, Closure(int): string}>
     */
    public static function limits(): iterable
    {
        $letters = static fn (int $length): string => str_pad('', $length, pad_string: 'a');
        $email = static fn (int $length): string => str_pad('', $length - 12, pad_string: 'a') . '@example.org';

        yield 'registration password' => [
            AccountRequest::class,
            'password',
            PlainPassword::of(...),
            $letters,
        ];

        yield 'forgotten password email' => [
            ForgotPasswordRequest::class,
            'email',
            Email::of(...),
            $email,
        ];

        yield 'member consent note' => [
            MemberApprovalRequest::class,
            'guardianConsentNote',
            static fn (string $text): MemberLink => MemberLink::of(Uuid::v7(), AgeCheck::GuardianConsent, $text),
            $letters,
        ];

        yield 'people search' => [
            PeopleQueryRequest::class,
            'q',
            static fn (string $text): int => self::directory()->search($text, limit: 1)->count(),
            $letters,
        ];
    }

    /**
     * @param class-string $request
     */
    private static function lengthOf(string $request, string $field): Length
    {
        $attributes = new ReflectionProperty($request, $field)->getAttributes(Length::class);
        $length = ($attributes[0] ?? null)?->newInstance();
        self::assertInstanceOf(Length::class, $length, $field);

        return $length;
    }

    /**
     * @param Closure(string): mixed $check
     *
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    private static function fieldErrorsOf(Closure $check, string $text): array
    {
        try {
            $check($text);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        return [];
    }

    private static function directory(): PersonDirectory
    {
        $directory = self::getContainer()->get(PersonDirectory::class);
        self::assertInstanceOf(PersonDirectory::class, $directory);

        return $directory;
    }
}
