<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class ServiceValidationTest extends TestCase
{
    public function testABlankNameIsRejected(): void
    {
        self::assertSame(
            ['name' => ['code' => 'services.nameRequired']],
            ThrownFieldErrors::of(static fn (): Service => Structure::service(name: " \u{00A0} ")),
        );
    }

    public function testANameLongerThan120CharactersIsRejected(): void
    {
        Structure::service(name: str_repeat('a', times: 120));

        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn (): Service => Structure::service(name: str_repeat('a', times: 121))),
        );
    }

    public function testADescriptionLongerThan2000CharactersIsRejected(): void
    {
        self::assertSame(
            ['description' => ['code' => 'validation.tooLong', 'params' => ['max' => 2000]]],
            ThrownFieldErrors::of(
                static fn (): Service => new Service('Choir', description: str_repeat('a', times: 2001)),
            ),
        );
    }

    public function testABadNameAndABadDescriptionAreRejectedTogether(): void
    {
        self::assertSame(
            [
                'name' => ['code' => 'services.nameRequired'],
                'description' => ['code' => 'validation.tooLong', 'params' => ['max' => 2000]],
            ],
            ThrownFieldErrors::of(static fn (): Service => new Service(' ', description: str_repeat('a', times: 2001))),
        );
    }
}
