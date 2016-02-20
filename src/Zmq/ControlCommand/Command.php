<?php

namespace BitWasp\Bitcoin\Node\Zmq\ControlCommand;

use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;

abstract class Command implements CommandInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var array
     */
    protected $params = [];

    public function __construct()
    {
        $this->configure();
    }

    /**
     * @param string $name
     * @return $this
     */
    protected function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $description
     * @return $this
     */
    protected function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string $name
     * @param string $description
     * @return $this
     */
    protected function setParam($name, $description)
    {
        $this->params[$name] = $description;
        return $this;
    }

    abstract protected function configure();

    /**
     * @return string
     */
    public function getName()
    {
        if (null === $this->name) {
            throw new \RuntimeException('Name for command not set');
        }

        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        if (null === $this->name) {
            throw new \RuntimeException('Description for command not set');
        }

        return $this->description;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param BlockIndexInterface $index
     * @return array
     */
    public function convertIndexToArray(BlockIndexInterface $index)
    {
        $header = $index->getHeader();

        return [
            'height' => $index->getHeight(),
            'work' => $index->getWork(),
            'header' => [
                'height' => $index->getHeight(),
                'hash' => $index->getHash()->getHex(),
                'prevBlock' => $header->getPrevBlock()->getHex(),
                'merkleRoot' => $header->getMerkleRoot()->getHex(),
                'nBits' => $header->getBits()->getInt(),
                'nTimestamp' => $header->getTimestamp(),
                'nNonce' => $header->getNonce()
            ],
        ];
    }
}
