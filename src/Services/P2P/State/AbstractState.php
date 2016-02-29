<?php

namespace BitWasp\Bitcoin\Node\Services\P2P\State;

use Doctrine\Common\Cache\ArrayCache;

abstract class AbstractState extends ArrayCache implements \ArrayAccess
{
    public function offsetSet($offset, $value)
    {
        $this->save($offset, $value);
    }

    public function offsetExists($offset)
    {
        return $this->contains($offset);
    }

    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    public function offsetGet($offset)
    {
        return $this->fetch($offset);
    }
}
