<?php

namespace BitWasp\Bitcoin\Node\State;


use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Node\Index\Blocks;

class PeerState extends AbstractState
{
    const ISDOWNLOAD = 'isDownload';
    const INDEXBESTKNOWNBLOCK = 'indexBestKnownBlock';
    const HASHLASTUNKNOWNBLOCK = 'hashLastUnkownBlock';

    /**
     * @var array
     */
    protected static $defaults = [
        self::ISDOWNLOAD => false,
        self::INDEXBESTKNOWNBLOCK => null,
        self::HASHLASTUNKNOWNBLOCK => null
    ];

    /**
     * @return bool
     */
    public function isDownload()
    {
        return $this->fetch(self::ISDOWNLOAD);
    }

    /**
     * @return \BitWasp\Bitcoin\Node\Database\DbBlockIndex|null
     */
    public function getIndexBestKnownBlock()
    {
        return $this->fetch(self::INDEXBESTKNOWNBLOCK);
    }

    /**
     * @return string|null
     */
    public function getHashLastUnknownBlock()
    {
        return $this->fetch(self::HASHLASTUNKNOWNBLOCK);
    }

    /**
     * @return static
     */
    public static function create()
    {
        $state = new self;
        foreach (self::$defaults as $key => $value) {
            $state->save($key, $value);
        }

        return $state;
    }

    /**
     *
     */
    public function useForDownload()
    {
        $this->save(self::ISDOWNLOAD, true);
    }

    /**
     * @param Blocks $blocks
     */
    public function processBlockAvailability($blocks)
    {
        $unknownHash = $this->getHashLastUnknownBlock();
        if (!is_null($unknownHash)) {
            $find = $blocks->findHash($unknownHash);
            if ($find) {
                // Hash exists. It exceeds indexBestKnownBlock, or just unset unknownHash anyway.
                $bestKnownBlock = $this->getIndexBestKnownBlock();
                /* todo: or unknown blocks chainwork greater than ) */
                if (is_null($bestKnownBlock)) {
                    $this->save(self::INDEXBESTKNOWNBLOCK, $bestKnownBlock);
                }
                $this->save(self::HASHLASTUNKNOWNBLOCK, null);
            }
        }
    }

    /**
     * @param Blocks $blocks
     * @param string $hash
     */
    public function updateBestKnownBlock($blocks, $hash)
    {
        $index = $blocks->findHash($hash);
        if ($index) {
            if ($this->fetch(self::INDEXBESTKNOWNBLOCK) == null /* todo: or chainwork >= indexBestKnownBlock->chainwork */) {
                $this->save(self::INDEXBESTKNOWNBLOCK, $index);
            }

            return;
        }

        $this->save(self::HASHLASTUNKNOWNBLOCK, $hash);
    }
}