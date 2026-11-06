<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\Database;

final readonly class SundaySchool
{
    public static function withStagesIn(Database $database): Service
    {
        $service = Structure::service('Sunday School');
        $old = Structure::stage($service, 'Old Class', position: 2);
        $old->archive(Structure::now());

        $database->persisted(
            $service,
            Structure::stage($service, '1st Class', position: 0),
            Structure::stage($service, '2nd Class', position: 1),
            $old,
        );

        return $service;
    }

    public static function stagesPath(Service $service): string
    {
        return '/api/services/' . $service->id()->toRfc4122() . '/stages';
    }
}
