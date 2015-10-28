<?php

namespace BitWasp\Bitcoin\Node\State;


use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Index\Blocks;

class PeerState extends AbstractState
{
    const ISDOWNLOAD = 'isDownload';
    const ISBLOCKDOWNLOAD = 'isBlockDownload';
    const INDEXBESTKNOWNBLOCK = 'indexBestKnownBlock';
    const HASHLASTUNKNOWNBLOCK = 'hashLastUnkownBlock';
    const DOWNLOADBLOCKS = 'downloadBlocks';

    /**
     * @var array
     */
    protected static $defaults = [
        self::ISDOWNLOAD => false,
        self::ISBLOCKDOWNLOAD => false,
        self::INDEXBESTKNOWNBLOCK => null,
        self::HASHLASTUNKNOWNBLOCK => null,
        self::DOWNLOADBLOCKS => 0
    ];

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
     * @return bool
     */
    public function isDownload()
    {
        return $this->fetch(self::ISDOWNLOAD);
    }

    /**
     * @param bool|true $default
     */
    public function useForDownload($default = true)
    {
        $this->save(self::ISDOWNLOAD, $default);
    }

    /**
     * @return bool
     */
    public function isBlockDownload()
    {
        return $this->fetch(self::ISBLOCKDOWNLOAD);
    }

    /**
     * @param bool|true $default
     */
    public function useForBlockDownload($default = true)
    {
        $this->save(self::ISBLOCKDOWNLOAD, $default);
    }

    /**
     * @return int
     */
    public function hasDownloadBlocks()
    {
        $count = $this->fetch(self::DOWNLOADBLOCKS);
        return $count > 1;
    }

    /**
     * todo: remove, or rewrite
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
     * @param ChainState $state
     * @param string $hash
     */
    public function updateBlockAvailability(ChainState $state, $hash)
    {
        $chain = $state->getChain();
        var_dump($hash);
        if ($chain->containsHash($hash)) {
            echo "update peers BESTKNOWN block (".$chain->getHeightFromHash($hash).")\n";
            $this->save(self::INDEXBESTKNOWNBLOCK, $hash);
        } else {
            echo "update peers HASH UNKNOWN block\n";
            $this->save(self::HASHLASTUNKNOWNBLOCK, $hash);
        }
    }
}