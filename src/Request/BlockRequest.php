<?php

namespace BitWasp\Bitcoin\Node\Request;


use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Buffertools\Buffer;

class BlockRequest
{
    const DOWNLOAD_AMOUNT = 500;
    const MAX_IN_FLIGHT = 500;

    /**
     * @var array
     */
    private $inFlight = [];

    /**
     * @var Buffer|null
     */
    private $lastRequested;

    /**
     * @param ChainState $state
     * @param Buffer $startHash
     * @throws \RuntimeException
     * @throws \Exception
     * @return array
     */
    private function relativeNextInventory(ChainState $state, Buffer $startHash)
    {
        $best = $state->getChain();
        if (!$best->containsHash($startHash)) {
            throw new \RuntimeException('Hash not found in this chain');
        }

        $startHeight = $best->getHeightFromHash($startHash) + 1;
        $stopHeight = min($startHeight + self::DOWNLOAD_AMOUNT, $best->getIndex()->getHeight());
        $nInFlight = count($this->inFlight);

        $request = [];
        for ($i = $startHeight; $i < $stopHeight && $nInFlight < self::MAX_IN_FLIGHT; $i++) {
            $request[] = Inventory::block($best->getHashFromHeight($i));
            $nInFlight++;
        }

        return $request;
    }

    /**
     * @param ChainState $state
     * @return array
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function nextInventory(ChainState $state)
    {
        return $this->relativeNextInventory($state, Buffer::hex($state->getLastBlock()->getHash()));
    }

    /**
     * @param ChainState $state
     * @param Peer $peer
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function requestNextBlocks(ChainState $state, Peer $peer)
    {
        /** @var Inventory[] $nextData */
        if (null === $this->lastRequested) {
            $nextData = $this->nextInventory($state);
        } else {
            $nextData = $this->relativeNextInventory($state, $this->lastRequested);
        }

        if (count($nextData) > 0) {
            $last = null;
            foreach ($nextData as $inv) {
                $last = $inv->getHash();
                $this->inFlight[$last->getHex()] = 1;
            }
            $this->lastRequested = $last;
            $peer->getdata($nextData);
        }
    }

    /**
     * @param Buffer $hash
     * @return bool
     */
    public function isInFlight(Buffer $hash)
    {
        return array_key_exists($hash->getHex(), $this->inFlight);
    }

    /**
     * @param Buffer $hash
     * @return $this
     */
    public function markReceived(Buffer $hash)
    {
        unset($this->inFlight[$hash->getHex()]);
        return $this;
    }
}