<?php

namespace BitWasp\Bitcoin\Node\Serializer\Transaction;


use BitWasp\Buffertools\Parser;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\OutPointInterface;

class CachingOutPointSerializer implements OutPointSerializerInterface
{
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var OutPointSerializer
     */
    private $serializer;

    public function __construct()
    {
        $this->serializer = new OutPointSerializer();
    }

    /**
     * @param OutPointInterface $outpoint
     * @return mixed
     */
    public function serialize(OutPointInterface $outpoint)
    {
        $txid = $outpoint->getTxId()->getBinary() ;
        $vout = $outpoint->getVout();
        if (isset($this->cache[$txid . $vout])) {
            return $this->cache[$txid . $vout];
        } else {
            $buffer = $this->serializer->serialize($outpoint);
            $this->cache[$txid . $vout] = $buffer;
            return $buffer;
        }
    }

    public function parse($data)
    {
        return $this->fromParser(new Parser($data));
    }

    public function fromParser(Parser $parser)
    {
        $parsed = $this->serializer->fromParser($parser);
        $this->serialize($parsed);
        return $parsed;
    }
}