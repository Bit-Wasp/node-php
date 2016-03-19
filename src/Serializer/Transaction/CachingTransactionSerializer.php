<?php

namespace BitWasp\Bitcoin\Node\Serializer\Transaction;

use BitWasp\Bitcoin\Serializer\Transaction\OldTransactionSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\TransactionSerializerInterface;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;
use BitWasp\Buffertools\Parser;

class CachingTransactionSerializer implements TransactionSerializerInterface
{
    /**
     * @var OldTransactionSerializer
     */
    private $txSerializer;

    /**
     * @var \SplObjectStorage
     */
    private $storage;

    /**
     * CachingTransactionSerializer constructor.
     */
    public function __construct()
    {
        $this->txSerializer = new OldTransactionSerializer();
        $this->storage = new \SplObjectStorage();
    }

    /**
     * @param TransactionInterface $tx
     * @return BufferInterface
     */
    public function serialize(TransactionInterface $tx)
    {
        if ($this->storage->contains($tx)) {
            return $this->storage[$tx];
        } else {
            $serialized = $this->txSerializer->serialize($tx);
            $this->storage->attach($tx);
            return $serialized;
        }
    }

    /**
     * @param string|BufferInterface $data
     * @return \BitWasp\Bitcoin\Transaction\TransactionInterface
     */
    public function parse($data)
    {
        $parser = new Parser($data);
        $buffer = $parser->getBuffer();
        $parsed = $this->txSerializer->parse($buffer);
        $this->storage->attach($parsed, $buffer);
        return $parsed;
    }

    /**
     * @param Parser $parser
     * @return \BitWasp\Bitcoin\Transaction\TransactionInterface
     */
    public function fromParser(Parser $parser)
    {
        $parsed = $this->txSerializer->fromParser($parser);
        $this->storage->attach($parsed, $parsed->getBuffer());
        return $parsed;
    }
}
