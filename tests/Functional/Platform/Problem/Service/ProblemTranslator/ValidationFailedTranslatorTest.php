<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidationFailedTranslatorTest extends ApiTestCase
{
    public function testAMissingFieldGetsTheCodeOfItsConstraint(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload');

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'services.nameRequired');
    }

    public function testATooLongValueGetsItsLimitButIsNotEchoed(): void
    {
        $response = $this->jsonRequest('POST', '/api/test/problems/payload', [
            'name' => str_repeat('Kirollos ', times: 14),
        ]);

        $this->assertFieldError('name', 'validation.tooLong', ['limit' => 120, 'min' => 2, 'max' => 120]);
        self::assertStringNotContainsString('Kirollos', (string) $response->getContent());
    }

    public function testOnlyTheFirstViolationOfAFieldIsReported(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload', ['name' => '']);

        $this->assertFieldError('name', 'services.nameRequired');
    }

    public function testAListItemIsReportedByItsDotPath(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload', [
            'name' => 'Choir',
            'materials' => [
                ['url' => 'https://example.org'],
                ['url' => 'https://example.org/a'],
                ['url' => 'not a url'],
            ],
        ]);

        $this->assertFieldError('materials.2.url', 'materials.url');
    }

    public function testAValueOfTheWrongTypeIsReported(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload', ['name' => 'Choir', 'capacity' => 'many']);

        $this->assertFieldError('capacity', 'validation.invalidType');
    }

    #[DataProvider('schedules')]
    public function testADateAndTimeGetTheCalendarCodes(
        string $date,
        string $time,
        string $dateCode,
        string $timeCode,
    ): void {
        $this->jsonRequest('POST', '/api/test/problems/schedule', ['date' => $date, 'time' => $time]);

        $this->assertFieldError('date', $dateCode);
        $this->assertFieldError('time', $timeCode);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function schedules(): iterable
    {
        yield 'empty' => ['', '', 'validation.chooseDate', 'validation.chooseTime'];
        yield 'not on the calendar' => ['2026-02-30', '24:00', 'validation.invalidDate', 'validation.invalidTime'];
        yield 'unpadded' => ['2026-2-3', '7:5', 'validation.invalidDate', 'validation.invalidTime'];
    }

    public function testAValidDateAndTimeAreAccepted(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/schedule', ['date' => '2026-11-08', 'time' => '18:30']);

        self::assertResponseIsSuccessful();
    }
}
