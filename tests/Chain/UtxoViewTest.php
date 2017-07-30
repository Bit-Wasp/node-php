<?php

namespace BitWasp\Bitcoin\Tests\Node\Chain;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Node\Chain\UtxoView;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Tests\Node\BitcoinNodeTest;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;

class UtxoViewTest extends BitcoinNodeTest
{
    public function testCount()
    {
        $view = new UtxoView([]);
        $this->assertEquals(0, count($view));

        $view = new UtxoView([
            new Utxo(
                new OutPoint(new Buffer('', 32), 0),
                new TransactionOutput(1, new Script())
            )
        ]);

        $this->assertEquals(1, count($view));
    }

    public function testHaveUtxo()
    {
        $utxo = new Utxo(
            new OutPoint(new Buffer('a', 32), 0),
            new TransactionOutput(1, new Script())
        );

        $wantOutpoint = $utxo->getOutPoint();
        $differentOutpoint = new OutPoint(new Buffer('b', 32), 0);

        $view = new UtxoView([$utxo]);
        $this->assertTrue($view->have($wantOutpoint));
        $this->assertFalse($view->have($differentOutpoint));
    }

    public function testFetchUtxo()
    {
        $utxo = new Utxo(
            new OutPoint(new Buffer('a', 32), 0),
            new TransactionOutput(1, new Script())
        );

        $wantOutpoint = $utxo->getOutPoint();

        $view = new UtxoView([$utxo]);
        $result = $view->fetch($wantOutpoint);
        $this->assertSame($utxo, $result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Utxo not found in this UtxoView
     */
    public function testFetchUtxoFailure()
    {
        $utxo = new Utxo(
            new OutPoint(new Buffer('a', 32), 0),
            new TransactionOutput(1, new Script())
        );

        $differentOutpoint = new OutPoint(new Buffer('b', 32), 0);

        $view = new UtxoView([$utxo]);
        $view->fetch($differentOutpoint);
    }

    public function testFetchByInput()
    {
        $utxo = new Utxo(
            new OutPoint(new Buffer('a', 32), 0),
            new TransactionOutput(1, new Script())
        );

        $wantOutpoint = $utxo->getOutPoint();
        $input = new TransactionInput($wantOutpoint, new Script(), 0);

        $view = new UtxoView([$utxo]);
        $result = $view->fetchByInput($input);
        $this->assertSame($utxo, $result);
    }

    public function testGetValueIn()
    {
        $utxo1 = new Utxo(
            new OutPoint(new Buffer('a', 32), 0),
            new TransactionOutput(2, new Script())
        );

        $utxo2 = new Utxo(
            new OutPoint(new Buffer('a', 32), 1),
            new TransactionOutput(4, new Script())
        );

        $utxo3 = new Utxo(
            new OutPoint(new Buffer('b', 32), 0),
            new TransactionOutput(1, new Script())
        );
        $view = new UtxoView([$utxo1, $utxo2, $utxo3]);

        $transaction = TransactionFactory::build()
            ->spendOutPoint($utxo1->getOutPoint())
            ->spendOutPoint($utxo2->getOutPoint())
            ->spendOutPoint($utxo3->getOutPoint())
            ->output(5, new Script())
            ->get();

        $this->assertEquals(7, $view->getValueIn(Bitcoin::getMath(), $transaction));
        $this->assertEquals(2, $view->getFeePaid(Bitcoin::getMath(), $transaction));
    }
}
