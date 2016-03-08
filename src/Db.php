<?php

namespace BitWasp\Bitcoin\Node;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionOutputCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionWitnessCollection;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\Chain;
use BitWasp\Bitcoin\Node\Chain\ChainInterface;
use BitWasp\Bitcoin\Node\Chain\ChainState;
use BitWasp\Bitcoin\Node\Chain\ChainStateInterface;
use BitWasp\Bitcoin\Node\Chain\HeadersBatch;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Packaged\Config\ConfigProviderInterface;

class Db implements DbInterface
{
    /**
     * @var string
     */
    private $database;

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
        $stmt[] = $this->dbh->prepare('TRUNCATE blockIndex');
        $stmt[] = $this->dbh->prepare('TRUNCATE block_transactions');
        $stmt[] = $this->dbh->prepare('TRUNCATE transactions');
        $stmt[] = $this->dbh->prepare('TRUNCATE transaction_output');
        $stmt[] = $this->dbh->prepare('TRUNCATE transaction_input');

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
        $stmt[] = $this->dbh->prepare('TRUNCATE iindex');
        $stmt[] = $this->dbh->prepare('TRUNCATE headerIndex');
        $stmt[] = $this->dbh->prepare('TRUNCATE blockIndex');
        $stmt[] = $this->dbh->prepare('TRUNCATE block_transactions');
        $stmt[] = $this->dbh->prepare('TRUNCATE transactions');
        $stmt[] = $this->dbh->prepare('TRUNCATE transaction_output');
        $stmt[] = $this->dbh->prepare('TRUNCATE transaction_input');

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
        $stmtIndex = $this->dbh->prepare('INSERT INTO iindex (header_id, lft, rgt) VALUES (:headerId, :lft, :rgt)');

