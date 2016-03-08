<?php

require "vendor/autoload.php";
use BitWasp\Buffertools\Buffer;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;

$scriptPubKey = ScriptFactory::create()
    ->op('OP_1')
    ->op('OP_EQUAL')
    ->getScript();

$scriptSig = ScriptFactory::create()
    ->op('OP_1')
    ->getScript();

$tx = TransactionFactory::build()
    ->input(Buffer::hex('0000000000000000000000000000000000000000000000000000000000000001'), 0, $scriptSig)
    ->output(1, new \BitWasp\Bitcoin\Script\Script())
    ->get();

$t1 = [
    'txid'=> 'a',
    'tx' => $tx->getHex(),
    'scripts' => [
        $scriptPubKey->getHex()
    ]
];

$msg = [
    'id' => 'asdfasdfsadf',
    'flags' => 0,
    'txs' => [
        $t1
    ]
];

echo 'sending';
$context = new \ZMQContext();
$push = $context->getSocket(\ZMQ::SOCKET_REQ);
$push->connect('tcp://127.0.0.1:6661');
$push->send(json_encode($msg));
$response = $push->recv();
echo $response.PHP_EOL;