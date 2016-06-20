<?php

namespace BitWasp\Bitcoin\Node\Index\Validation;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Chain\ParamsInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Script\Interpreter\InterpreterInterface;

class Forks
{

    /**
     * @var ParamsInterface
     */
    private $params;

    /**
     * @var \BitWasp\Bitcoin\Math\Math
     */
    private $math;

    /**
     * @var BlockIndexInterface
     */
    private $index;

    /**
     * @var array|\int[]
     */
    private $versions;

    /**
     * @var int[]
     */
    private $versionCount;

    /**
     * @var bool
     */
    private $p2sh = false;

    /**
     * @var bool
     */
    private $bip34 = false;

    /**
     * @var bool
     */
    private $bip30 = false;

    /**
     * @var bool
     */
    private $derSig = false;

    /**
     * @var bool
     */
    private $cltv = false;

    /**
     * @var bool
     */
    private $witness = false;

    /**
     * @var int
     */
    private $flags = InterpreterInterface::VERIFY_NONE;

    /**
     * Forks constructor.
     * @param ParamsInterface $params
     * @param BlockIndexInterface $index
     * @param array[] $versions
     */
    public function __construct(ParamsInterface $params, BlockIndexInterface $index, array $versions)
    {
        $this->versionCount = ['v1' => 0, 'v2' => 0, 'v3' => 0, 'v4' => 0, 'v5' => 0];
        foreach ($versions as $value) {
            $this->updateCount($value);
        }

        $this->math = Bitcoin::getMath();
        $this->params = $params;
        $this->index = $index;
        $this->versions = $versions;
        $this->update();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'p2sh' => $this->p2sh,
            'cltv' => $this->cltv,
            'witness' => $this->witness,
            'derSig' => $this->derSig,
            'bip30' => $this->bip30,
            'bip34' => $this->bip34
        ];
    }

    /**
     * @param int $version
     */
    private function updateCount($version)
    {
        if ($version >= 5) {
            $this->versionCount['v5']++;
        } else if ($version >= 4) {
            $this->versionCount['v4']++;
        } else if ($version >= 3) {
            $this->versionCount['v3']++;
        } else if ($version >= 2) {
            $this->versionCount['v2']++;
        } else if ($version >= 1) {
            $this->versionCount['v1']++;
        }
    }

    /**
     * @param int $version
     */
    private function dropCount($version)
    {
        if ($version >= 5) {
            $this->versionCount['v5']--;
        } else if ($version >= 4) {
            $this->versionCount['v4']--;
        } else if ($version >= 3) {
            $this->versionCount['v3']--;
        } else if ($version >= 2) {
            $this->versionCount['v2']--;
        } else if ($version >= 1) {
            $this->versionCount['v1']--;
        }
    }

    /**
     *
     */
    private function update()
    {
        $header = $this->index->getHeader();

        // Check all active features
        if ($header->getTimestamp() >= $this->params->p2shActivateTime()) {
            $this->p2sh = true;
        }

        $hash = $this->index->getHash()->getBinary();
        $this->bip30 = !(
            ($this->index->getHeight() == 91842 && $hash == pack("H*", "00000000000a4d0a398161ffc163c503763b1f4360639393e0e4c8e300e0caec")) ||
            ($this->index->getHeight() == 91880 && $hash == pack("H*", "00000000000743f190a18c5577a3c2d2a1f610ae9601ac046a38084ccb7cd721"))
        );

        $highest = $this->majorityVersion();
        if (($highest >= 2)) {
            $this->bip34 = true;
        }

        if (($highest >= 3)) {
            $this->derSig = true;
        }

        if (($highest >= 4)) {
            $this->cltv = true;
        }

        if (($highest >= 5)) {
            $this->witness = true;
        }

        // Calculate flags
        $this->flags = $this->p2sh ? InterpreterInterface::VERIFY_NONE : InterpreterInterface::VERIFY_P2SH;
        if ($this->derSig) {
            $this->flags |= InterpreterInterface::VERIFY_DERSIG;
        }

        if ($this->cltv) {
            $this->flags |= InterpreterInterface::VERIFY_CHECKLOCKTIMEVERIFY;
        }

        if ($this->witness) {
            $this->flags |= InterpreterInterface::VERIFY_WITNESS;
        }
    }

    /**
     * @param BlockIndexInterface $index
     * @return bool
     */
    public function isNext(BlockIndexInterface $index)
    {
        return $this->index->isNext($index);
    }

    /**
     * @param BlockIndexInterface $index
     * @return $this
     */
    public function next(BlockIndexInterface $index)
    {
        if (!$this->isNext($index)) {
            throw new \RuntimeException('Incorrect next block for forks');
        }

        $this->index = $index;
        $first = array_shift($this->versions);
        $this->dropCount($first);

        $next = $index->getHeader()->getVersion();
        $this->versions[] = $next;
        $this->updateCount($next);

        $this->update();
        return $this;
    }

    /**
     * @return bool
     */
    public function doP2sh()
    {
        return $this->p2sh;
    }

    /**
     * @return bool
     */
    public function doBip30()
    {
        return $this->bip30;
    }

    /**
     * @return bool
     */
    public function doBip34()
    {
        return $this->bip34;
    }

    /**
     * @return bool
     */
    public function doCltv()
    {
        return $this->cltv;
    }

    /**
     * @return bool
     */
    public function doWitness()
    {
        return $this->witness;
    }

    /**
     * @return bool
     */
    public function doDerSig()
    {
        return $this->derSig;
    }

    /**
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @return int
     */
    private function majorityVersion()
    {
        if (($this->versionCount['v5'] / 1000) > 0.95) {
            return 5;
        }

        if (($this->versionCount['v4'] / 1000) > 0.95) {
            return 4;
        }

        if (($this->versionCount['v3'] / 1000) > 0.95) {
            return 3;
        }

        if (($this->versionCount['v2'] / 1000) > 0.95) {
            return 2;
        }

        return 1;
    }

    /**
     * @return int
     */
    public function getMajorityVersion()
    {
        if ($this->witness) {
            return 5;
        }

        if ($this->cltv) {
            return 4;
        }

        if ($this->derSig) {
            return 3;
        }

        if ($this->bip34) {
            return 2;
        }

        return 1;
    }
}