        $stmtHeader = $this->dbh->prepare('INSERT INTO headerIndex (
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
     * @param BlockIndexInterface $index
     */
    public function createBlockIndexGenesis(BlockIndexInterface $index)
    {
        $stmt = $this->dbh->prepare('INSERT INTO blockIndex ( hash ) SELECT id from headerIndex where hash = :hash ');
        $stmt->bindValue(':hash', $index->getHash()->getBinary());
        $stmt->execute();
    }

    /**
     * @param BlockInterface $block
     * @param BufferInterface $blockHash
     * @return bool
     * @throws \Exception
     */
    public function insertBlock(BufferInterface $blockHash, BlockInterface $block)
    {
        $blockHash = $blockHash->getBinary();

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
            $outData = [];

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

                $txBind[] = " ( :hash$i , :version$i , :nLockTime$i , :nOut$i , :nValueOut$i , :nFee$i , :isCoinbase$i ) ";
                $txData["hash$i"] = $hash;
                $txData["nOut$i"] = $nOut;
                $txData["nValueOut$i"] = $valueOut;
                $txData["nFee$i"] = '0';
                $txData["nLockTime$i"] = $tx->getLockTime();
                $txData["isCoinbase$i"] = $tx->isCoinbase();
                $txData["version$i"] = $tx->getVersion();

                for ($j = 0; $j < $nIn; $j++) {
                    $input = $tx->getInput($j);
                    $inBind[] = " ( :parentId$i , :nInput".$i."n".$j.", :hashPrevOut".$i."n".$j.", :nPrevOut".$i."n".$j.", :scriptSig".$i."n".$j.", :nSequence".$i."n".$j." ) ";
                    $outpoint = $input->getOutPoint();
                    $inData["hashPrevOut" .$i  ."n"  .$j] = $outpoint->getTxId()->getBinary();
                    $inData["nPrevOut"    .$i  ."n"  .$j] = $outpoint->getVout();
                    $inData["scriptSig"   .$i  ."n"  .$j] = $input->getScript()->getBinary();
                    $inData["nSequence"   .$i  ."n"  .$j] = $input->getSequence();
                    $inData["nInput"      .$i  ."n"  .$j] = $j;

                }

                for ($k = 0; $k < $nOut; $k++) {
                    $output = $tx->getOutput($k);
                    $outBind[] = " ( :parentId$i , :nOutput".$i."n".$k.", :value".$i."n".$k.", :scriptPubKey".$i."n".$k." ) ";
                    $outData["value"        .$i  ."n"  .$k] = $output->getValue();
                    $outData["scriptPubKey" .$i  ."n"  .$k] = $output->getScript()->getBinary();
                    $outData["nOutput"      .$i  ."n"  .$k] = $k;

                }
            }

            // Finish & prepare each statement
            // Insert the block header ID
            $blockInsert = $this->dbh->prepare('INSERT INTO blockIndex ( hash ) SELECT id from headerIndex where hash = :refHash ');
            $blockInsert->bindValue(':refHash', $blockHash);
            $blockInsert->execute();

            $blockId = (int) $this->dbh->lastInsertId();

            $insertTx = $this->dbh->prepare('INSERT INTO transactions  (hash, version, nLockTime, nOut, valueOut, valueFee, isCoinbase ) VALUES ' . implode(', ', $txBind));
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

            $insertTxList = $this->dbh->prepare('INSERT INTO block_transactions  (block_hash, transaction_hash) VALUES ' . implode(', ', $txListBind));
            unset($txListBind);

            $insertInputs = $this->dbh->prepare('INSERT INTO transaction_input (parent_tx, nInput, hashPrevOut, nPrevOut, scriptSig, nSequence) VALUES ' . implode(', ', $inBind));
            unset($inBind);

            $insertOutputs = $this->dbh->prepare('INSERT INTO transaction_output  (parent_tx, nOutput, value, scriptPubKey) VALUES ' . implode(', ', $outBind));
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
     * @param HeadersBatch $batch
     * @return bool
     * @throws \Exception
     */
    public function insertHeaderBatch(HeadersBatch $batch)
    {
        if (null === $this->fetchLftStmt) {
            $this->fetchLftStmt = $this->dbh->prepare('SELECT i.lft from iindex i JOIN headerIndex h ON h.id = i.header_id WHERE h.hash = :prevBlock');
            $this->updateIndicesStmt = $this->dbh->prepare('
                UPDATE iindex  SET rgt = rgt + :nTimes2 WHERE rgt > :myLeft ;
                UPDATE iindex  SET lft = lft + :nTimes2 WHERE lft > :myLeft ;
            ');
        }

        $fetchParent = $this->fetchLftStmt;
        $resizeIndex = $this->updateIndicesStmt;

        $fetchParent->bindValue(':prevBlock', $batch->getTip()->getChainIndex()->getHash()->getBinary());
        if ($fetchParent->execute()) {
            foreach ($fetchParent->fetchAll() as $record) {
                $myLeft = $record['lft'];
            }
        }

        $fetchParent->closeCursor();
        if (!isset($myLeft)) {
            throw new \RuntimeException('Failed to extract header position');
        }

        $index = $batch->getIndices();
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
                  INSERT INTO headerIndex  (hash, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce)
                  VALUES ' . implode(', ', $headerQuery));
                $insertHeaders->execute($headerValues);

                $lastId = (int)$this->dbh->lastInsertId();
                $count = count($index);
                for ($i = 0; $i < $count; $i++) {
                    $rowId = $i + $lastId;
                    $indexValues['header_id' . $i] = $rowId;
                }

                $insertIndices = $this->dbh->prepare('INSERT INTO iindex  (header_id, lft, rgt) VALUES ' . implode(', ', $indexQuery));
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
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        if (null == $this->fetchIndexStmt) {
            $this->fetchIndexStmt = $this->dbh->prepare('
               SELECT     h.*
               FROM       headerIndex  h
               WHERE      h.hash = :hash
            ');
        }

        $stmt = $this->fetchIndexStmt;
        $stmt->bindValue(':hash', $hash->getBinary());

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
     * @return BlockIndexInterface
     */
    public function fetchIndexById($id)
    {

        if (null == $this->fetchIndexIdStmt) {
            $this->fetchIndexIdStmt = $this->dbh->prepare('
               SELECT     i.*
               FROM       headerIndex  i
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
     * @param int $blockId
     * @return TransactionCollection
     */
    public function fetchBlockTransactions($blockId)
    {
        if (null === $this->txsStmt) {
            $this->txsStmt = $this->dbh->prepare('
                SELECT t.id, t.version, t.nLockTime
                FROM transactions  t
                JOIN block_transactions  bt ON bt.transaction_hash = t.id
                WHERE bt.block_hash = :id
            ');

            $this->txInStmt = $this->dbh->prepare('
                SELECT txIn.parent_tx, txIn.hashPrevOut, txIn.nPrevOut, txIn.scriptSig, txIn.nSequence
                FROM transaction_input  txIn
                JOIN block_transactions  bt ON bt.transaction_hash = txIn.parent_tx
                WHERE bt.block_hash = :id
                GROUP BY txIn.parent_tx
                ORDER BY txIn.nInput
            ');

            $this->txOutStmt = $this->dbh->prepare('
              SELECT    txOut.parent_tx, txOut.value, txOut.scriptPubKey
              FROM      transaction_output  txOut
              JOIN      block_transactions  bt ON bt.transaction_hash = txOut.parent_tx
              WHERE     bt.block_hash = :id
              GROUP BY  txOut.parent_tx
              ORDER BY  txOut.nOutput
            ');
        }

        // We pass a callback instead of looping
        $this->txsStmt->bindValue(':id', $blockId);
        $this->txsStmt->execute();
        /** @var TxBuilder[] $builder */
        $builder = [];
        $this->txsStmt->fetchAll(\PDO::FETCH_FUNC, function ($id, $version, $locktime) use (&$builder) {
            $builder[$id] = (new TxBuilder())
                ->version($version)
                ->locktime($locktime);
        });

        $this->txInStmt->bindValue(':id', $blockId);
        $this->txInStmt->execute();
        $this->txInStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $hashPrevOut, $nPrevOut, $scriptSig, $nSequence) use (&$builder) {
            $builder[$parent_tx]->spendOutPoint(new OutPoint(new Buffer($hashPrevOut, 32), $nPrevOut), new Script(new Buffer($scriptSig)), $nSequence);
        });

        $this->txOutStmt->bindValue(':id', $blockId);
        $this->txOutStmt->execute();
        $this->txOutStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $value, $scriptPubKey) use (&$builder) {
            $builder[$parent_tx]->output($value, new Script(new Buffer($scriptPubKey)));
        });

        $collection = [];
        foreach ($builder as $b) {
            $collection[] = $b->get();
        }
        return new TransactionCollection($collection);
    }

    /**
     * @param BufferInterface $hash
     * @return Block
     */
    public function fetchBlock(BufferInterface $hash)
    {

        $stmt = $this->dbh->prepare('
           SELECT     h.id, h.hash, h.version, h.prevBlock, h.merkleRoot, h.nBits, h.nNonce, h.nTimestamp
           FROM       blockIndex  b
           JOIN       headerIndex  h ON b.hash = h.id
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
                    $this->fetchBlockTransactions($r['id'])
                );
            }
        }

        throw new \RuntimeException('Failed to fetch block');
    }

    /**
     * @param Headers $headers
     * @param BufferInterface $hash
     * @return ChainStateInterface
     */
    public function fetchHistoricChain(Headers $headers, BufferInterface $hash)
    {
        $math = Bitcoin::getMath();
        $fetchChainStmt = $this->dbh->prepare('
           SELECT h.hash
           FROM     iindex AS node,
                    iindex AS parent
           JOIN     headerIndex  h on h.id = parent.header_id
           WHERE    node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt');
        $loadLastBlockStmt = $this->dbh->prepare('
        SELECT h.* from iindex as node,
                         iindex as parent
        INNER JOIN blockIndex b on b.hash = parent.header_id
        JOIN headerIndex h on h.id = parent.header_id
        WHERE node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt
        ORDER BY parent.rgt
        LIMIT 1');
        $stmt = $this->fetchIndexStmt;
        $stmt->bindValue(':hash', $hash->getBinary());

        if ($stmt->execute()) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (empty($row)) {
                throw new \RuntimeException('Not found');
            }

            $index = new BlockIndex(
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

            $fetchChainStmt->bindValue(':id', $row['id']);
            $fetchChainStmt->execute();
            $map = $fetchChainStmt->fetchAll(\PDO::FETCH_COLUMN);

            $loadLastBlockStmt->bindValue(':id', $row['id']);
            $loadLastBlockStmt->execute();
            $block = $loadLastBlockStmt->fetch(\PDO::FETCH_ASSOC);

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

            return new ChainState(
                new Chain(
                    $map,
                    $index,
                    $headers,
                    $math
                ),
                $lastBlock
            );

        }

        throw new \RuntimeException('Failed to load historic chain?');
    }

    /**
     * @param Headers $headers
     * @return ChainStateInterface[]
     */
    public function fetchChainState(Headers $headers)
    {
        if ($this->fetchChainStmt === null) {
            $this->loadTipStmt = $this->dbh->prepare('SELECT h.* from iindex i JOIN headerIndex h on h.id = i.header_id WHERE i.rgt = i.lft + 1 ');
            $this->fetchChainStmt = $this->dbh->prepare('
               SELECT h.hash
               FROM     iindex AS node,
                        iindex AS parent
               JOIN     headerIndex  h on h.id = parent.header_id
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
     * @param ChainInterface $activeChain
     * @param BlockLocator $locator
     * @return BufferInterface
     */
    public function findFork(ChainInterface $activeChain, BlockLocator $locator)
    {
        $hashes = [$activeChain->getIndex()->getHash()->getBinary()];
        foreach ($locator->getHashes() as $hash) {
            $hashes[] = $hash->getBinary();
        }

        $stmt = $this->dbh->prepare('
            SELECT    node.hash
            FROM      headerIndex AS node,
                      headerIndex AS parent
            WHERE     parent.hash = ? AND node.hash in (' . rtrim(str_repeat('?, ', count($hashes) - 1), ', ') . ')
            ORDER BY  node.rgt LIMIT 1
        ');

        if ($stmt->execute($hashes)) {
            $column = $stmt->fetch();
            $stmt->closeCursor();
            return new Buffer($column['hash'], 32);
        }

        throw new \RuntimeException('Failed to execute findFork');
    }

    /**
     * Here, we return max 2000 headers following $hash.
     * Useful for helping other nodes sync.
     * @param BufferInterface $hash
     * @return BlockHeaderInterface[]
     */
    public function fetchNextHeaders(BufferInterface $hash)
    {
        $stmt = $this->dbh->prepare('
            SELECT    child.version, child.prevBlock, child.merkleRoot,
                      child.nTimestamp, child.nBits, child.nNonce, child.height
            FROM      headerIndex AS child, headerIndex  AS parent
            WHERE     child.rgt < parent.rgt
            AND       parent.hash = :hash
            LIMIT     2000
        ');

        $stmt->bindValue(':hash', $hash->getBinary());
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

        throw new \RuntimeException('Failed to fetch next headers ' . $hash->getHex());
    }

    /**
     * @param BufferInterface $tipHash
     * @param BufferInterface $txid
     * @return TransactionInterface
     */
    public function getTransaction(BufferInterface $tipHash, BufferInterface $txid)
    {
        $tx = $this->dbh->prepare('
        SELECT t.id, t.hash, t.version, t.nLockTime
FROM iindex as tip
JOIN iindex as parent on (tip.lft between parent.lft AND parent.rgt)
JOIN block_transactions bt on (bt.block_hash = parent.header_id)
JOIN transactions t on (bt.transaction_hash = t.id)
WHERE tip.header_id = (
    SELECT id FROM headerIndex WHERE hash = :tipHash
) AND tip.lft BETWEEN parent.lft and parent.rgt AND t.hash = :txHash
        ');

        $tx->execute([':tipHash' => $tipHash->getBinary(), ':txHash' => $txid->getBinary()]);
        $txResults = $tx->fetchAll(\PDO::FETCH_ASSOC);
        if (count($txResults) === 0) {
            throw new \RuntimeException('getTransaction: Transaction not found');
        }
        $txInfo = $txResults[0];

        $fetchInputs = $this->dbh->prepare('SELECT i.* from transactions t join transaction_input i on t.id = i.parent_tx WHERE t.id = :id ORDER BY i.nInput');
        $fetchOutputs = $this->dbh->prepare('SELECT o.* from transactions t join transaction_output o on t.id = o.parent_tx WHERE t.id = :id ORDER BY o.nOutput');

        $fetchInputs->execute(['id' => $txInfo['id']]);
        $fetchOutputs->execute(['id' => $txInfo['id']]);

        $inputs = $fetchInputs->fetchAll();
        $outputs = $fetchOutputs->fetchAll();

        $transaction = new Transaction(
            $txInfo['version'],
            new TransactionInputCollection(array_map(function (array $input) {
                return new TransactionInput(
                    new OutPoint(
                        new Buffer($input['hashPrevOut'], 32),
                        $input['nPrevOut']
                    ),
                    new Script(new Buffer($input['scriptSig'])),
                    $input['nSequence']
                );
            }, $inputs)),
            new TransactionOutputCollection(array_map(function (array $input) {
                return new TransactionOutput(
                    $input['value'],
                    new Script(new Buffer($input['scriptPubKey']))
                );
            }, $outputs)),
            new TransactionWitnessCollection([]),
            $txInfo['nLockTime']
        );

        return $transaction;
    }

    /**
     * @param BufferInterface $tipHash
     * @param OutPointInterface[] $outpoints
     * @return Utxo[]
     * @throws \Exception
     */
    public function fetchUtxoList(BufferInterface $tipHash, array $outpoints)
    {
        $requiredCount = count($outpoints);
        if (0 === count($outpoints)) {
            return [];
        }

        $queryValues = [];
        $joinList = [];
        foreach ($outpoints as $i => $outpoint) {
            $queryValues['hashParent' . $i ] = $outpoint->getTxId()->getBinary();
            $queryValues['noutparent' . $i ] = $outpoint->getVout();

            if (0 === $i) {
                $joinList[] = 'SELECT :hashParent' . $i . ' as hashPrevOut, :noutparent' . $i . ' as nOutput';
            } else {
                $joinList[] = '  SELECT :hashParent' . $i . ', :noutparent' . $i ;
            }
        }

        $innerJoin = implode(PHP_EOL . "   UNION ALL " . PHP_EOL, $joinList);

        $stmt = $this->dbh->prepare('SELECT i.lft,i.rgt from headerIndex h, iindex i where h.hash = :hash and i.header_id = h.id');
        $stmt->execute(['hash' => $tipHash->getBinary()]);
        $id = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->dbh->beginTransaction();

        try {
            $fetchUtxoStmt = $this->dbh->prepare('
SELECT o.hashPrevOut as txid, o.nOutput as vout, ou.* FROM transactions t
JOIN ('.$innerJoin.') o on (o.hashPrevOut = t.hash)
JOIN transaction_output ou on (ou.parent_tx = t.id and ou.nOutput = o.nOutput)
LEFT JOIN transaction_input ti on (ti.hashPrevOut = t.hash AND ti.nPrevOut = o.nOutput)
JOIN block_transactions bt on (bt.transaction_hash = t.id)
JOIN iindex i on (i.header_id = bt.block_hash)
WHERE i.lft <= :lft and i.rgt >= :rgt AND ti.nPrevOut is NULL
');
            $queryValues['rgt'] = $id['rgt'];
            $queryValues['lft'] = $id['lft'];

            $fetchUtxoStmt->execute($queryValues);
            $rows = $fetchUtxoStmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->dbh->commit();

            $outputSet = [];
            foreach ($rows as $utxo) {
                $outputSet[] = new Utxo(new OutPoint(new Buffer($utxo['txid'], 32), $utxo['vout']), new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey']))));
            }

            if (count($outputSet) < $requiredCount) {
                throw new \RuntimeException('Less than ('.count($outputSet).') required amount ('.$requiredCount.')returned');
            }

            return $outputSet;

        } catch (\Exception $e) {
            $this->dbh->rollBack();
            throw $e;
        }
    }

    /**
     * @param BufferInterface $hash
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByHash(BufferInterface $hash, $numAncestors = 1000)
    {
        $stmt = $this->dbh->prepare('SELECT i.lft,i.rgt from headerIndex h, iindex i where h.hash = :hash and i.header_id = h.id');
        if ($stmt->execute(['hash' => $hash->getBinary()])) {
            $id = $stmt->fetch(\PDO::FETCH_ASSOC);

            $stmt = $this->dbh->prepare('
            SELECT
                   h.version
            FROM   iindex i, headerIndex h
            WHERE  h.id = i.header_id
            AND    i.lft < :lft AND i.rgt > :rgt ORDER BY i.rgt ASC LIMIT 1000');

            $stmt->execute(['lft'=>$id['lft'],'rgt'=>$id['rgt']]);
            $stream = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            return $stream;
        }

        throw new \RuntimeException('findSuperMajorityInfoByHash: Failed to lookup hash');
    }
}
