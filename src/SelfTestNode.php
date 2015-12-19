<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\MerkleRoot;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Buffertools\Buffer;
use React\EventLoop\LoopInterface;

class SelfTestNode extends BitcoinNode
{

    /**
     * @var \Packaged\Config\ConfigProviderInterface
     */
    public $config;

    /**
     * @var Index\Blocks
     */
    public $blocks;

    /**
     * @var Index\Headers
     */
    public $headers;

    /**
     * @var Chains
     */
    public $chains;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var Index\UtxoIdx
     */
    public $utxo;

    /**
     * @var ProofOfWork
     */
    protected $pow;

    /**
     * @param ParamsInterface $params
     * @param LoopInterface $loop
     */
    public function __construct(ParamsInterface $params, LoopInterface $loop)
    {
        parent::__construct($params, $loop);
    }

   /**
     * @var ChainState
     */
    private $forkState;
    private $i = 0;

    /**
     *
     */
    public function start()
    {
        $this->loop->addPeriodicTimer(1, function () {
            echo "\n";

            $math = Bitcoin::getMath();
            $best = $this->chain();
            $height = $best->getChainIndex()->getHeight();
            echo "Height: $height\n";

            $testing = false;
            if ($this->i == 4 && $this->forkState == null) {
                echo "Induce fork first time\n";
                $testing = true;
                $nheight = 2;
                $hash = $best->getChain()->getChainCache()->getHash($nheight);
                $index = $this->headers->fetch($hash);
            } elseif ($this->i == 9 || $this->i == 10 || $this->i == 11) {
                echo "Induce by 3\n";
                $testing = true;
                $best = $this->forkState;
                $index = $best->getChainIndex();
                ;
            } elseif ($math->cmp($this->i, 14)>0 && $math->cmp($this->i, 30)<0) {
                echo "Induce fork by 10\n";
                $testing = true;
                $best = $this->forkState;
                $index = $best->getChainIndex();
                ;
            } else {
                echo "Elongate tip\n";
                $index = $best->getChainIndex();
            }

            $bestHeader = $index->getHeader();

            $tx = TransactionFactory::build()
                ->input(new Buffer('', 32), 0xffffffff, new Script(Buffer::hex('2b03b2ef051e4d696e656420627920416e74506f6f6c207573613117a9167c205674133e81220000ea700200')))
                ->payToAddress(50+mt_rand(2, 500000), AddressFactory::fromString('15HCzh8AoKRnTWMtmgAsT9TKUPrQ6oh9HQ'))
                ->get();

            $txs = new TransactionCollection([$tx]);
            $merkle = (new MerkleRoot($math, $txs))->calculateHash();
            $time = time();
            $found = false;

            for ($i = 0; $i < (2 << 32) && $found === false; $i++) {
                echo ".";

                $new = new BlockHeader(
                    1,
                    $index->getHash(),
                    $merkle,
                    $time,
                    $bestHeader->getBits(),
                    $i
                );

                try {
                    if ($this->pow->check($new->getHash(), $bestHeader->getBits()->getInt())) {

                        /** @var ChainState $state */
                        $state = null;
                        $this->headers->acceptBatch([$new], $state);
                        $this->chains->checkTips();

                        $found = true;
                        if ($testing) {
                            if ($height == 4) {
                                $this->forkState = $state;
                            }
                            echo "make sure we set the thing\n";
                        }
                        echo "Block found\n";
                    }
                } catch (\Exception $e) {
                    if ($e->getMessage() !== 'Hash doesn\'t match nBits') {
                        echo $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
                        die();
                    }

                    /* .... */
                }

            }
            $this->i++;

        });
    }
}
