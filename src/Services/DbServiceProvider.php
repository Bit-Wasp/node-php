<?php

namespace BitWasp\Bitcoin\Node\Services;


use BitWasp\Bitcoin\Node\DbInterface;

class DbServiceProvider extends GenericInstanceServiceProvider
{
    /**
     * DbServiceProvider constructor.
     * @param DbInterface $db
     */
    public function __construct(DbInterface $db)
    {
        parent::__construct('db', $db);
    }
}