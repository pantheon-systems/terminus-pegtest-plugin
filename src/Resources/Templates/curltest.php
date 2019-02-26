<?php

$url = '%url%';
$constantName = '%constant-name%';
$constantValue = constant($constantName);
if (filter_var($url, FILTER_VALIDATE_IP) === false) {
    $host = parse_url($url, PHP_URL_HOST);
} else {
    $host = $url;
}
$resolveHost = [
    sprintf('%s:%d:%s', $host, $constantValue, '127.0.0.1'),
];

$ch = curl_init();
$curlOpts = [
    'CURLOPT_CONNECTTIMEOUT' => 5,
    'CURLOPT_TIMEOUT' => 30,
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_SSL_VERIFYPEER' => true,
    'CURLOPT_VERBOSE' => false,
    'CURLOPT_URL' => $url,
    'CURLOPT_PORT' => $constantValue,
    'CURLOPT_RESOLVE' => $resolveHost,
];

$preparedCurlOpts = [];
array_walk($curlOpts, function ($v, $k) use (&$preparedCurlOpts) {
    $preparedCurlOpts[constant($k)] = $v;
});

curl_setopt_array($ch, $preparedCurlOpts);

$starttime = microtime(true);
$results = curl_exec($ch);
$endtime = microtime(true);
$error = curl_error($ch);

file_put_contents(__DIR__ . '/curltest_results.json', json_encode([
    'rawCurlOpts' => $curlOpts,
    'preparedCurlOpts' => $preparedCurlOpts,
    'results' => $results,
    'error' => $error,
    'elapsed' => $endtime - $starttime,
]));
