<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\Chain;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\Utxo\UtxoView;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;
use Packaged\Config\ConfigProviderInterface;

class Db
{
    /**
     * @var \PDO
     */
    private $dbh;

    /**
     * @var \PDOStatement
     */
    private $fetchIndexStmt;

    /**
     * @var \PDOStatement
     */
    private $fetchChainStmt;

    /**
     * @var \PDOStatement
     */
    private $loadTipStmt;

    /**
     * @var \PDOStatement
     */
    private $loadLastBlockStmt;

    /**
     * @var \PDOStatement
     */
    private $fetchIndexIdStmt;

    /**
     * @var \PDOStatement
     */
    private $fetchLftStmt;

    /**
     * @var \PDOStatement
     */
    private $txsStmt;

    /**
     * @var \PDOStatement
     */
    private $txInStmt;

    /**
     * @var \PDOStatement
     */
    private $txOutStmt;

    /**
     * @var \PDOStatement
     */
    private $updateIndicesStmt;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var string
     */
    private $tblIndex = 'iindex';

    /**
     * @var string
     */
    private $tblHeaders = 'headerIndex';

    /**
     * @var string
     */
    private $tblBlocks = 'blockIndex';

    /**
     * @var string
     */
    private $tblBlockTxs = 'block_transactions';

    /**
     * @var string
     */
    private $tblTransactions = 'transactions';

    /**
     * @var string
     */
    private $tblTxIn = 'transaction_input';

    /**
     * @var string
     */
    private $tblTxOut = 'transaction_output';

