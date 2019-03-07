<?php

$target = '127.0.0.1';
$relay = '%relay-address%';
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
    $error = 'Unable to establish socket connection to {' . $target . ':' . $constantValue . '}.';
    $testtResults = false;
} else {
    $header = fgets($fp);
    $command = "HELO $relay\r\n";
    $printCommand = trim($command);
    fputs($fp, $command);
    $response = fgets($fp);
    $printResponse = trim($response);
    if ($response) {
        $results = "Successfully issued a HELO command to $relay. Command was: $printCommand; response was: $printResponse.";
        $testResults = true;
    } else {
        $error = "Unable to issue a HELO command: $printCommand. No results received.";
        $testResults = false;
    }
    fclose($fp);
}
$endtime = microtime(true);

file_put_contents(__DIR__ . '/smtptest_results.json', json_encode([
    'results' => $results,
    'error' => $error,
    'elapsed' => $endtime - $starttime,
]));
