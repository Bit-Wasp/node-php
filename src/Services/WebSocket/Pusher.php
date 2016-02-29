<?php

namespace BitWasp\Bitcoin\Node\Services\WebSocket;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class Pusher implements WampServerInterface
{

    /**
     * A lookup of all the topics clients have subscribed to
     */
    protected $subscribedTopics = array();

    /**
     * @var CommandInterface[]
     */
    protected $command = array();

    /**
     * @var NodeInterface
     */
    protected $node;

    /**
     * Pusher constructor.
     * @param NodeInterface $node
     * @param CommandInterface[] $commands
     */
    public function __construct(NodeInterface $node, array $commands = [])
    {
        foreach ($commands as $command) {
            $this->command[$command->getName()] = $command;
        }
        $this->node = $node;
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     */
    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
    }

    /**
     * @param ConnectionInterface $conn
     * @param string $id
     * @param \Ratchet\Wamp\Topic|string $topic
     * @param array $params
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        if (isset($this->command[$topic->getId()])) {
            /** @var CommandInterface $command */
            $command = $this->command[$topic->getId()];

            $result = $command->run($this->node, $params);
            $conn->callResult($id, $result);
        }

        $conn->callError($id, $topic, ['error' => 'Invalid call']);

    }

    /**
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     * @param string $event
     * @param array $exclude
     * @param array $eligible
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        // In this application if clients send data it's because the user hacked around in console
        $conn->close();
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Ratchet\Wamp\Topic|string $topic
     */
    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->subscribedTopics[$topic->getId()] = $topic;
    }

    /**
     * @param string $entry - JSONified string we'll receive from ZeroMQ
     */
    public function onMessage($entry)
    {
        $entryData = json_decode($entry, true);

        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($entryData['event'], $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$entryData['event']];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast($entry);
    }
}
