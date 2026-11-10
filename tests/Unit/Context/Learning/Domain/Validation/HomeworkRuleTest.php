<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Learning\Domain\Validation;

use App\Context\Learning\Domain\Validation\HomeworkRule;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class HomeworkRuleTest extends TestCase
{
    public function testATitleHasOneTo120Characters(): void
    {
        HomeworkRule::ensureTitle(str_repeat('ä', times: 120));

        self::assertSame(
            ['title' => ['code' => 'homework.titleRequired']],
            ThrownFieldErrors::of(static fn () => HomeworkRule::ensureTitle('')),
        );

        self::assertSame(
            ['title' => ['code' => 'validation.tooLong', 'params' => ['max' => 120]]],
            ThrownFieldErrors::of(static fn () => HomeworkRule::ensureTitle(str_repeat('a', times: 121))),
        );
    }

    public function testInstructionsHaveOneTo2000Characters(): void
    {
        HomeworkRule::ensureInstructions(str_repeat('ä', times: 2000));

        self::assertSame(
            ['instructions' => ['code' => 'homework.instructionsRequired']],
            ThrownFieldErrors::of(static fn () => HomeworkRule::ensureInstructions('')),
        );

        self::assertSame(
            ['instructions' => ['code' => 'homework.instructionsTooLong']],
            ThrownFieldErrors::of(static fn () => HomeworkRule::ensureInstructions(str_repeat('a', times: 2001))),
        );
    }

    public function testANewDueDateIsNotBeforeTheLessonButAKeptOneMayBe(): void
    {
        $teachingDate = LocalDate::of('2026-11-08');
        $early = LocalDate::of('2026-11-07');

        HomeworkRule::ensureDueDate(null, $early, $teachingDate);
        HomeworkRule::ensureDueDate($teachingDate, null, $teachingDate);
        HomeworkRule::ensureDueDate($early, LocalDate::of('2026-11-07'), $teachingDate);

        self::assertSame(
            ['dueDate' => ['code' => 'homework.dueBeforeLesson']],
            ThrownFieldErrors::of(static fn () => HomeworkRule::ensureDueDate($early, null, $teachingDate)),
        );
    }
}
