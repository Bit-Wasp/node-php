<?php

namespace BitWasp\Bitcoin\Node\Validation;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class ScriptValidationState
{
    /**
     * @var bool
     */
    private $active;

    /**
     * @var array
     */
    private $results = [];

    /**
     * @var
     */
    private $loop;

    private $knownResult;

    /**
     * ScriptValidationState constructor.
     * @param LoopInterface $loop
     * @param bool $active
     */
    public function __construct(LoopInterface $loop, $active = true)
    {
        if (!is_bool($active)) {
            throw new \InvalidArgumentException('ScriptValidationState: $active should be bool');
        }

        $this->loop = $loop;
        $this->active = $active;
    }

    /**
     * @return bool
     */
    public function active()
    {
        return $this->active;
    }

    /**
     * @param PromiseInterface $promise
     */
    public function queue(PromiseInterface $promise)
    {
        $this->results[] = $promise;
    }

    /**
     * Function to keep the loop ticking, allowing the results
     * to be returned.
     *
     * @param PromiseInterface $promise
     * @return null
     * @throws null
     */
    private function awaitResults(PromiseInterface $promise)
    {
        $wait = true;
        $resolved = null;
        $exception = null;

        $promise->then(
            function ($c) use (&$resolved, &$wait) {
                $resolved = $c;
                $wait = false;
            },
            function ($error) use (&$exception, &$wait) {
                $exception = $error;
                $wait = false;
            }
        );

        while ($wait) {
            $this->loop->tick();
        }

        if ($exception instanceof \Exception) {
            throw $exception;
        }

        return $resolved;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function result()
    {
        if ($this->knownResult !== null) {
            return $this->knownResult;
        }

        if (0 === count($this->results)) {
            return true;
        }

        $outcome = $this->awaitResults(\React\Promise\all($this->results));
        $result = true;
        foreach ($outcome as $r) {
            if ($result === true) {
                $result = $result && $r;
            }
        }

        $this->knownResult = $result;
        $this->results = [];

        return $result;
    }
}
