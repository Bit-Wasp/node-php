<?php

namespace BitWasp\Bitcoin\Tests\Node\Index\Validation;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderFactory;
use BitWasp\Bitcoin\Chain\Params;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Bitcoin\Node\Index\Validation\HeaderCheck;
use BitWasp\Bitcoin\Tests\Node\BitcoinNodeTest;

class HeaderCheckTest extends BitcoinNodeTest
{
    private function getGenesisHex()
    {
        return '0100000000000000000000000000000000000000000000000000000000000000000000003ba3edfd7a7b12b27ac72c3e67768f617fc81bc3888a51323a9fb8aa4b1e5e4a29ab5f49ffff001d1dac2b7c';
    }

    /**
     * @param $expected
     * @param callable $callable
     */
    private function doAssertException($expected, callable $callable)
    {
        try {
            $callable();
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        $this->assertEquals($expected, $result);
    }

    public function assertNoException(callable $callable)
    {
        $this->doAssertException(true, $callable);
    }

    public function assertException(callable $callable)
    {
        $this->doAssertException(false, $callable);
    }
    
    public function testCreateInstance()
    {
        $ecAdapter = Bitcoin::getEcAdapter();
        $params = new Params($ecAdapter->getMath());
        $consensus = new Consensus($ecAdapter->getMath(), $params);
        $proofOfWork = new ProofOfWork($ecAdapter->getMath(), $params);
        $check = new HeaderCheck(
            $consensus,
            $ecAdapter,
            $proofOfWork
        );

        $header = BlockHeaderFactory::fromHex($this->getGenesisHex());
        $hash = $header->getHash();

        $this->assertNoException(function () use ($check, $hash, $header) {
            $check->check($hash, $header, false);
        });

        $this->assertNoException(function () use ($check, $hash, $header) {
            $check->check($hash, $header, true);
        });
        
        $badHeader = new BlockHeader(
            $header->getVersion(),
            $header->getPrevBlock(),
            $header->getMerkleRoot(),
            $header->getTimestamp(),
            $header->getBits(),
            1
        );

        $this->assertException(function () use ($check, $badHeader) {
            $hash = $badHeader->getHash();
            $check->check($hash, $badHeader, true);
        });
    }
}
