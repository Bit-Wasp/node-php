<?php

namespace BitWasp\Bitcoin\Node\Chain;

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
     * @var ChainUtxo[]
     */
    public $parsedUtxos = [];

    /**
     * @var ChainUtxo[]
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
}
