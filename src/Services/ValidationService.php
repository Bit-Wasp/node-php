<?php

namespace BitWasp\Bitcoin\Node\Services;

use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\HeaderChainViewInterface;
use BitWasp\Bitcoin\Node\NodeInterface;
use Evenement\EventEmitter;
use Pimple\Container;
use React\EventLoop\LoopInterface;

class ValidationService extends EventEmitter
{
    /**
     * @var NodeInterface
     */
    private $node;

    /**
     * @var LoopInterface
     */
    private $loop;

    const ENABLED = false;

    /**
     * P2PHeadersService constructor.
     * @param NodeInterface $node
     * @param Container $container
     */
    public function __construct(NodeInterface $node, Container $container)
    {
        $this->node = $node;
        $this->loop = $container['loop'];

        $this->node->blocks()->on('block.accept', function($chain, $index, $block) {
            $this->loop->futureTick(function () use ($chain, $index, $block) {
                $this->connectBlock($chain, $index, $block);
            });
        });

        try {
            if (self::ENABLED) {
                $this->catchUp($this->node->chain());
            }

        } catch (\Exception $e) {
            echo $e->getMessage().PHP_EOL;
            echo $e->getTraceAsString().PHP_EOL;
        }
    }

    /**
     * @param HeaderChainViewInterface $chainView
     */
    public function catchUp(HeaderChainViewInterface $chainView)
    {
        $dataBlk = $chainView->blocks()->getIndex();
        $validBlk = $chainView->validBlocks()->getIndex();

        if ($dataBlk->getHeight() != $validBlk->getHeight()) {
            $this->loop->futureTick(function() use ($chainView, $validBlk) {
                try {
                    $hash = $chainView->blocks()->getHashFromHeight($validBlk->getHeight() + 1);
                    $access = $this->node->chains()->access($chainView);
                    $index = $access->fetchIndex($hash);
                    $block = $access->fetchBlock($hash);
                    $this->connectBlock($chainView, $index, $block);
                    $this->catchUp($chainView);
                } catch (\Exception $e) {
                    echo "problem catching up: {$e->getMessage()}\n";
                    echo $e->getTraceAsString().PHP_EOL;
                }
            });
        }
    }

    /**
     * @param HeaderChainViewInterface $chainView
     * @param BlockIndexInterface $index
     * @param BlockInterface $block
     * @return void
     */
    public function connectBlock(HeaderChainViewInterface $chainView, BlockIndexInterface $index, BlockInterface $block)
    {
        if (!self::ENABLED) {
            return;
        }

        try {
            if (!$chainView->containsHash($index->getHash())) {
                echo "provided chain does not contain this hash\n";
                return;
            }

            $lastValid = $chainView->validBlocks()->getIndex();
            if ($index->getHeight() != $lastValid->getHeight() + 1) {
                echo "index for ConnectBlock does not extend last Valid block - wait";
                return;
            }

            $this->node->blocks()->connect($index, $block, $chainView);

        } catch (\Exception $e) {
            echo $e->getMessage().PHP_EOL;
            echo $e->getTraceAsString().PHP_EOL;
            die("EXCEPTION VAlidationSErvice connect block {$e->getMessage()}");
        }

    }
}
