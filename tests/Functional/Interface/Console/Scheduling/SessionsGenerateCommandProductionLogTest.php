<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Scheduling;

use App\Tests\Support\JsonBody;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SessionsGenerateCommandProductionLogTest extends TestCase
{
    public function testTheProductionSummaryGoesStraightToStderrOutsideTheErrorBuffer(): void
    {
        $handlers = JsonBody::asArray(
            JsonBody::valueAt(
                Yaml::parseFile(dirname(__DIR__, levels: 5) . '/config/packages/monolog.yaml'),
                ['when@prod', 'monolog', 'handlers'],
                'when@prod.monolog.handlers',
            ),
            'when@prod.monolog.handlers',
        );

        self::assertSame(
            [
                'type' => 'stream',
                'channels' => ['session_generator'],
                'path' => 'php://stderr',
                'level' => 'info',
                'formatter' => 'monolog.formatter.json',
            ],
            JsonBody::valueAt($handlers, ['session_generator'], 'session_generator'),
        );

        self::assertSame('fingers_crossed', JsonBody::valueAt($handlers, ['main', 'type'], 'main.type'));

        self::assertContains('!session_generator', JsonBody::asArray(
            JsonBody::valueAt($handlers, ['main', 'channels'], 'main.channels'),
            'main.channels',
        ));
    }
}
