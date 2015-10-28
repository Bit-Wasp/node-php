<?php

namespace BitWasp\Bitcoin\Node\Thread;


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
     * @var
     */
    private $lastRequested;

    /**
     * @param ChainState $state
     * @param string $startHash
     * @return array
     */
    private function relativeNextInventory(ChainState $state, $startHash)
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
            $request[] = Inventory::block(Buffer::hex($best->getHashFromHeight($i)));
            $nInFlight++;
        }

        return $request;
    }

    /**
     * @param ChainState $state
     * @return Inventory[]
     */
    public function nextInventory(ChainState $state)
    {
        $last = $state->getLastBlock();

        return $this->relativeNextInventory($state, $last->getHash());
    }

    /**
     * @param ChainState $state
     * @param Peer $peer
     */
    public function requestNextBlocks(ChainState $state, Peer $peer)
    {
        if (is_null($this->lastRequested)) {
            $nextData = $this->nextInventory($state);
        } else {
            $nextData = $this->relativeNextInventory($state, $this->lastRequested);
        }

        $dataCount = count($nextData);
        if ($dataCount > 0) {
            $last = null;
            foreach ($nextData as $inv) {
                $last = $inv->getHash()->getHex();
                $this->inFlight[$last] = 1;
            }
            $this->lastRequested = $last;
            $peer->getdata($nextData);
        }
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isInFlight($hash)
    {
        return isset($this->inFlight[$hash]);
    }

    /**
     * @param string $hash
     * @return $this
     */
    public function markReceived($hash)
    {
        unset($this->inFlight[$hash]);
        return $this;
    }
}