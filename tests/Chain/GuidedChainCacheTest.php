<?php

namespace BitWasp\Bitcoin\Tests\Node\Chain;

use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainCache;
use BitWasp\Bitcoin\Node\Chain\GuidedChainCache;
use BitWasp\Bitcoin\Tests\Node\BitcoinNodeTest;
use BitWasp\Buffertools\Buffer;

class GuidedChainCacheTest extends BitcoinNodeTest
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Parent cache must be initialized
     */
    public function testParentChainEmpty()
    {
        $cache = new ChainCache([]);
        new GuidedChainCache($cache, 0);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cap exceeds parent cache size
     */
    public function testCapExceedsParent()
    {
        $hashStr = str_pad('', 32, "\x41");
        $cache = new ChainCache([$hashStr]);
        new GuidedChainCache($cache, 5);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCapHeightInvalid()
    {
        $cache = new ChainCache([]);
        new GuidedChainCache($cache, 1);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage GuidedChainCache: BlockIndex does not match this Chain
     */
    public function testAddWrongIndex()
    {
        $hash1 = str_pad('', 32, "\x41");
        $hash2 = str_pad('', 32, "\x42");
        $cache = new ChainCache([
            $hash1,
            $hash2
        ]);

        $guided = new GuidedChainCache($cache, 0);

        $wrongNext = new Buffer(str_pad('', 32, "\x90"));
        $next = new BlockIndex(
            $wrongNext,
            1,
            1,
            new BlockHeader(
                0,
                $wrongNext,
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $guided->add($next);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Hash not found
     */
    public function testBadHash()
    {
        $hashStr = str_pad('', 32, "\x41");
        $badBuf = new Buffer(str_pad('', 32, "\x49"));
        $cache = new ChainCache([$hashStr]);
        $guided = new GuidedChainCache($cache, 1);
        $guided->getHeight($badBuf);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage GuidedChainCache: index at this height (99) not known
     */
    public function testBadHeight()
    {
        $hashStr = str_pad('', 32, "\x41");
        $cache = new ChainCache([$hashStr]);
        $guided = new GuidedChainCache($cache, 1);
        $guided->getHash(99);
    }

    public function testCapHeight()
    {
        $hashStr = str_pad('', 32, "\x41");
        $cache = new ChainCache([$hashStr]);
        $guided = new GuidedChainCache($cache, 0);
        $this->assertEquals(1, count($guided));
    }

    public function testContainsHashWhenBeyondCap()
    {
        $hash1 = str_pad('', 32, "\x41");
        $hash2 = str_pad('', 32, "\x42");
        $hash3 = str_pad('', 32, "\x43");
        $cache = new ChainCache([$hash1, $hash2, $hash3]);
        $guided = new GuidedChainCache($cache, 1);
        $this->assertFalse($guided->containsHash(new Buffer($hash3)));
    }

    public function testWithOneSuccess()
    {
        $hash1 = str_pad('', 32, "\x41");
        $hash2 = str_pad('', 32, "\x42");
        $hash1Buf = new Buffer($hash1, 32);
        $hash2Buf = new Buffer($hash2, 32);
        $cache = new ChainCache([
            $hash1,
            $hash2
        ]);

        $guided = new GuidedChainCache($cache, 0);
        $this->assertEquals(1, count($guided));
        $this->assertTrue($guided->containsHash($hash1Buf));
        $this->assertEquals($hash1, $guided->getHash(0)->getBinary());
        $this->assertEquals(0, $guided->getHeight($hash1Buf));

        $next = new BlockIndex(
            $hash2Buf,
            1,
            1,
            new BlockHeader(
                0,
                $hash1Buf,
                new Buffer('', 32),
                0,
                new Buffer(),
                0
            )
        );

        $guided->add($next);
        $this->assertEquals(2, count($guided));
        $this->assertTrue($guided->containsHash($hash2Buf));
        $this->assertEquals($hash2, $guided->getHash(1)->getBinary());
        $this->assertEquals(1, $guided->getHeight($hash2Buf));
    }
}
