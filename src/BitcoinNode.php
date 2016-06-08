<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Chain\ProofOfWork;
use BitWasp\Bitcoin\Node\Chain\ChainContainer;
use BitWasp\Bitcoin\Node\Chain\Chains;
use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\ChainView;
use BitWasp\Bitcoin\Node\Db\DbInterface;
use Evenement\EventEmitter;
use Packaged\Config\ConfigProviderInterface;

class BitcoinNode extends EventEmitter implements NodeInterface
{
    /**
     * @var DbInterface
     */
    private $db;

    /**
     * @var Index\Blocks
     */
    protected $blocks;

    /**
     * @var Index\Headers
     */
    protected $headers;

    /**
     * @var Index\Transactions
     */
    protected $transactions;

    /**
     * @var ChainsInterface
     */
    protected $chains;

    /**
     * BetterNode constructor.
     * @param ConfigProviderInterface $config
     * @param ParamsInterface $params
     * @param DbInterface $db
     */
    public function __construct(ConfigProviderInterface $config, ParamsInterface $params, DbInterface $db)
    {
        $math = Bitcoin::getMath();
        $adapter = Bitcoin::getEcAdapter($math);

        $this->chains = new ChainContainer($math, $params);
        $consensus = new Consensus($math, $params);

        $pow = new ProofOfWork($math, $params);
        $this->headers = new Index\Headers($db, $adapter, $this->chains, $consensus, $pow);
        $this->blocks = new Index\Blocks($db, $config, $adapter, $this->chains, $consensus);
        $this->transactions = new Index\Transactions($db);

        $genesis = $params->getGenesisBlock();
        $this->headers->init($genesis->getHeader());
        $this->blocks->init($genesis);

        $this->db = $db;
        $segments = $this->db->fetchChainSegments();
        foreach ($segments as $segment) {
            $this->chains->addSegment($segment);
        }

        $this->chains->initialize($this->db);
    }

    /**
     * @return void
     */
    public function stop()
    {
        $this->db->stop();
    }

    /**
     * @return Index\Transactions
     */
    public function transactions()
    {
        return $this->transactions;
    }

    /**
     * @return Index\Headers
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * @return Index\Blocks
     */
    public function blocks()
    {
        return $this->blocks;
    }

    /**
     * @return ChainView
     */
    public function chain()
    {
        return $this->chains->best(Bitcoin::getMath());
    }

    /**
     * @return ChainsInterface
     */
    public function chains()
    {
        return $this->chains;
    }
}
