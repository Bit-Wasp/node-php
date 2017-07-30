<?php

namespace BitWasp\Bitcoin\Tests\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderFactory;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Tests\Node\BitcoinNodeTest;
use BitWasp\Buffertools\Buffer;

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

    public function testIsNext()
    {
        $first = new BlockIndex(
            new Buffer('aa', 32),
            0,
            0,
            new BlockHeader(
                0,
                new Buffer('', 32),
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $nextGood = new BlockIndex(
            new Buffer('bb'),
            1, /* height + 1 */
            0,
            new BlockHeader(
                0,
                new Buffer('aa', 32), /* prevBlock matches */
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $nextBadHeight = new BlockIndex(
            Buffer::hex('bc', 32),
            222, /* bad height */
            0,
            new BlockHeader(
                0,
                new Buffer('aa', 32), /* prevBlock matches */
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $nextBadHash = new BlockIndex(
            Buffer::hex('bd', 32),
            1, /* height matches */
            0,
            new BlockHeader(
                0,
                new Buffer('22', 32), /* prevBlock doesn't match */
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $this->assertFalse($first->isNext($nextBadHash));
        $this->assertFalse($first->isNext($nextBadHeight));
        $this->assertTrue($first->isNext($nextGood));
    }
}
