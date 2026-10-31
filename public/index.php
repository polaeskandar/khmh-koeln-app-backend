<?php

declare(strict_types=1);

use App\Platform\Kernel\Service\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return Kernel::fromRuntimeContext(...);
