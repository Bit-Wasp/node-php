<?php

namespace BitWasp\Bitcoin\Node\Serializer\Transaction;

use BitWasp\Buffertools\Parser;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\OutPointInterface;

class CachingOutPointSerializer implements OutPointSerializerInterface
{

    /**
     * @var OutPointSerializer
     */
    private $serializer;
    private $parse = 0;
    private $serialize = 0;
    private $cached = 0;
    /**
     * @var \SplObjectStorage
     */
    private $cachedObj;
    /**
     * @var array
     */
    private $cachedStr = [];
    public function __construct()
    {
        $this->serializer = new OutPointSerializer();
        $this->cachedObj = new \SplObjectStorage();
    }

    public function stats()
    {
        return [
            'cached' => $this->cached, 'serialize' => $this->serialize, 'parse' => $this->parse
        ];
    }
    /**
     * @param OutPointInterface $outpoint
     * @return mixed
     */
    public function serialize(OutPointInterface $outpoint)
    {
        if (isset($this->cachedObj[$outpoint])) {
            $this->cached++;
            return $this->cachedObj[$outpoint];
        }

        $buffer = $this->serializer->serialize($outpoint);
        $this->cachedObj[$outpoint] = $buffer;
        $this->cachedStr[$buffer->getBinary()] = $outpoint;
        $this->serialize++;
        return $buffer;
    }

    /**
     * @param \BitWasp\Buffertools\BufferInterface|string $data
     * @return array|OutPointInterface
     */
    public function parse($data)
    {
        return $this->fromParser(new Parser($data));
    }

    /**
     * @param Parser $parser
     * @return array|OutPointInterface
     */
    public function fromParser(Parser $parser)
    {
        $buffer = $parser->getBuffer();
        if ($buffer->getSize() > 36) {
            $buffer = $buffer->slice(0, 36);
            if (isset($this->cachedStr[$buffer->getBinary()])) {
                $this->cached++;
                return $this->cachedStr[$buffer->getBinary()];
            }
        }

        $parsed = $this->serializer->fromParser($parser);
        $this->parse++;
        return $parsed;
    }
}
