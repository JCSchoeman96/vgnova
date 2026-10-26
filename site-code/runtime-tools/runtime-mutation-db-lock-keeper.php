<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime-mutation-database.php';

if (PHP_SAPI !== 'cli') {
    exit(70);
}

exit(VgRuntimeMutationDatabase::keeperMain(array_slice($argv, 1)));
