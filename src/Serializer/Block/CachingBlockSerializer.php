<?php

namespace BitWasp\Bitcoin\Node\Serializer\Block;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Serializer\Block\BlockHeaderSerializer;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializer;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\TransactionSerializerInterface;
use BitWasp\Buffertools\BufferInterface;
use BitWasp\Buffertools\Parser;

class CachingBlockSerializer implements BlockSerializerInterface
{
    /**
     * @var BlockSerializer
     */
    private $blockSerializer;

    /**
     * @var \SplObjectStorage
     */
    private $storage;

    /**
     * CachingBlockSerializer constructor.
     * @param Math $math
     * @param BlockHeaderSerializer $headerSerializer
     * @param TransactionSerializerInterface $transactionSerializer
     */
    public function __construct(Math $math, BlockHeaderSerializer $headerSerializer, TransactionSerializerInterface $transactionSerializer)
    {
        $this->blockSerializer = new BlockSerializer($math, $headerSerializer, $transactionSerializer);
        $this->storage = new \SplObjectStorage();
    }

    /**
     * @param BlockInterface $block
     * @return BufferInterface
     */
    public function serialize(BlockInterface $block)
    {
        if ($this->storage->contains($block)) {
            return $this->storage[$block];
        } else {
            $serialized = $this->blockSerializer->serialize($block);
            $this->storage->attach($block, $serialized);
            return $serialized;
        }
    }

    /**
     * @param string|BufferInterface $data
     * @return \BitWasp\Bitcoin\Block\BlockInterface
     */
    public function parse($data)
    {
        $parser = new Parser($data);
        $buffer = $parser->getBuffer();
        $parsed = $this->blockSerializer->parse($buffer);
        $this->storage->attach($parsed, $buffer);
        return $parsed;
    }

    /**
     * @param Parser $parser
     * @return \BitWasp\Bitcoin\Block\BlockInterface
     */
    public function fromParser(Parser $parser)
    {
        $parsed = $this->blockSerializer->fromParser($parser);
        $this->storage->attach($parsed, $parsed->getBuffer());
        return $parsed;
    }
}
