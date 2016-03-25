<?php

require "vendor/autoload.php";

function parseKeys(&$results, $string) {

    list ($key, $value) = explode("=", $string);
    $results[$key] = $value;

}

// Connect to the TOR server using password authentication
$tc = new TorControl\TorControl(
    array(
        'hostname' => 'localhost',
        'port'     => 9051,
        'password' => 'testtesttesttesttesttest',
        'authmethod' => 1
    )
);

$tc->connect();
$tc->authenticate();

// Renew identity
//$res = $tc->executeCommand('SIGNAL NEWNYM');

$privateKey = 'NEW:BEST';

$res = $tc->executeCommand('ADD_ONION '.$privateKey.' Port=1234,127.0.0.1:1234');

$parsed = [];
parseKeys($parsed, $res[0]['message']);
parseKeys($parsed, $res[1]['message']);

echo $parsed['ServiceID'] . ".onion";

// Quit
$tc->quit();

