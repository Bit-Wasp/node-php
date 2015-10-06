<?php
/**
 * Created by PhpStorm.
 * User: aeonium
 * Date: 12/09/15
 * Time: 22:12
 */

namespace BitWasp\Bitcoin\Node;


class KnownInventory
{
    private $storage;

    public function __construct()
    {
        $this->storage = new \SplObjectStorage();
    }

    public function save()
    {

    }
}