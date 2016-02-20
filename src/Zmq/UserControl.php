<?php

namespace BitWasp\Bitcoin\Node\Zmq;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Zmq\ControlCommand\CommandInterface;
use \React\ZMQ\Context;

class UserControl
{
    /**
     * @var \React\ZMQ\SocketWrapper
     */
    private $socket;

    /**
     * @var CommandInterface[]
     */
    private $commands = [];

    /**
     * @param Context $context
     * @param NodeInterface $node
     * @param CommandInterface[] $commands
     */
    public function __construct(Context $context, NodeInterface $node, array $commands = [])
    {
        foreach ($commands as $command) {
            $this->commands[$command->getName()] = $command;
        }

        $cmdControl = $context->getSocket(\ZMQ::SOCKET_REP);
        $cmdControl->bind('tcp://127.0.0.1:5560');
        $cmdControl->on('message', function ($e) use ($node) {

            $input = json_decode($e, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($input['cmd'])) {
                    $found = false;
                    foreach ($this->commands as $name => $command) {
                        if ($input['cmd'] === $name) {
                            $found = true;
                            $params = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];
                            try {
                                $result = $command->execute($node, $params);
                            } catch (\Exception $e) {
                                $result = ['error' => $e->getMessage()];
                            }
                        }
                    }

                    if (!$found) {
                        $result = ['error'=>'Unknown command'];
                    }
                } else {
                    $result = ['error'=>'Malformed request'];
                }

            } else {
                $result = ['error'=>'Malformed request'];
            }

            $this->socket->send(json_encode($result, JSON_PRETTY_PRINT));
        });

        $this->socket = $cmdControl;
    }
}
