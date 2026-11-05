<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

#[AutoconfigureTag(self::TAG)]
interface PersonReferences
{
    public const string TAG = 'app.person_references';

    public function forgetPerson(Uuid $personId): int;
}
