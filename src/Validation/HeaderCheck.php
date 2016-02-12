<?php

namespace BitWasp\Bitcoin\Node\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Consensus;
use BitWasp\Buffertools\BufferInterface;

class HeaderCheck implements HeaderCheckInterface
{

    /**
     * @var Consensus
     */
    private $consensus;

    /**
     * @var \BitWasp\Bitcoin\Math\Math
     */
    private $math;

    /**
     * @var ProofOfWork
     */
    private $pow;

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
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @param bool $checkPow
     * @return $this
     */
    public function check(BufferInterface $hash, BlockHeaderInterface $header, $checkPow = true)
    {
        try {
            if ($checkPow) {
                $this->pow->check($hash, $header->getBits()->getInt());
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Headers::check() - failed validating header proof-of-work');
        }

        return $this;
    }

    /**
     * @param ChainStateInterface $state
     * @param BlockHeaderInterface $header
     * @return $this
     */
    public function checkContextual(ChainStateInterface $state, BlockHeaderInterface $header)
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
     * @param BlockIndexInterface $prevIndex
     * @param BufferInterface $hash
     * @param BlockHeaderInterface $header
     * @return BlockIndexInterface
     */
    public function makeIndex(BlockIndexInterface $prevIndex, BufferInterface $hash, BlockHeaderInterface $header)
    {
        return new BlockIndex(
            $hash,
            $this->math->add($prevIndex->getHeight(), 1),
            $this->math->add($this->pow->getWork($header->getBits()), $prevIndex->getWork()),
            $header
        );
    }
}
