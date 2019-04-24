<?php

$target = '127.0.0.1';
$constantName = '%constant-name%';
$constantValue = constant($constantName);

$testResults = null;
$results = '';
$error = '';
$errNo = null;
$errStr = null;
$starttime = microtime(true);
$fp = fsockopen($target, $constantValue, $errNo, $errStr, 10);
if (!$fp) {
    $error = 'Unable to establish a socket connection to {' . $target . ':' . $constantValue . '}.';
    $testResults = false;
} else {
    $header = fgets($fp, 2048);
    if (stristr($header, 'ssh') === false) {
        $error = 'Established a connection but server does not appear to be an SSH server. Header was: {' . $header . '}.';
        $testResults = false;
    } else {
        $results = 'Established a connection with an SSH server! Header was: {' . $header . '}.';
        $testResults = true;
    }
}
$endtime = microtime(true);

file_put_contents(__DIR__ . '/sshtest_results.json', json_encode([
    'results' => $results,
    'error' => $error,
    'elapsed' => $endtime - $starttime,
]));
