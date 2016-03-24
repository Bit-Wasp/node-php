<?php

namespace BitWasp\Bitcoin\Tests\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainCache;
use BitWasp\Bitcoin\Tests\Node\BitcoinNodeTest;
use BitWasp\Buffertools\Buffer;

class ChainCacheTest extends BitcoinNodeTest
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage ChainCache: index at this height (0) not known
     */
    public function testChainCacheBadHeight()
    {
        $cache = new ChainCache([]);
        $cache->getHash(0);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Hash not found
     */
    public function testChainCacheBadHash()
    {
        $cache = new ChainCache([]);
        $cache->getHeight(new Buffer());
    }

    public function testLookup()
    {
        $hash = str_pad("", 32, "\x01");
        $buffer = new Buffer($hash);
        $height = 0;
        $cache = new ChainCache([$hash]);

        try {
            $lookup = $cache->getHash($height);
            $this->assertEquals($hash, $lookup->getBinary());
            $this->assertEquals($height, $cache->getHeight($buffer));

            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        $this->assertTrue($result, 'cache item exists');
    }

    public function testCount()
    {
        $hash = str_pad("", 32, "\x01");
        $cache = new ChainCache([$hash]);

        $this->assertEquals(1, count($cache));
    }

    public function testAddingToCache()
    {
        $hash = str_pad("", 32, "\x01");
        $cache = new ChainCache([$hash]);

        $next = new BlockIndex(
            new Buffer(str_pad("", 32, "\x41")),
            1,
            '1',
            new BlockHeader(
                1,
                new Buffer($hash),
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $cache->add($next);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage ChainCache: New BlockIndex does not refer to last
     */
    public function testFailsAddingToCache()
    {
        $hash = str_pad("", 32, "\x01");
        $another = str_pad("", 32, "\x80");
        $cache = new ChainCache([$hash]);

        $next = new BlockIndex(
            new Buffer(str_pad("", 32, "\x41")),
            1,
            '1',
            new BlockHeader(
                1,
                new Buffer($another),
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $cache->add($next);
    }
}
