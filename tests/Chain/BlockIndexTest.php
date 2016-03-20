<?php

namespace BitWasp\Bitcoin\Node\Tests\Chain;

use BitWasp\Bitcoin\Block\BlockHeaderFactory;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Tests\BitcoinNodeTest;

class BlockIndexTest extends BitcoinNodeTest
{
    public function testBlockIndex()
    {
        $headerHex = '0100000000000000000000000000000000000000000000000000000000000000000000003ba3edfd7a7b12b27ac72c3e67768f617fc81bc3888a51323a9fb8aa4b1e5e4a29ab5f49ffff001d1dac2b7c';
        $header = BlockHeaderFactory::fromHex($headerHex);

        $hash = $header->getHash();
        $height = 0;
        $work = 0;
        $index = new BlockIndex($hash, $height, $work, $header);

        $this->assertEquals($hash, $index->getHash());
        $this->assertEquals($height, $index->getHeight());
        $this->assertEquals($work, $index->getWork());
        $this->assertSame($header, $index->getHeader());

    }
}