    /**
     * @param ConfigProviderInterface $config
     * @param bool|false $debug
     */
    public function __construct(ConfigProviderInterface $config, $debug = false)
    {
        $this->debug = $debug;

        $driver = $config->getItem('db', 'driver');
        $host = $config->getItem('db', 'host');
        $username = $config->getItem('db', 'username');
        $password = $config->getItem('db', 'password');
        $database = $config->getItem('db', 'database');

        $this->database = $database;
        $this->dbh = new \PDO("$driver:host=$host;dbname=$database", $username, $password);
        $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     *
     */
    public function stop()
    {
        $this->dbh = null;
    }

    /**
     * @return bool
     */
    public function wipe()
    {
        /** @var \PDOStatement $stmt */
        $stmt = $this->dbh->prepare('DROP DATABASE ' . $this->database);
        $stmt->execute();

        return true;
    }

    /**
     * @return bool
     */
    public function resetBlocksOnly()
    {
        /** @var \PDOStatement[] $stmt */
        $stmt = [];
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblBlocks);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblBlockTxs);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTransactions);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTxOut);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTxIn);

        foreach ($stmt as $st) {
            $st->execute();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function reset()
    {
        /** @var \PDOStatement[] $stmt */
        $stmt = [];
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblIndex);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblHeaders);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblBlocks);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblBlockTxs);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTransactions);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTxOut);
        $stmt[] = $this->dbh->prepare('TRUNCATE ' . $this->tblTxIn);

        foreach ($stmt as $st) {
            $st->execute();
        }

        return true;
    }

    /**
     * Creates the Genesis block index
     * @param BlockHeaderInterface $header
     * @return bool
     */
    public function createIndexGenesis(BlockHeaderInterface $header)
    {
        $stmtIndex = $this->dbh->prepare('INSERT INTO ' . $this->tblIndex . ' (header_id, lft, rgt) VALUES (:headerId, :lft, :rgt)');

        $stmtHeader = $this->dbh->prepare('INSERT INTO ' . $this->tblHeaders . ' (
            hash, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce
          ) VALUES (
            :hash, :height, :work, :version, :prevBlock, :merkleRoot, :nBits, :nTimestamp, :nNonce
          )
        ');

        if ($stmtHeader->execute(array(
            'hash' => $header->getHash()->getBinary(),
            'height' => 0,
            'work' => 0,
            'version' => $header->getVersion(),
            'prevBlock' => $header->getPrevBlock()->getBinary(),
            'merkleRoot' => $header->getMerkleRoot()->getBinary(),
            'nBits' => $header->getBits()->getInt(),
            'nTimestamp' => $header->getTimestamp(),
            'nNonce' => $header->getNonce()
        ))) {

            if ($stmtIndex->execute([
                'headerId' => $this->dbh->lastInsertId(),
                'lft' => 1,
                'rgt' => 2
            ])) {
                return true;
            }
        }

        throw new \RuntimeException('Failed to update insert Genesis block index!');
    }

    /**
     * @param BlockIndex $index
     */
    public function createBlockIndexGenesis(BlockIndex $index)
    {
        $stmt = $this->dbh->prepare('INSERT INTO '.$this->tblBlocks.' ( hash ) SELECT id from headerIndex where hash = :hash ');
        $stmt->bindValue(':hash', $index->getHash()->getBinary());
        $stmt->execute();
    }

    /**
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlockOld(BlockInterface $block)
    {
        $blockHash = $block->getHeader()->getHash()->getBinary();

        try {
            $this->dbh->beginTransaction();

            $txListBind = [];
            $txListData = ['blockHash' => $blockHash];
            $temp = [];

            // Prepare SQL statement adding all transaction inputs in this block.
            $inBind = [];
            $inData = [];

            // Prepare SQL statement adding all transaction outputs in this block
            $outBind = [];
            $outData = [ ];

            // Add all transactions in the block
            $txBind = [];
            $txData = [];

            $transactions = $block->getTransactions();
            foreach ($transactions as $i => $tx) {
                $hash = $tx->getTxId()->getBinary();
                $temp[$hash] = $i;
                $valueOut = $tx->getValueOut();
                $nOut = count($tx->getOutputs());
                $nIn = count($tx->getInputs());

                $txListBind[] = " ( :blockHash, :tx$i) ";
                $txListData["tx$i"] = $hash;

                $txBind[] = " ( :hash$i , :version$i , :nLockTime$i , :tx$i , :nOut$i , :nValueOut$i , :nFee$i , :isCoinbase$i ) ";
                $txData["hash$i"] = $hash;
                $txData["tx$i"] = $tx->getBinary();
                $txData["nOut$i"] = $nOut;
                $txData["nValueOut$i"] = $valueOut;
                $txData["nFee$i"] = '0';
                $txData["nLockTime$i"] = $tx->getLockTime();
                $txData["isCoinbase$i"] = $tx->isCoinbase();
                $txData["version$i"] = $tx->getVersion();

                $inData["parenthash$i"] = $hash;
                for ($j = 0; $j < $nIn; $j++) {
                    $input = $tx->getInput($j);
                    $inBind[] = " ( :parenthash$i , :nInput$i$j, :hashPrevOut$i$j, :nPrevOut$i$j, :scriptSig$i$j, :nSequence$i$j ) ";
                    $inData["nInput$i$j"] = $j;
                    $inData["hashPrevOut$i$j"] = $input->getOutPoint()->getTxId()->getBinary();
                    $inData["nPrevOut$i$j"] = $input->getOutPoint()->getVout();
                    $inData["scriptSig$i$j"] = $input->getScript()->getBinary();
                    $inData["nSequence$i$j"] = $input->getSequence();
                }

                $outData["parenthash$i"] = $hash; // Populate these later, list of parent_id for each output
                for ($k = 0; $k < $nOut; $k++) {
                    $output = $tx->getOutput($k);
                    $outBind[] = " ( :parenthash$i , :nOutput$i$k, :value$i$k, :scriptPubKey$i$k ) ";

                    $outData["nOutput$i$k"] = $k;
                    $outData["value$i$k"] = $output->getValue();
                    $outData["scriptPubKey$i$k"] = $output->getScript()->getBinary();
                }
            }

            // Finish & prepare each statement
            // Insert the blocks hash
            $blockInsert = $this->dbh->prepare('INSERT INTO '.$this->tblBlocks.' ( hash ) VALUES ( :hash )');
            $blockInsert->bindValue(':hash', $blockHash);

            $insertTx = $this->dbh->prepare('INSERT INTO '.$this->tblTransactions.'  (hash, version, nLockTime, transaction, nOut, valueOut, valueFee, isCoinbase ) VALUES ' . implode(', ', $txBind));
            unset($txBind);

            $insertTxList = $this->dbh->prepare('INSERT INTO '.$this->tblBlockTxs.'  (block_hash, transaction_hash) VALUES ' . implode(', ', $txListBind));
            unset($txListBind);

            $insertInputs = $this->dbh->prepare('INSERT INTO '.$this->tblTxIn.'  (parent_tx, nInput, hashPrevOut, nPrevOut, scriptSig, nSequence) VALUES ' . implode(', ', $inBind));
            unset($inBind);

            $insertOutputs = $this->dbh->prepare('INSERT INTO '.$this->tblTxOut.'  (parent_tx, nOutput, value, scriptPubKey) VALUES ' . implode(', ', $outBind));
            unset($outBind);

            $blockInsert->execute();
            $insertTxList->execute($txListData);
            $insertTx->execute($txData);
            $insertInputs->execute($inData);
            $insertOutputs->execute($outData);

            $this->dbh->commit();
            return true;

        } catch (\Exception $e) {
            $this->dbh->rollBack();
        }

        throw new \RuntimeException('MySqlDb: Failed executing Block insert transaction');
    }



    /**
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlock(BlockInterface $block)
    {
        $blockHash = $block->getHeader()->getHash()->getBinary();

        try {
            $this->dbh->beginTransaction();

            $txListBind = [];
            $txListData = [];
            $temp = [];

            // Prepare SQL statement adding all transaction inputs in this block.
            $inBind = [];
            $inData = [];

            // Prepare SQL statement adding all transaction outputs in this block
            $outBind = [];
            $outData = [ ];

            // Add all transactions in the block
            $txBind = [];
            $txData = [];

            $transactions = $block->getTransactions();
            foreach ($transactions as $i => $tx) {
                $hash = $tx->getTxId()->getBinary();
                $temp[$i] = $hash;
                $valueOut = $tx->getValueOut();
                $nOut = count($tx->getOutputs());
                $nIn = count($tx->getInputs());

                $txListBind[] = " ( :headerId, :txId$i) ";

                $txBind[] = " ( :hash$i , :version$i , :nLockTime$i , :tx$i , :nOut$i , :nValueOut$i , :nFee$i , :isCoinbase$i ) ";
                $txData["hash$i"] = $hash;
                $txData["tx$i"] = $tx->getBinary();
                $txData["nOut$i"] = $nOut;
                $txData["nValueOut$i"] = $valueOut;
                $txData["nFee$i"] = '0';
                $txData["nLockTime$i"] = $tx->getLockTime();
                $txData["isCoinbase$i"] = $tx->isCoinbase();
                $txData["version$i"] = $tx->getVersion();

                for ($j = 0; $j < $nIn; $j++) {
                    $input = $tx->getInput($j);
                    $inBind[] = " ( :parentId$i , :nInput$i$j, :hashPrevOut$i$j, :nPrevOut$i$j, :scriptSig$i$j, :nSequence$i$j ) ";
                    $outpoint = $input->getOutPoint();
                    $inData["hashPrevOut$i$j"] = $outpoint->getTxId()->getBinary();
                    $inData["nPrevOut$i$j"] = $outpoint->getVout();
                    $inData["scriptSig$i$j"] = $input->getScript()->getBinary();
                    $inData["nSequence$i$j"] = $input->getSequence();
                    $inData["nInput$i$j"] = $j;

                }

                for ($k = 0; $k < $nOut; $k++) {
                    $output = $tx->getOutput($k);
                    $outBind[] = " ( :parentId$i , :nOutput$i$k, :value$i$k, :scriptPubKey$i$k ) ";
                    $outData["value$i$k"] = $output->getValue();
                    $outData["scriptPubKey$i$k"] = $output->getScript()->getBinary();
                    $outData["nOutput$i$k"] = $k;

                }
            }

            // Finish & prepare each statement
            // Insert the block header ID
            $blockInsert = $this->dbh->prepare('INSERT INTO '.$this->tblBlocks.' ( hash ) SELECT id from headerIndex where hash = :refHash ');
            $blockInsert->bindValue(':refHash', $blockHash);
            $blockInsert->execute();

            $blockId = (int) $this->dbh->lastInsertId();

            $insertTx = $this->dbh->prepare('INSERT INTO '.$this->tblTransactions.'  (hash, version, nLockTime, transaction, nOut, valueOut, valueFee, isCoinbase ) VALUES ' . implode(', ', $txBind));
            $insertTx->execute($txData);
            unset($txBind);

            // Populate inserts
            $txListData['headerId'] = $blockId;
            $lastId = (int)$this->dbh->lastInsertId();
            foreach ($temp as $i => $hash) {
                $rowId = $i + $lastId;
                $val = $rowId;
                $outData["parentId$i"] = $val;
                $inData["parentId$i"] = $val;
                $txListData["txId$i"] = $val;
            }
            unset($val);

            $insertTxList = $this->dbh->prepare('INSERT INTO '.$this->tblBlockTxs.'  (block_hash, transaction_hash) VALUES ' . implode(', ', $txListBind));
            unset($txListBind);

            $insertInputs = $this->dbh->prepare('INSERT INTO '.$this->tblTxIn.'  (parent_tx, nInput, hashPrevOut, nPrevOut, scriptSig, nSequence) VALUES ' . implode(', ', $inBind));
            unset($inBind);

            $insertOutputs = $this->dbh->prepare('INSERT INTO '.$this->tblTxOut.'  (parent_tx, nOutput, value, scriptPubKey) VALUES ' . implode(', ', $outBind));
            unset($outBind);

            $insertTxList->execute($txListData);
            $insertInputs->execute($inData);
            $insertOutputs->execute($outData);

            $this->dbh->commit();
            return true;

        } catch (\Exception $e) {
            $this->dbh->rollBack();
        }

        throw new \RuntimeException('MySqlDb: Failed executing Block insert transaction');
    }

    /**
     * @param BlockIndex $startIndex
     * @param BlockIndex[] $index
     * @return bool
     * @throws \Exception
     */
    public function insertIndexBatch(BlockIndex $startIndex, array $index)
    {
        if (null === $this->fetchLftStmt) {
            $this->fetchLftStmt = $this->dbh->prepare('SELECT i.lft from ' . $this->tblIndex . ' i JOIN headerIndex h ON h.id = i.header_id WHERE h.hash = :prevBlock');
            $this->updateIndicesStmt = $this->dbh->prepare('
                UPDATE ' . $this->tblIndex . '  SET rgt = rgt + :nTimes2 WHERE rgt > :myLeft ;
                UPDATE ' . $this->tblIndex . '  SET lft = lft + :nTimes2 WHERE lft > :myLeft ;
            ');
        }

        $fetchParent = $this->fetchLftStmt;
        $resizeIndex = $this->updateIndicesStmt;

        $fetchParent->bindParam(':prevBlock', $startIndex->getHash()->getBinary());
        if ($fetchParent->execute()) {
            foreach ($fetchParent->fetchAll() as $record) {
                $myLeft = $record['lft'];
            }
        }

        $fetchParent->closeCursor();
        if (!isset($myLeft)) {
            throw new \RuntimeException('Failed to extract header position');
        }

        $totalN = count($index);
        $nTimesTwo = 2 * $totalN;
        $leftOffset = $myLeft;
        $rightOffset = $myLeft + $nTimesTwo;

        $this->dbh->beginTransaction();
        try {
            if ($resizeIndex->execute(['nTimes2' => $nTimesTwo, 'myLeft' => $myLeft])) {
                $resizeIndex->closeCursor();

                $headerValues = [];
                $headerQuery = [];

                $indexValues = [];
                $indexQuery = [];

                $c = 0;
                foreach ($index as $i) {
                    $headerQuery[] = "(:hash$c , :height$c , :work$c ,
                    :version$c , :prevBlock$c , :merkleRoot$c ,
                    :nBits$c , :nTimestamp$c , :nNonce$c  )";

                    $headerValues['hash' . $c] = $i->getHash()->getBinary();
                    $headerValues['height' . $c] = $i->getHeight();
                    $headerValues['work' . $c] = $i->getWork();

                    $header = $i->getHeader();
                    $headerValues['version' . $c] = $header->getVersion();
                    $headerValues['prevBlock' . $c] = $header->getPrevBlock()->getBinary();
                    $headerValues['merkleRoot' . $c] = $header->getMerkleRoot()->getBinary();
                    $headerValues['nBits' . $c] = $header->getBits()->getInt();
                    $headerValues['nTimestamp' . $c] = $header->getTimestamp();
                    $headerValues['nNonce' . $c] = $header->getNonce();

                    $indexQuery[] = "(:header_id$c, :lft$c, :rgt$c )";
                    $indexValues['lft' . $c] = $leftOffset + 1 + $c;
                    $indexValues['rgt' . $c] = $rightOffset - $c;
                    $c++;
                }

                $insertHeaders = $this->dbh->prepare('
                  INSERT INTO ' . $this->tblHeaders . '  (hash, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce)
                  VALUES ' . implode(', ', $headerQuery));
                $insertHeaders->execute($headerValues);

                $lastId = (int)$this->dbh->lastInsertId();
                $count = count($index);
                for ($i = 0; $i < $count; $i++) {
                    $rowId = $i + $lastId;
                    $indexValues['header_id' . $i] = $rowId;
                }

                $insertIndices = $this->dbh->prepare('INSERT INTO ' . $this->tblIndex . '  (header_id, lft, rgt) VALUES ' . implode(', ', $indexQuery));
                $insertIndices->execute($indexValues);
                $this->dbh->commit();

                return true;

            }
        } catch (\Exception $e) {
            $this->dbh->rollBack();
            throw $e;
        }

        throw new \RuntimeException('Failed to update chain!');
    }

    /**
     * @param Buffer $hash
     * @return BlockIndex
     */
    public function fetchIndex(Buffer $hash)
    {
        if (null == $this->fetchIndexStmt) {
            $this->fetchIndexStmt = $this->dbh->prepare('
               SELECT     i.*
               FROM       ' . $this->tblHeaders . '  i
               WHERE      i.hash = :hash
            ');
        }

        $stmt = $this->fetchIndexStmt;
        $stmt->bindParam(':hash', $hash->getBinary());

        if ($stmt->execute()) {
            $row = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($row) == 1) {
                $row = $row[0];
                return new BlockIndex(
                    new Buffer($row['hash'], 32),
                    $row['height'],
                    $row['work'],
                    new BlockHeader(
                        $row['version'],
                        new Buffer($row['prevBlock'], 32),
                        new Buffer($row['merkleRoot'], 32),
                        $row['nTimestamp'],
                        Buffer::int((string)$row['nBits'], 4),
                        $row['nNonce']
                    )
                );
            }
        }

        throw new \RuntimeException('Index by that hash not found');
    }

    /**
     * @param int $id
     * @return BlockIndex
     */
    public function fetchIndexById($id)
    {

        if (null == $this->fetchIndexIdStmt) {
            $this->fetchIndexIdStmt = $this->dbh->prepare('
               SELECT     i.*
               FROM       ' . $this->tblHeaders . '  i
               WHERE      i.id = :id
            ');
        }

        if ($this->fetchIndexIdStmt->execute([':id' => $id])) {
            $row = $this->fetchIndexIdStmt->fetchAll();
            if (count($row) === 1) {
                $row = $row[0];
                return new BlockIndex(
                    new Buffer($row['hash'], 32),
                    $row['height'],
                    $row['work'],
                    new BlockHeader(
                        $row['version'],
                        new Buffer($row['prevBlock'], 32),
                        new Buffer($row['merkleRoot'], 32),
                        $row['nTimestamp'],
                        Buffer::int((string)$row['nBits'], 4),
                        $row['nNonce']
                    )
                );
            }
        }

        throw new \RuntimeException('Index by that ID not found');
    }

    /**
     * @param Buffer $blockHash
     * @return TransactionCollection
     */
    public function fetchBlockTransactions(Buffer $blockHash)
    {

        if (null === $this->txsStmt) {
            $this->txsStmt = $this->dbh->prepare('
                SELECT t.hash, t.version, t.nLockTime
                FROM ' . $this->tblTransactions . '  t
                JOIN ' . $this->tblBlockTxs . '  bt ON bt.transaction_hash = t.hash
                WHERE bt.block_hash = :hash
            ');

            $this->txInStmt = $this->dbh->prepare('
                SELECT txIn.parent_tx, txIn.hashPrevOut, txIn.nPrevOut, txIn.scriptSig, txIn.nSequence
                FROM ' . $this->tblTxIn . '  txIn
                JOIN ' . $this->tblBlockTxs . '  bt ON bt.transaction_hash = txIn.parent_tx
                WHERE bt.block_hash = :hash
                GROUP BY txIn.parent_tx
                ORDER BY txIn.nInput
            ');

            $this->txOutStmt = $this->dbh->prepare('
              SELECT    txOut.parent_tx, txOut.value, txOut.scriptPubKey
              FROM      ' . $this->tblTxOut . '  txOut
              JOIN      ' . $this->tblBlockTxs . '  bt ON bt.transaction_hash = txOut.parent_tx
              WHERE     bt.block_hash = :hash
              GROUP BY  txOut.parent_tx
              ORDER BY  txOut.nOutput
            ');
        }

        $binHash = $blockHash->getBinary();
        // We pass a callback instead of looping
        $this->txsStmt->bindValue(':hash', $binHash);
        $this->txsStmt->execute();
        /** @var TxBuilder[] $builder */
        $builder = [];
        $this->txsStmt->fetchAll(\PDO::FETCH_FUNC, function ($hash, $version, $locktime) use (&$builder) {
            $builder[$hash] = (new TxBuilder())
                ->version($version)
                ->locktime($locktime);
        });

        $this->txInStmt->bindParam(':hash', $binHash);
        $this->txInStmt->execute();
        $this->txInStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $hashPrevOut, $nPrevOut, $scriptSig, $nSequence) use (&$builder) {
            $builder[$parent_tx]->spendOutPoint(new OutPoint(new Buffer($hashPrevOut, 32), $nPrevOut), new Script(new Buffer($scriptSig)), $nSequence);
        });

        $this->txOutStmt->bindParam(':hash', $binHash);
        $this->txOutStmt->execute();
        $this->txOutStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $value, $scriptPubKey) use (&$builder) {
            $builder[$parent_tx]->output($value, new Script(new Buffer($scriptPubKey)));
        });

        return new TransactionCollection(array_map(function (TxBuilder $value) {
            return $value->get();
        }, $builder));
    }

    /**
     * @param Buffer $hash
     * @return Block
     */
    public function fetchBlock(Buffer $hash)
    {

        $stmt = $this->dbh->prepare('
           SELECT     h.hash, h.version, h.prevBlock, h.merkleRoot, h.nBits, h.nNonce, h.nTimestamp
           FROM       ' . $this->tblBlocks . '  b
           JOIN       ' . $this->tblHeaders . '  h ON b.hash = h.id
           WHERE      h.hash = :hash
        ');

        $stmt->bindValue(':hash', $hash->getBinary());
        if ($stmt->execute()) {
            $r = $stmt->fetch();
            $stmt->closeCursor();
            if ($r) {
                return new Block(
                    Bitcoin::getMath(),
                    new BlockHeader(
                        $r['version'],
                        new Buffer($r['prevBlock'], 32),
                        new Buffer($r['merkleRoot'], 32),
                        $r['nTimestamp'],
                        Buffer::int($r['nBits'], 4),
                        $r['nNonce']
                    ),
                    $this->fetchBlockTransactions($hash)
                );
            }
        }

        throw new \RuntimeException('Failed to fetch block');
    }

    /**
     * @param Headers $headers
     * @return array
     */
    public function fetchChainState(Headers $headers)
    {
        if ($this->fetchChainStmt === null) {
            $this->loadTipStmt = $this->dbh->prepare('SELECT h.* from '.$this->tblIndex . ' i JOIN headerIndex h on h.id = i.header_id WHERE i.rgt = i.lft + 1 ');
            $this->fetchChainStmt = $this->dbh->prepare('
               SELECT h.hash
               FROM     ' . $this->tblIndex . ' AS node,
                        ' . $this->tblIndex . ' AS parent
               JOIN     ' . $this->tblHeaders . '  h on h.id = parent.header_id
               WHERE    node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt');
            $this->loadLastBlockStmt = $this->dbh->prepare('
            SELECT h.* from iindex as node,
                             iindex as parent
            INNER JOIN blockIndex b on b.hash = parent.header_id
            JOIN headerIndex h on h.id = parent.header_id
            WHERE node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt
            ORDER BY parent.rgt
            LIMIT 1');
        }

        $loadTip = $this->loadTipStmt;
        $fetchTipChain = $this->fetchChainStmt;
        $loadLast = $this->loadLastBlockStmt;

        $math = Bitcoin::getMath();

        if ($loadTip->execute()) {
            $states = [];
            foreach ($loadTip->fetchAll(\PDO::FETCH_ASSOC) as $index) {
                $bestHeader = new BlockIndex(
                    new Buffer($index['hash'], 32),
                    $index['height'],
                    $index['work'],
                    new BlockHeader(
                        $index['version'],
                        new Buffer($index['prevBlock'], 32),
                        new Buffer($index['merkleRoot'], 32),
                        $index['nTimestamp'],
                        Buffer::int($index['nBits'], 4, $math),
                        $index['nNonce']
                    )
                );

                $info = $this->findSuperMajorityInfo($index['id'], [2, 3, 4]);

                $fetchTipChain->bindValue(':id', $index['id']);
                $fetchTipChain->execute();
                $map = $fetchTipChain->fetchAll(\PDO::FETCH_COLUMN);

                $loadLast->bindValue(':id', $index['id']);
                $loadLast->execute();
                $block = $loadLast->fetchAll(\PDO::FETCH_ASSOC);

                if (count($block) === 1) {
                    $block = $block[0];
                    $lastBlock = new BlockIndex(
                        new Buffer($block['hash'], 32),
                        $block['height'],
                        $block['work'],
                        new BlockHeader(
                            $block['version'],
                            new Buffer($block['prevBlock'], 32),
                            new Buffer($block['merkleRoot'], 32),
                            $block['nTimestamp'],
                            Buffer::int($block['nBits'], 4, $math),
                            $block['nNonce']
                        )
                    );
                } else {
                    $lastBlock = $bestHeader;
                }

                $states[] = new ChainState(
                    $math,
                    new Chain(
                        $map,
                        $bestHeader,
                        $headers,
                        $math
                    ),
                    $lastBlock
                );
            }

            return $states;
        }

        throw new \RuntimeException('Failed to fetch block progress');

    }

    /**
     * We use this to help other nodes sync headers. Identify last common
     * hash in our chain
     *
     * @param Chain $activeChain
     * @param BlockLocator $locator
     * @return false|string
     */
    public function findFork(Chain $activeChain, BlockLocator $locator)
    {
        $hashes = [$activeChain->getIndex()->getHash()->getBinary()];
        foreach ($locator->getHashes() as $hash) {
            $hashes[] = $hash->getBinary();
        }

        $placeholders = rtrim(str_repeat('?, ', count($hashes) - 1), ', ') ;
        $stmt = $this->dbh->prepare('
            SELECT    node.hash
            FROM      ' . $this->tblHeaders . ' AS node,
                      ' . $this->tblHeaders . ' AS parent
            WHERE     parent.hash = ? AND node.hash in (' . $placeholders . ')
            ORDER BY  node.rgt LIMIT 1
        ');

        if ($stmt->execute($hashes)) {
            $column = $stmt->fetch();
            $stmt->closeCursor();
            return $column['hash'];
        }

        throw new \RuntimeException('Failed to execute findFork');
    }

    /**
     * Here, we return max 2000 headers following $hash.
     * Useful for helping other nodes sync.
     * @param string $hash
     * @return array
     */
    public function fetchNextHeaders($hash)
    {
        $stmt = $this->dbh->prepare('
            SELECT    child.version, child.prevBlock, child.merkleRoot,
                      child.nTimestamp, child.nBits, child.nNonce, child.height
            FROM      ' . $this->tblHeaders . ' AS child, ' . $this->tblHeaders . '  AS parent
            WHERE     child.rgt < parent.rgt
            AND       parent.hash = :hash
            LIMIT     2000
        ');

        $stmt->bindParam(':hash', $hash);
        if ($stmt->execute()) {
            $results = array();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = new BlockHeader(
                    $row['version'],
                    new Buffer($row['prevBlock'], 32),
                    new Buffer($row['merkleRoot'], 32),
                    $row['nTimestamp'],
                    Buffer::int($row['nBits'], 4),
                    $row['nNonce']
                );
            }

            $stmt->closeCursor();
            return $results;
        }

        throw new \RuntimeException('Failed to fetch next headers ' . $hash);
    }

    /**
     * @param BlockInterface $block
     * @return array
     */
    public function filterUtxoRequest(BlockInterface $block)
    {
        $need = [];
        $utxos = [];

        // Iterating backwards, record all required inputs.
        // If an Output can be found in a transaction in
        // the same block, it will be dropped from the list
        // of required inputs, and returned as a UTXO.

        $vTx = $block->getTransactions();
        for ($i = count($vTx) - 1; $i > 0; $i--) {
            $tx = $vTx[$i];
            foreach ($tx->getInputs() as $in) {
                $outpoint = $in->getOutPoint();
                $lookup = $outpoint->getTxId()->getBinary() . $outpoint->getVout();
                $need[$lookup] = $i;
            }

            $hash = $tx->getTxId();
            foreach ($tx->getOutputs() as $v => $out) {
                $lookup = $hash->getBinary() . $v;
                if (isset($need[$lookup])) {
                    $utxos[] = new Utxo(new OutPoint($hash, $v), $out);
                    unset($need[$lookup]);
                }
            }
        }

        $required = [];
        foreach ($need as $str => $txidx) {
            $required[] = [new OutPoint(new Buffer(substr($str, 0, 32), 32), substr($str, 32)), $txidx];
        }

        return [$required, $utxos];
    }

    /**
     * @param BlockInterface $block
     * @return UtxoView
     */
    public function fetchUtxoView(BlockInterface $block)
    {
        $txs = $block->getTransactions();
        if (1 === count($txs)) {
            return new UtxoView([]);
        }

        list ($required, $outputSet) = $this->filterUtxoRequest($block);

        $joinList = [];
        $queryValues = ['hash' => $block->getHeader()->getPrevBlock()->getBinary()];
        $requiredCount = count($required);
        $initialCount = count($outputSet);

        for ($i = 0, $last = $requiredCount - 1; $i < $requiredCount; $i++) {
            /**
             * @var OutPointInterface $outpoint
             * @var integer $txidx
             */
            list ($outpoint, $txidx) = $required[$i];

            if (0 === $i) {
                $joinList[] = 'SELECT :hashParent' . $i . ' as hashParent, :noutparent' . $i . ' as nOut, :txidx' . $i . ' as txidx ';
            } else {
                $joinList[] = '  SELECT :hashParent' . $i . ', :noutparent' . $i . ', :txidx' . $i;
            }

            $queryValues['hashParent' . $i ] = $outpoint->getTxId()->getBinary();
            $queryValues['noutparent' . $i ] = $outpoint->getVout();
            $queryValues['txidx' . $i] = $txidx;
        }
        $innerJoin = implode(PHP_EOL . "   UNION ALL " . PHP_EOL, $joinList);

        $sql = '
            SELECT listed.hashParent as txid, listed.nOut as vout,
                   o.value, o.scriptPubKey,
                   listed.txidx
            FROM   transactions t
            JOIN (
                '.$innerJoin.'
            ) as listed on (t.hash = listed.hashParent)
            JOIN transaction_output o on (o.parent_tx = t.id AND o.nOutput = listed.nOut)
            JOIN ' . $this->tblBlockTxs . ' as bt on t.id = bt.transaction_hash
            JOIN (
                SELECT parent.header_id from iindex as tip,
                  iindex as parent
                    WHERE tip.header_id = (SELECT id FROM headerIndex WHERE hash = :hash) AND tip.lft BETWEEN parent.lft and parent.rgt
            ) as allowed_block on bt.block_hash = allowed_block.header_id
';

        echo "\nExtract utxos\n";
        $m1 = microtime(true);
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($queryValues);
        echo (microtime(true) - $m1) . " - seconds \n";

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $utxo) {
            $outputSet[] = new Utxo(new OutPoint(new Buffer($utxo['txid'], 32), $utxo['vout']), new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey']))));
        }
        echo "Just got " . count($outputSet) . "\n\n";

        if (count($outputSet) !== ($initialCount + $requiredCount)) {
            throw new \RuntimeException('Utxo was not found');
        }

        return new UtxoView($outputSet);

    }

    public function fetchUtxos($required, $bestBlock)
    {
        $requiredCount = count($required);
        $joinList = '';
        $queryValues = ['hash' => $bestBlock];
        for ($i = 0, $last = $requiredCount - 1; $i < $requiredCount; $i++) {
            list ($txid, $vout, $txidx) = $required[$i];

            if (0 == $i) {
                $joinList .= "SELECT :hashParent$i as hashParent, :noutparent$i as nOut, :txidx$i as txidx\n";
            } else {
                $joinList .= "  SELECT :hashParent$i, :noutparent$i, :txidx$i \n";
            }

            if ($i < $last) {
                $joinList .= "  UNION ALL\n";
            }

            $queryValues["hashParent$i"] = $txid;
            $queryValues["noutparent$i"] = $vout;
            $queryValues["txidx$i"] = $txidx;
        }

        $sql = '
              SELECT    listed.hashParent as txid, listed.nOut as vout,
                        o.value, o.scriptPubKey,
                        allowed_block.height, listed.txidx
              FROM      ' . $this->tblTxOut . '  o
              INNER JOIN (
                $joinList
              ) as listed ON (listed.hashParent = o.parent_tx AND listed.nOut = o.nOutput)
              INNER JOIN ' . $this->tblBlockTxs . '  as bt on listed.hashParent = bt.transaction_hash
              JOIN (
                    SELECT    parent.hash, parent.height
                    FROM      ' . $this->tblHeaders . '  AS tip,
                              ' . $this->tblHeaders . ' AS parent
                    WHERE     tip.hash = :hash AND tip.lft BETWEEN parent.lft AND parent.rgt
              ) as allowed_block on bt.block_hash = allowed_block.hash
              ';

        $outputSet = [];
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($queryValues);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $utxo) {
            $outputSet[] = new Utxo($utxo['txid'], $utxo['vout'], new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey']))));
        }

        if (count($outputSet) !== $requiredCount) {
            throw new \RuntimeException('Utxo was not found');
        }

        return $outputSet;
    }

    public function findSuperMajorityInfo($headerId, array $versions)
    {
        $fragment = implode(", ", array_map(
            function ($version) {
                return "sum(case when h.version = $version then 1 else 0 end) v$version";
            },
            $versions
        ));

        $stmt = $this->dbh->prepare('
            SELECT COUNT(h.id) total,
                   '.$fragment.'
            FROM '.$this->tblIndex.' as tip,
                    '.$this->tblIndex.' as parent
            JOIN    '.$this->tblHeaders.' h on (h.id = parent.header_id)
            WHERE   tip.header_id = :id
                AND parent.header_id > :id - 1250
                AND tip.lft between parent.lft AND parent.rgt');

        $stmt->bindValue(':id', $headerId);
        if ($stmt->execute()) {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        throw new \RuntimeException('Failed to query supermajority info');
    }

    /**
     * @param Buffer $hash
     * @param array $versions
     * @return array
     */
    public function findSuperMajorityInfoByHash(Buffer $hash, array $versions)
    {
        $stmt = $this->dbh->prepare('SELECT id from '.$this->tblHeaders.' where hash = :hash');
        if ($stmt->execute(['hash' => $hash->getBinary()])) {
            $id = $stmt->fetch(\PDO::FETCH_COLUMN);
            return $this->findSuperMajorityInfo($id, $versions);
        }

        throw new \RuntimeException('findSuperMajorityInfoByHash: Failed to lookup hash');
    }
}
