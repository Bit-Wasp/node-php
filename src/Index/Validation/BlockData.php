<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Utxo\Utxo;

class BlockData
{
    /**
     * @var OutPointInterface[]
     */
    public $requiredOutpoints = [];

    /**
     * @var Utxo[]
     */
    public $parsedUtxos = [];

    /**
     * @var Utxo[]
     */
    public $remainingNew = [];

    /**
     * @var UtxoView
     */
    public $utxoView;

    /**
     * @var HashStorage
     */
    public $hashStorage;

    /**
     * @var \GMP
     */
    public $nFees;

    /**
     * @var int
     */
    public $nSigOps = 0;

    public function __construct()
    {
        $this->nFees = gmp_init(0);
    }
}
