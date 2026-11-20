<?php

/**
 * Runs every test suite that needs no old plugin sources:  php tests/run.php
 * (The golden-master comparison against the old plugins is separate:
 *  php tests/golden.php <old-login-dir> <old-page-dir> [iterations] [seed])
 */

$status = 0;

foreach (['manifest', 'unit', 'extension', 'installer'] as $suite) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $suite . '.php'), $code);
    $status |= $code;
}

exit($status === 0 ? 0 : 1);
