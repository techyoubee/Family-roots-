<?php

/**
 * Family Roots - local keep-warm helper.
 *
 * Requests the lightweight /health endpoint once so Apache, PHP/opcache and
 * MySQL pages stay resident on memory-constrained Windows machines. This is a
 * *development-only workaround* for Windows trimming working sets; it is not a
 * fix for an application bug and is not needed on a normal production host.
 *
 * Intended to be called by the Windows scheduled task registered by
 * scripts/register-keep-warm.bat, e.g. every 3 minutes.
 *
 * It never logs credentials or query details and does not modify data.
 */

$url = 'http://127.0.0.1/family-root/public/index.php?r=/health';

$context = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'timeout'         => 15,
        'ignore_errors'   => true,
        'header'          => "User-Agent: family-roots-keep-warm\r\n",
    ],
]);

$started = microtime(true);
$body = @file_get_contents($url, false, $context);
$ms = (microtime(true) - $started) * 1000;

$statusLine = $http_response_header[0] ?? '(no response)';
$ok = $body !== false && strpos($statusLine, ' 200 ') !== false;

// One short line per run; append-only, in the existing storage/logs folder.
$log = dirname(__DIR__) . '/storage/logs/keep-warm.log';
@file_put_contents(
    $log,
    '[' . date('Y-m-d H:i:s') . '] ' . ($ok ? 'OK' : 'FAIL') . ' ' . round($ms) . 'ms ' . $statusLine . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

exit($ok ? 0 : 1);
