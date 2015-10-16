<?php

namespace BitWasp\Bitcoin\Node\State;


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
     * @param int $count
     */
    public function addDownloadBlocks($count)
    {
        $blocks = $this->fetch(self::DOWNLOADBLOCKS);
        $blocks += $count;
        $this->save(self::DOWNLOADBLOCKS, $blocks);
    }

    /**
     *
     */
    public function unsetDownloadBlock()
    {
        $blocks = $this->fetch(self::DOWNLOADBLOCKS);
        $blocks--;
        $this->save(self::DOWNLOADBLOCKS, $blocks);
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
     * todo: remove, or rewrite
     * @return string|null
     */
    public function getHashLastUnknownBlock()
    {
        return $this->fetch(self::HASHLASTUNKNOWNBLOCK);
    }

    /**
     * todo: remove, or rewrite
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
     * todo: remove, or rewrite
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