<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\Request;

use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Networking\Structure\Inventory;
use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Buffertools\BufferInterface;

class BlockRequest
{
    const DOWNLOAD_AMOUNT = 500;
    const MAX_IN_FLIGHT = 16;

    /**
     * @var array
     */
    private $inFlight = [];

    /**
     * @var BufferInterface|null
     */
    private $lastRequested;

    /**
     * @param ChainViewInterface $headerChain
     * @param BufferInterface $startHash
     * @throws \RuntimeException
     * @throws \Exception
     * @return Inventory[]
     */
    private function relativeNextInventory(ChainViewInterface $headerChain, BufferInterface $startHash)
    {
        if (!$headerChain->containsHash($startHash)) {
            throw new \RuntimeException('Hash not found in this chain');
        }
        
        $startHeight = $headerChain->getHeightFromHash($startHash) + 1;
        $stopHeight = min($startHeight + self::DOWNLOAD_AMOUNT, $headerChain->getIndex()->getHeight());
        $nInFlight = count($this->inFlight);

        $request = [];
        for ($i = $startHeight; $i < $stopHeight && $nInFlight < self::MAX_IN_FLIGHT; $i++) {
            $request[] = Inventory::block($headerChain->getHashFromHeight($i));
            $nInFlight++;
        }

        return $request;
    }

    /**
     * @param ChainViewInterface $state
     * @return Inventory[]
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function nextInventory(ChainViewInterface $state)
    {
        return $this->relativeNextInventory($state, $state->getLastBlock()->getHash());
    }

    /**
     * @param ChainViewInterface $headerChain
     * @param Peer $peer
     */
    public function requestNextBlocks(ChainViewInterface $headerChain, Peer $peer)
    {
        if (null === $this->lastRequested) {
            $nextData = $this->nextInventory($headerChain);
        } else {
            $nextData = $this->relativeNextInventory($headerChain, $this->lastRequested);
        }

        if (count($nextData) > 0) {
            $last = null;
            foreach ($nextData as $inv) {
                $last = $inv->getHash();
                $this->inFlight[$last->getBinary()] = 1;
            }
            $this->lastRequested = $last;
            $peer->getdata($nextData);
        }
    }

    /**
     * @param BufferInterface $hash
     * @return bool
     */
    public function isInFlight(BufferInterface $hash)
    {
        return array_key_exists($hash->getBinary(), $this->inFlight);
    }

    /**
     * @param BufferInterface $hash
     * @return $this
     */
    public function markReceived(BufferInterface $hash)
    {
        unset($this->inFlight[$hash->getBinary()]);
        return $this;
    }
}
