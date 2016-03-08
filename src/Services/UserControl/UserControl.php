<?php

namespace BitWasp\Bitcoin\Node\Services\UserControl;

use BitWasp\Bitcoin\Node\NodeInterface;
use BitWasp\Bitcoin\Node\Services\UserControl\ControlCommand\CommandInterface;
use React\ZMQ\Context;

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
            if (json_last_error() !== JSON_ERROR_NONE || !isset($input['cmd'])) {
                return $this->respondError('Malformed request');
            }

            if (!isset($this->commands[$input['cmd']])) {
                return $this->respondError('Unknown command');
            }

            $params = isset($input['params']) && is_array($input['params']) ? $input['params'] : [];
            $result = $this->commands[$input['cmd']]->run($node, $params);

            return $this->respond($result);
        });

        $this->socket = $cmdControl;
    }

    /**
     * @param string $message
     * @return bool
     */
    private function respondError($message)
    {
        $this->socket->send(json_encode(['error' => $message], JSON_PRETTY_PRINT));
        return true;
    }

    /**
     * @param array $result
     * @return bool
     */
    private function respond(array $result)
    {
        $this->socket->send(json_encode($result, JSON_PRETTY_PRINT));
        return true;
    }
}
