<?php

namespace BitWasp\Bitcoin\Node\Routine;


use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Consensus;

class HeaderCheck implements HeaderCheckInterface
{

    /**
     * @param Consensus $consensus
     * @param EcAdapterInterface $ecAdapter
     * @param ProofOfWork $proofOfWork
     */
    public function __construct(
        Consensus $consensus,
        EcAdapterInterface $ecAdapter,
        ProofOfWork $proofOfWork
    ) {
        $this->consensus = $consensus;
        $this->math = $ecAdapter->getMath();
        $this->pow = $proofOfWork;
    }

    /**
     * @param BlockHeaderInterface $header
     * @param bool|true $checkPow
     * @return $this
     */
    public function check(BlockHeaderInterface $header, $checkPow = true)
    {
        try {
            if ($checkPow) {
                $this->pow->checkHeader($header);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Headers::check() - failed validating the header');
        }

        return $this;
    }

    /**
     * @param ChainState $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainState $state, BlockHeaderInterface $header)
    {
        $work = $this->consensus->getWorkRequired($state);
        if ($this->math->cmp($header->getBits()->getInt(), $work) != 0) {
            throw new \RuntimeException('Headers::CheckContextual(): invalid proof of work : ' . $header->getBits()->getInt() . '? ' . $work);
        }

        // check timestamp
        // reject block version 1 when 95% has upgraded
        // reject block version 2 when 95% has upgraded

        return $this;
    }

    /**
     * @param BlockIndex $prevIndex
     * @param BlockHeaderInterface $header
     * @return BlockIndex
     */
    public function makeIndex(BlockIndex $prevIndex, BlockHeaderInterface $header)
    {
        return new BlockIndex(
            $header->getHash()->getHex(),
            $this->math->add($prevIndex->getHeight(), 1),
            $this->math->add($this->pow->getWork($header->getBits()), $prevIndex->getWork()),
            $header
        );
    }
}