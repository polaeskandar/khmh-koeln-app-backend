<?php

declare(strict_types=1);

$files = glob(dirname(__DIR__) . '/var/cache/prod/*.preload.php');

foreach ($files === false ? [] : $files as $file) {
    require $file;
}
