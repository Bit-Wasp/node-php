<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\State;

use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Buffertools\BufferInterface;

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
     * @return \BitWasp\Bitcoin\Node\Chain\BlockIndexInterface
     */
    public function getIndexBestKnownBlock()
    {
        return $this->fetch(self::INDEXBESTKNOWNBLOCK);
    }

    /**
     * @return BufferInterface|null
     */
    public function getHashLastUnknownBlock()
    {
        return $this->fetch(self::HASHLASTUNKNOWNBLOCK);
    }

    /**
     * @param ChainViewInterface $view
     * @param BufferInterface $hash
     */
    public function updateBlockAvailability(ChainViewInterface $view, BufferInterface $hash)
    {
        if ($view->containsHash($hash)) {
            $this->save(self::INDEXBESTKNOWNBLOCK, $hash);
        } else {
            $this->save(self::HASHLASTUNKNOWNBLOCK, $hash);
        }
    }
}
