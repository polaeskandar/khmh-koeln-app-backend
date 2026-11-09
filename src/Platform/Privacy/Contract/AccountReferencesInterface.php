<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

#[AutoconfigureTag(self::TAG)]
interface AccountReferencesInterface
{
    public const string TAG = 'app.account_references';

    public function forget(Uuid $accountId): int;
}
