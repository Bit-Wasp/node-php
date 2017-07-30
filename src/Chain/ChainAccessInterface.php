<?php

namespace BitWasp\Bitcoin\Node\Chain;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Index\Transactions;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;

interface ChainAccessInterface
{

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash);

    /**
     * @param BufferInterface $hash
     * @return BlockInterface
     */
    public function fetchBlock(BufferInterface $hash);

    /**
     * @param Transactions $txIndex
     * @param BufferInterface $txid
     * @return TransactionInterface
     */
    public function fetchTransaction(Transactions $txIndex, BufferInterface $txid);

    /**
     * @param int $height
     * @return BlockIndexInterface
     */
    public function fetchAncestor($height);
}
