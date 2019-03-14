<?php

$constantName = '%constant-name%';
$constantValue = constant($constantName);
$command = sprintf('openssl s_client -connect 127.0.0.1:%s -showcerts', $constantValue);
$result = 0;

$starttime = microtime(true);
ob_start();
passthru($command, $result);
$results = ob_get_contents();
ob_end_clean();
$endtime = microtime(true);

file_put_contents(__DIR__ . '/showcerts_results.json', json_encode([
    'results' => $results,
    'error' => null,
    'elapsed' => $endtime - $starttme,
]));
