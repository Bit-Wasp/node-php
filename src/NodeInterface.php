<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Node\Chain\ChainsInterface;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\ChainView;
use Evenement\EventEmitterInterface;

interface NodeInterface extends EventEmitterInterface
{
    public function stop();

    /**
     * @return \BitWasp\Bitcoin\Node\Index\Headers
     */
    public function headers();

    /**
     * @return \BitWasp\Bitcoin\Node\Index\Blocks
     */
    public function blocks();

    /**
     * @return \BitWasp\Bitcoin\Node\Index\Transactions
     */
    public function transactions();

    /**
     * @return ChainView
     */
    public function chain();

    /**
     * @return ChainsInterface
     */
    public function chains();

}
