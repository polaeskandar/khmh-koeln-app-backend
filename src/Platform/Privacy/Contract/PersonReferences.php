<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Contract;

use App\Platform\Privacy\Model\ErasedPerson;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface PersonReferences
{
    public const string TAG = 'app.person_references';

    /**
     * @return array<string, int>
     */
    public function forgetPerson(ErasedPerson $person): array;
}
