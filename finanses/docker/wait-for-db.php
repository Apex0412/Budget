<?php
require __DIR__ . '/../vendor/autoload.php';

use Finanses\Config;
use Finanses\Database;

Config::load(dirname(__DIR__));

$maxAttempts = 40;
$attempt = 0;
$delaySeconds = 3;

while ($attempt < $maxAttempts) {
    try {
        Database::connection();
        fwrite(STDOUT, "[wait-for-db] connection established\n");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDOUT, sprintf("[wait-for-db] database unavailable (%s). retrying...\n", $e->getMessage()));
        $attempt++;
        sleep($delaySeconds);
    }
}

fwrite(STDERR, "[wait-for-db] database connection timed out\n");
exit(1);
