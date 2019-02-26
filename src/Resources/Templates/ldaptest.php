<?php

$target = '127.0.0.1';
$constantName = '%constant-name%';
$constantValue = constant($constantName);
$useTLS = filter_var('%use-tls%', FILTER_VALIDATE_BOOLEAN);
$proto = '%proto%';
$bindDN = '%bind-dn%';
$bindPW = '%bind-password%';
$isAnonBinding = empty($bindDN);

$testResults = null;
$results = '';
$error = '';
$ldapAddress = sprintf(
    'ldap%s://%s:%s',
    $useTLS ? 's' : '',
    $target,
    $constantValue
);

$starttime = microtime(true);
if (!$ds = ldap_connect($ldapAddress)) {
    $testStatus = false;
    $error = "Unable to establish a connection with {$ldapAddress}.";
} else {
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $proto);
    if ($isAnonBinding) {
        if (!$bind = ldap_bind($ds)) {
            $testStatus = false;
            $error = "Unable to perform anonymous binding with {$ldapAddress}.";
        } else {
            ldap_unbind($ds);
            $testStatus = true;
            $results = "Successfully performed anonymous binding with {$ldapAddress}.";
        }
    } else {
        if (!$bind = ldap_bind($ds, $bindDN, $bindPW)) {
            $testStatus = false;
            $error = "Unable to authenticate to binding {$ldapAddress} using DN {$bindDN}.";
        } else {
            ldap_unbind($ds);
            $testStatus = true;
            $results = "Successfully performed authenticated binding with {$ldapAddress} using DN {$bindDN}.";
        }
    }
}
$endtime = microtime(true);

file_put_contents(__DIR__ . '/ldaptest_results.json', json_encode([
    'results' => $results,
    'error' => $error,
    'elapsed' => $endtime - $starttime,
]));
