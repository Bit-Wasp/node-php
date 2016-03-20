<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainInterface;
use BitWasp\Bitcoin\Node\Chain\Forks;
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
     * @param ChainInterface $chain
     * @param BlockIndexInterface $index
     * @param BlockIndexInterface $prevIndex
     * @param Forks $forks
     * @return $this
     */
    public function checkContextual(ChainInterface $chain, BlockIndexInterface $index, BlockIndexInterface $prevIndex, Forks $forks)
    {
        $work = $this->consensus->getWorkRequired($chain, $prevIndex);

        $header = $index->getHeader();
        if ($this->math->cmp($header->getBits()->getInt(), $work) != 0) {
            throw new \RuntimeException('Headers::CheckContextual(): invalid proof of work : ' . $header->getBits()->getInt() . '? ' . $work);
        }

        if ($this->math->cmp($header->getVersion(), $forks->getMajorityVersion()) < 0) {
            throw new \RuntimeException('Rejected version');
        }

        return $this;
    }
}
