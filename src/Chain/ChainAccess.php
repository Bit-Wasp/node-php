<?php

namespace BitWasp\Bitcoin\Node\Chain;


use BitWasp\Bitcoin\Node\Db\DbInterface;
use BitWasp\Bitcoin\Node\Index\Transactions;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\BufferInterface;

class ChainAccess implements ChainAccessInterface
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var ChainViewInterface
     */
    private $view;

    /**
     * ChainAccess constructor.
     * @param DbInterface $db
     * @param ChainViewInterface $view
     */
    public function __construct(DbInterface $db, ChainViewInterface $view)
    {
        $this->db = $db;
        $this->view = $view;
    }

    /**
     * @param int $height
     * @return BlockIndexInterface
     */
    public function fetchAncestor($height)
    {
        return $this->fetchIndex($this->view->getHashFromHeight($height));
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        if (!$this->view->containsHash($hash)) {
            throw new \RuntimeException('Index by this hash not known');
        }

        return $this->db->fetchIndex($hash);
    }

    /**
     * @param Transactions $txIndex
     * @param BufferInterface $txid
     * @return TransactionInterface
     */
    public function fetchTransaction(Transactions $txIndex, BufferInterface $txid)
    {
        return $txIndex->fetch($this->view->getIndex()->getHash(), $txid);
    }
}