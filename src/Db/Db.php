<?php

namespace BitWasp\Bitcoin\Node\Db;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockFactory;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\Chain\ChainViewInterface;
use BitWasp\Bitcoin\Node\Chain\DbUtxo;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\BlockAcceptData;
use BitWasp\Bitcoin\Node\Index\Validation\BlockData;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializerInterface;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\OutPointInterface;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
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
    public $newLoadTipStmt;

    /**
     * @var \PDOStatement
     */
    public $loadSegmentHashList;

    /**
     * @var \PDOStatement
     */
    public $loadSegmentAncestor;

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
     * @var \PDOStatement
     */
    private $deleteUtxoStmt;
    /**
     * @var \PDOStatement
     */
    private $deleteUtxosInView;
    /**
     * @var \PDOStatement
     */
    private $deleteUtxoByIdStmt;
    /**
     * @var \PDOStatement
     */
    private $dropDatabaseStmt;

    /**
     * @var \PDOStatement
     */
    private $fetchLftRgtByHash;

    /**
     * @var \PDOStatement
     */
    private $fetchSuperMajorityVersions;

    /**
     * @var \PDOStatement
     */
    private $insertBlockStmt;

    /**
     * @var \PDOStatement
     */
    private $updateBlockStatusStmt;

    /**
     * @var \PDOStatement
     */
    private $insertToBlockIndexStmt;

    /**
     * @var \PDOStatement
     */
    private $loadChainByCoord;

    /**
     * @var \PDOStatement
     */
    private $loadLastBlockByCoord;

    /**
     * @var \PDOStatement
     */
    private $truncateOutpointsStmt;

    /**
     * @var \PDOStatement
     */
    private $selectUtxosByOutpointsStmt;

    /**
     * Db constructor.
     * @param ConfigProviderInterface $config
     */
    public function __construct(ConfigProviderInterface $config, \PDO $pdo)
    {
        $driver = $config->getItem('db', 'driver');
        $host = $config->getItem('db', 'host');
        $username = $config->getItem('db', 'username');
        $password = $config->getItem('db', 'password');
        $database = $config->getItem('db', 'database');

        $this->database = $database;
        $this->dbh = new \PDO("$driver:host=$host;dbname=$database", $username, $password);
        $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->truncateOutpointsStmt = $this->dbh->prepare('TRUNCATE outpoints');
        $this->selectUtxosByOutpointsStmt = $this->dbh->prepare("SELECT u.* FROM utxo u join outpoi o on (o.hashKey = u.hashKey)");
        $this->fetchIndexStmt = $this->dbh->prepare('SELECT h.* FROM headerIndex h WHERE h.hash = :hash');
        $this->fetchLftStmt = $this->dbh->prepare('SELECT i.lft FROM iindex i JOIN headerIndex h ON h.id = i.header_id WHERE h.hash = :prevBlock');
        $this->fetchLftRgtByHash = $this->dbh->prepare('SELECT i.lft,i.rgt FROM headerIndex h, iindex i WHERE h.hash = :hash AND i.header_id = h.id');
        $this->fetchSuperMajorityVersions = $this->dbh->prepare('SELECT h.version FROM   iindex i, headerIndex h WHERE  h.id = i.header_id AND    i.lft < :lft AND i.rgt > :rgt ORDER BY i.rgt ASC LIMIT 1000');

        $this->updateIndicesStmt = $this->dbh->prepare('
                UPDATE iindex  SET rgt = rgt + :nTimes2 WHERE rgt > :myLeft ;
                UPDATE iindex  SET lft = lft + :nTimes2 WHERE lft > :myLeft ;
            ');
        $this->deleteUtxoStmt = $this->dbh->prepare('DELETE FROM utxo WHERE hashKey = ?');
        //$this->deleteUtxoByIdStmt = $this->dbh->prepare('DELETE FROM utxo WHERE id = :id');
        //$this->deleteUtxosInView = $this->dbh->prepare('DELETE u FROM outpoints o join utxo u on (o.hashKey = u.hashKey)');
        $this->deleteUtxosInView = $this->dbh->prepare('DELETE FROM outpoi WHERE 1');

        $this->dropDatabaseStmt = $this->dbh->prepare('DROP DATABASE ' . $this->database);
        $this->insertBlockStmt = $this->dbh->prepare('
INSERT INTO blockIndex (status, block, size_bytes, numtx, header_id) 
values (:status, :block, :size_bytes, :numtx, (select h.id FROM headerIndex h WHERE h.hash = :hash))');
        $this->updateBlockStatusStmt = $this->dbh->prepare('UPDATE blockIndex SET status = :status WHERE header_id=(SELECT h.id FROM headerIndex h WHERE h.hash = :hash)');

        $this->fetchIndexIdStmt = $this->dbh->prepare('
               SELECT     i.*
               FROM       headerIndex  i
               WHERE      i.id = :id
            ');
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
                ORDER BY txIn.nInput
            ');

        $this->txOutStmt = $this->dbh->prepare('
              SELECT    txOut.parent_tx, txOut.value, txOut.scriptPubKey
              FROM      transaction_output  txOut
              JOIN      block_transactions  bt ON bt.transaction_hash = txOut.parent_tx
              WHERE     bt.block_hash = :id
              ORDER BY  txOut.nOutput
            ');
        $this->loadTipStmt = $this->dbh->prepare('SELECT * FROM iindex i JOIN headerIndex h ON h.id = i.header_id WHERE i.rgt = i.lft + 1 ');
        $this->newLoadTipStmt = $this->dbh->prepare('SELECT segment, max(id) as id, max(height) as maxh, min(height) as minh from headerIndex group by segment order by segment');
        $this->loadSegmentHashList = $this->dbh->prepare("SELECT hash, height from headerIndex where segment = :segment");
        $this->loadSegmentAncestor = $this->dbh->prepare("SELECT c1.segment from headerIndex c1 join headerIndex c2 on (c2.prevBlock = c1.hash) where c2.segment = :thisSegment and c2.height = :thisMin");

        $this->loadChainByCoord = $this->dbh->prepare("SELECT h.hash FROM iindex i JOIN headerIndex h ON (h.id = i.header_id) WHERE i.lft <= :lft AND i.rgt >= :rgt");
        $this->fetchChainStmt = $this->dbh->prepare('
               SELECT h.hash
               FROM     iindex AS node,
                        iindex AS parent
               JOIN     headerIndex  h ON h.id = parent.header_id
               WHERE    node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt');
        $this->loadLastBlockByCoord = $this->dbh->prepare('
            SELECT node.*, h.* FROM iindex node 
            JOIN blockIndex b ON (b.hash = node.header_id)
            JOIN headerIndex h ON (h.id = b.id) 
            WHERE node.lft <= :lft AND node.rgt >= :rgt 
            ORDER BY node.header_id DESC
            LIMIT 1
        ');
    }

    /**
     * @param int $segmentId
     * @return array
     */
    public function loadHashesForSegment($segmentId)
    {
        $this->loadSegmentHashList->execute(['segment' => $segmentId]);
        $hashes = $this->loadSegmentHashList->fetchAll(\PDO::FETCH_ASSOC);
        return $hashes;
    }

    /**
     * @param int $segmentId
     * @param int $segmentStart
     * @return int
     */
    public function loadSegmentAncestor($segmentId, $segmentStart)
    {
        $this->loadSegmentAncestor->execute(['thisSegment' => $segmentId, 'thisMin' => $segmentStart]);
        $ancestorRow = $this->loadSegmentAncestor->fetch(\PDO::FETCH_ASSOC);
        if ($ancestorRow === false) {
            throw new \RuntimeException('Ancestor not found');
        }

        return $ancestorRow['segment'];
    }


    public function getPdo()
    {
        return $this->dbh;
    }
    /**
     * @param ConfigProviderInterface $config
     * @return Db
     */
    public static function create(ConfigProviderInterface $config)
    {
        $driver = $config->getItem('db', 'driver');
        $host = $config->getItem('db', 'host');
        $username = $config->getItem('db', 'username');
        $password = $config->getItem('db', 'password');
        $database = $config->getItem('db', 'database');

        $dbh = new \PDO("$driver:host=$host;dbname=$database", $username, $password);
        return new self($config, $dbh);
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
        $this->dropDatabaseStmt->execute();
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
        $stmt[] = $this->dbh->prepare('TRUNCATE utxo');
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
        $stmt[] = $this->dbh->prepare('TRUNCATE utxo');


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
        $stmtHeader = $this->dbh->prepare('INSERT INTO headerIndex (
            hash, segment, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce
          ) VALUES (
            :hash, :segment, :height, :work, :version, :prevBlock, :merkleRoot, :nBits, :nTimestamp, :nNonce
          )
        ');

        if ($stmtHeader->execute(array(
            'hash' => $header->getHash()->getBinary(),
            'segment' => 0,
            'height' => 0,
            'work' => 0,
            'version' => $header->getVersion(),
            'prevBlock' => $header->getPrevBlock()->getBinary(),
            'merkleRoot' => $header->getMerkleRoot()->getBinary(),
            'nBits' => $header->getBits(),
            'nTimestamp' => $header->getTimestamp(),
            'nNonce' => $header->getNonce()
        ))
        ) {
            return true;
        }

        throw new \RuntimeException('Failed to update insert Genesis block index!');
    }

    public function updateBlockStatus(BlockIndexInterface $index, $status)
    {
        // Insert the block header ID
        return $this->updateBlockStatusStmt->execute([
            'hash' => $index->getHash()->getBinary(),
            'status' => $status,
        ]);
    }

    /**
     * @param BufferInterface $blockHash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @param int $status
     * @return int
     */
    public function insertBlock(BufferInterface $blockHash, BlockInterface $block, BlockSerializerInterface $blockSerializer, $status)
    {
        $serializedBlock = $blockSerializer->serialize($block);
        $nTx = count($block->getTransactions());
        $acceptData = new BlockAcceptData();
        $acceptData->numTx = $nTx;
        $acceptData->size = $serializedBlock->getSize();
        return $this->insertBlockRaw($blockHash, $serializedBlock, $acceptData, $status);
    }

    /**
     * @param BufferInterface $blockHash
     * @param BufferInterface $block
     * @param BlockAcceptData $acceptData
     * @param int $status
     * @return string
     */
    public function insertBlockRaw(BufferInterface $blockHash, BufferInterface $block, BlockAcceptData $acceptData, $status)
    {
        if ($this->insertBlockStmt->execute([
            'status' => $status,
            'size_bytes' => $acceptData->size,
            'numtx' => $acceptData->numTx,
            'block' => $block->getBinary(),
            'hash' => $blockHash->getBinary(),
        ])) {
            return $this->dbh->lastInsertId();
        }

        throw new \RuntimeException("Failed to insert block to database");
    }

    /**
     * @param int $blockId
     * @param BlockInterface $block
     * @param HashStorage $hashStorage
     * @return bool
     */
    public function insertBlockTransactions($blockId, BlockInterface $block, HashStorage $hashStorage)
    {
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

        /** @var BufferInterface $txHash */
        $transactions = $block->getTransactions();
        foreach ($transactions as $i => $tx) {
            $txHash = $hashStorage[$tx];
            $hash = $txHash->getBinary();
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
            $txData["isCoinbase$i"] = (int) $tx->isCoinbase();
            $txData["version$i"] = $tx->getVersion();

            for ($j = 0; $j < $nIn; $j++) {
                $input = $tx->getInput($j);
                $inBind[] = " ( :parentId$i , :nInput" . $i . "n" . $j . ", :hashPrevOut" . $i . "n" . $j . ", :nPrevOut" . $i . "n" . $j . ", :scriptSig" . $i . "n" . $j . ", :nSequence" . $i . "n" . $j . " ) ";
                $outpoint = $input->getOutPoint();
                $inData["hashPrevOut" . $i . "n" . $j] = $outpoint->getTxId()->getBinary();
                $inData["nPrevOut" . $i . "n" . $j] = (int) $outpoint->getVout();
                $inData["scriptSig" . $i . "n" . $j] = $input->getScript()->getBinary();
                $inData["nSequence" . $i . "n" . $j] = $input->getSequence();
                $inData["nInput" . $i . "n" . $j] = $j;
            }

            for ($k = 0; $k < $nOut; $k++) {
                $output = $tx->getOutput($k);
                $outBind[] = " ( :parentId$i , :nOutput" . $i . "n" . $k . ", :value" . $i . "n" . $k . ", :scriptPubKey" . $i . "n" . $k . " ) ";
                $outData["value" . $i . "n" . $k] = $output->getValue();
                $outData["scriptPubKey" . $i . "n" . $k] = $output->getScript()->getBinary();
                $outData["nOutput" . $i . "n" . $k] = $k;
            }
        }

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

        return true;
    }

    /**
     * @param array $cacheHits
     */
    public function appendUtxoViewKeys(array $cacheHits)
    {
        $joinList = [];
        $queryValues = [];
        foreach ($cacheHits as $i => $key) {
            $queryValues[] = $key;
            $joinList[] = "(?)";
        }

        $append = $this->dbh->prepare("INSERT INTO outpoints (hashKey) VALUES " . implode(", ", $joinList));
        $append->execute($queryValues);
    }

    /**
     * @param ChainSegment[] $history
     * @param int $status
     * @return BlockIndexInterface
     */
    public function findSegmentBestBlock(array $history, $status)
    {
        $queryValues = [];
        $queryBind = ['status' => $status];
        //$queryBind = [];
        foreach ($history as $c => $segment) {
            $queryValues[] = "h.segment = :seg$c";
            $queryBind['seg' . $c] = $segment->getId();
        }

        $tail = implode(" OR ", $queryValues);
        $query = "SELECT * from headerIndex where id = (SELECT MAX(b.header_id) FROM blockIndex b JOIN headerIndex h on (b.header_id = h.id) WHERE status >= :status AND " . $tail . " LIMIT 1)";
        $sql = $this->dbh->prepare($query);
        $sql->execute($queryBind);
        $result = $sql->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \RuntimeException("Not found");
        }

        $index = new BlockIndex(
            new Buffer($result['hash'], 32),
            $result['height'],
            $result['work'],
            new BlockHeader(
                $result['version'],
                new Buffer($result['prevBlock'], 32),
                new Buffer($result['merkleRoot'], 32),
                $result['nTimestamp'],
                $result['nBits'],
                $result['nNonce']
            )
        );

        return $index;
    }

    /**
     * @param HeadersBatch $batch
     * @return bool
     * @throws \Exception
     */
    public function insertHeaderBatch(HeadersBatch $batch)
    {

        $index = $batch->getIndices();
        $segment = $batch->getTip()->getSegment()->getId();

        $headerValues = [];
        $headerQuery = [];

        foreach ($index as $c => $i) {
            $headerQuery[] = "(:hash$c , :segment$c , :height$c , :work$c ,
            :version$c , :prevBlock$c , :merkleRoot$c ,
            :nBits$c , :nTimestamp$c , :nNonce$c  )";

            $headerValues['hash' . $c] = $i->getHash()->getBinary();
            $headerValues['height' . $c] = $i->getHeight();
            $headerValues['segment' . $c] = $segment;
            $headerValues['work' . $c] = $i->getWork();

            $header = $i->getHeader();
            $headerValues['version' . $c] = $header->getVersion();
            $headerValues['prevBlock' . $c] = $header->getPrevBlock()->getBinary();
            $headerValues['merkleRoot' . $c] = $header->getMerkleRoot()->getBinary();
            $headerValues['nBits' . $c] = $header->getBits();
            $headerValues['nTimestamp' . $c] = $header->getTimestamp();
            $headerValues['nNonce' . $c] = $header->getNonce();
        }

        $insertHeaders = $this->dbh->prepare('
          INSERT INTO headerIndex  (hash, segment, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce)
          VALUES ' . implode(', ', $headerQuery));
        $insertHeaders->execute($headerValues);

        $lastId = (int)$this->dbh->lastInsertId();
        $count = count($index);
        for ($i = 0; $i < $count; $i++) {
            $rowId = $i + $lastId;
            $indexValues['header_id' . $i] = $rowId;
        }

        return true;
    }

    /**
     * @param BufferInterface $hash
     * @return BlockIndexInterface
     */
    public function fetchIndex(BufferInterface $hash)
    {
        if ($this->fetchIndexStmt->execute(['hash' => $hash->getBinary()])) {
            $row = $this->fetchIndexStmt->fetchAll(\PDO::FETCH_ASSOC);
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
                        $row['nBits'],
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
                        $row['nBits'],
                        $row['nNonce']
                    )
                );
            }
        }

        throw new \RuntimeException('Index by that ID not found');
    }

    /**
     * @param int $blockId
     * @return TransactionInterface[]
     */
    public function fetchBlockTransactions($blockId)
    {
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
        return $collection;
    }

    /**
     * @param BufferInterface $hash
     * @return Block
     */
    public function fetchBlock(BufferInterface $hash)
    {
        $stmt = $this->dbh->prepare('
           SELECT     b.block
           FROM       blockIndex  b
           JOIN       headerIndex  h ON b.header_id = h.id
           WHERE      h.hash = :hash
        ');

        $stmt->bindValue(':hash', $hash->getBinary());
        if ($stmt->execute()) {
            $r = $stmt->fetch();
            $stmt->closeCursor();
            if ($r) {
                return BlockFactory::fromHex(new Buffer($r['block']));
                /*return new Block(
                    Bitcoin::getMath(),
                    new BlockHeader(
                        $r['version'],
                        new Buffer($r['prevBlock'], 32),
                        new Buffer($r['merkleRoot'], 32),
                        $r['nTimestamp'],
                        $r['nBits'],
                        $r['nNonce']
                    ),
                    $this->fetchBlockTransactions($r['id'])
                );*/
            }
        }

        throw new \RuntimeException('Failed to fetch block');
    }

    /**
     * @return ChainSegment[]
     */
    public function fetchChainSegments()
    {
        $this->newLoadTipStmt->execute();
        $entries = $this->newLoadTipStmt->fetchAll(\PDO::FETCH_ASSOC);
        $segments = [];

        foreach ($entries as $entry) {
            $index = $this->fetchIndexById($entry['id']);
            $segments[] = new ChainSegment($entry['segment'], $entry['minh'], $index);
        }

        return $segments;
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
                    $row['nBits'],
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
FROM iindex AS tip
JOIN iindex AS parent ON (tip.lft BETWEEN parent.lft AND parent.rgt)
JOIN block_transactions bt ON (bt.block_hash = parent.header_id)
JOIN transactions t ON (bt.transaction_hash = t.id)
WHERE tip.header_id = (
    SELECT id FROM headerIndex WHERE hash = :tipHash
) AND tip.lft BETWEEN parent.lft AND parent.rgt AND t.hash = :txHash
        ');

        $tx->execute([':tipHash' => $tipHash->getBinary(), ':txHash' => $txid->getBinary()]);
        $txResults = $tx->fetchAll(\PDO::FETCH_ASSOC);
        if (count($txResults) === 0) {
            throw new \RuntimeException('getTransaction: Transaction not found');
        }
        $txInfo = $txResults[0];

        $fetchInputs = $this->dbh->prepare('SELECT i.* FROM transactions t JOIN transaction_input i ON t.id = i.parent_tx WHERE t.id = :id ORDER BY i.nInput');
        $fetchOutputs = $this->dbh->prepare('SELECT o.* FROM transactions t JOIN transaction_output o ON t.id = o.parent_tx WHERE t.id = :id ORDER BY o.nOutput');

        $fetchInputs->execute(['id' => $txInfo['id']]);
        $fetchOutputs->execute(['id' => $txInfo['id']]);

        $inputs = $fetchInputs->fetchAll();
        $outputs = $fetchOutputs->fetchAll();

        $transaction = new Transaction(
            $txInfo['version'],
            array_map(function (array $input) {
                return new TransactionInput(
                    new OutPoint(
                        new Buffer($input['hashPrevOut'], 32),
                        $input['nPrevOut']
                    ),
                    new Script(new Buffer($input['scriptSig'])),
                    $input['nSequence']
                );
            }, $inputs),
            array_map(function (array $input) {
                return new TransactionOutput(
                    $input['value'],
                    new Script(new Buffer($input['scriptPubKey']))
                );
            }, $outputs),
            [],
            $txInfo['nLockTime']
        );

        return $transaction;
    }

    /**
     * @param OutPointSerializerInterface $serializer
     * @param array $outpoints
     * @param array $map
     * @return string
     */
    public function selectUtxoByOutpoint($n)
    {
        $parameters = implode(", ", array_fill(0, $n, "?"));
        $query = "SELECT * from utxo where hashKey in ($parameters)";
        return $query;
    }

    public function selectUtxoByOutpointOrig(OutPointSerializerInterface $serializer, array $outpoints, array & $values)
    {
        $list = [];
        foreach ($outpoints as $v => $outpoint) {
            $values[] = $serializer->serialize($outpoint)->getBinary();
            $list[] = "SELECT * from utxo where hashKey = ?";
        }

        $query = implode(" UNION ", $list);
        return $query;
    }

    /**
     * @param OutPointSerializerInterface $outpointSerializer
     * @param OutPointInterface[] $outpoints
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializerInterface $outpointSerializer, array $outpoints)
    {
        $requiredCount = count($outpoints);
        if (0 === count($outpoints)) {
            return [];
        }

        $t1 = microtime(true);

        $genStart = microtime(true);
        $sql = $this->selectUtxoByOutpoint($requiredCount);
        $query = $this->dbh->prepare($sql);

        $genDiff = microtime(true)-$genStart;
        try {
            $query->execute(array_keys($outpoints));
        } catch (\Exception $e) {
            echo $sql.PHP_EOL;
            var_dump(array_map('bin2hex', array_keys($outpoints)));
            throw $e;
        }

        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);

        $outputSet = [];
        $pDiff = 0;
        foreach ($rows as $utxo) {
            $t = microtime(true);
            if (!array_key_exists($utxo['hashKey'], $outpoints)) {
                throw new \RuntimeException("UTXO key from database was not included in query parameters");
            }

            $outpoint = $outpoints[$utxo['hashKey']];
            $pDiff += (microtime(true)-$t);
            $outputSet[$utxo['hashKey']] = new DbUtxo($utxo['id'], $outpoint, new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey']))));
        }

        if (count($outputSet) !== $requiredCount) {
            throw new \RuntimeException('Less than (' . count($outputSet) . ') required amount (' . $requiredCount . ')returned');
        }

        echo "Loading UTXOs (".count($outpoints).") took " . (microtime(true) - $t1) . " seconds\n";
        echo "[gen-query: {$genDiff}] [parse-results: {$pDiff}\n";
        return $outputSet;
    }

    /**
     * @param OutPointSerializerInterface $serializer
     * @param array $utxos
     */
    private function insertUtxosToTable(OutPointSerializerInterface $serializer, array $utxos)
    {
        $a= microtime(true);
        $utxoQuery = [];
        $utxoValues = [];
        $c = 0;
        foreach ($utxos as $hashKey => $utxo) {
            $utxoQuery[] = "(:hash$c, :v$c, :s$c)";
            $utxoValues["hash$c"] = $hashKey;
            $utxoValues["v$c"] = $utxo->getOutput()->getValue();
            $utxoValues["s$c"] = $utxo->getOutput()->getScript()->getBinary();
            $c++;
        }
        $at = microtime(true)-$a;

        $b = microtime(true);
        $insertUtxos = $this->dbh->prepare('INSERT INTO utxo (hashKey, value, scriptPubKey) VALUES ' . implode(', ', $utxoQuery));
        $insertUtxos->execute($utxoValues);
        $bt = microtime(true)-$b;
        echo "[build query params: $at] [exec utxos $bt] ";
    }

    /**
     * @param OutPointSerializerInterface $serializer
     * @param array $outpoints
     * @param array $values
     * @return string
     */
    public function deleteUtxosByOutpoint(OutPointSerializerInterface $serializer, array $outpoints, array & $values)
    {
        $list = [];
        foreach ($outpoints as $i => $outpoint) {
            $values['hash' . $i] = $serializer->serialize($outpoint)->getBinary();
            $list[] = "'hash$i'";
        }

        $query = "DELETE FROM utxo WHERE hashKey in (".implode(",", $list).")";
        return $query;
    }

    public function deleteUtxosByUtxo($n)
    {
        $list = array_fill(0, $n, "?");

        $query = "DELETE FROM utxo WHERE id in (".implode(",", $list).")";
        return $query;
    }

    /**
     * @param OutPointSerializerInterface $outSer
     * @param BlockData $blockData
     */
    public function updateUtxoSet(OutPointSerializerInterface $outSer, BlockData $blockData)
    {
        $str = "";
        if (!empty($blockData->requiredOutpoints)) {
            $deleteIds = [];
            $c = 0;
            $diff = 0;
            foreach ($blockData->requiredOutpoints as $outPoint) {
                $a = microtime(true);
                $utxo = $blockData->utxoView->fetch($outPoint);
                $diff += microtime(true)-$a;
                $deleteIds[] = $utxo->getId();
                $c++;
            }

            $b = microtime(true);
            $delete = $this->dbh->prepare($this->deleteUtxosByUtxo($c));
            $diff2 = microtime(true) - $b;

            $delete->execute($deleteIds);

            echo "[delete utxos fetch: $diff] [delete utxos query: $diff2] ";
        }

        if (!empty($blockData->remainingNew)) {
            $a = microtime(true);
            $this->insertUtxosToTable($outSer, $blockData->remainingNew);
            $diff = microtime(true)-$a;
            echo "[insert: $diff] ";
        }
    }

    /**
     * @param BufferInterface $hash
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByHash(BufferInterface $hash, $numAncestors = 1000)
    {
        $this->fetchLftRgtByHash->execute(['hash' => $hash->getBinary()]);
        $id = $this->fetchLftRgtByHash->fetch(\PDO::FETCH_ASSOC);

        $this->fetchSuperMajorityVersions->execute(['lft' => $id['lft'], 'rgt' => $id['rgt']]);
        $stream = $this->fetchSuperMajorityVersions->fetchAll(\PDO::FETCH_COLUMN);
        return $stream;
    }

    /**
     * @param ChainViewInterface $view
     * @param int $numAncestors
     * @return array
     */
    public function findSuperMajorityInfoByView(ChainViewInterface $view, $numAncestors = 1000)
    {
        $tipHeight = $view->getIndex()->getHeight();
        $min = max($tipHeight - $numAncestors, 0);

        $pointer = $tipHeight;
        $history = $view->getHistory();

        $values = [];
        $query = [];
        $c = 0;
        while ($pointer !== $min && count($history) > 0) {
            /**
             * @var ChainSegment $segment
             */
            $segment = array_pop($history);
            $end = $segment->getLast()->getHeight();
            if ($end !== $pointer) {
                throw new \RuntimeException('Pointer inconsistent');
            }

            $size = min($numAncestors, $end - $segment->getStart());
            $start = $end - $size;
            $values['segid' . $c] = $segment->getId();
            $values['heightlast' . $c] = $end;
            $values['heightstart' . $c] = $start;
            $query[] = "SELECT h.version from headerIndex h where h.segment = :segid" . $c . " AND h.height BETWEEN :heightstart" . $c . " AND :heightlast" . $c ;
            $pointer -= $size;
            $c++;
        }
        
        $query = implode("UNION ALL", $query);
        $statement = $this->dbh->prepare($query);
        $statement->execute($values);
        $results = $statement->fetchAll(\PDO::FETCH_COLUMN);
        
        return $results;
    }

    /**
     * @param callable $function
     * @return void
     * @throws \Exception
     */
    public function transaction(callable $function)
    {
        $this->dbh->beginTransaction();

        try {
            $function();
            $this->dbh->commit();
        } catch (\Exception $e) {
            $this->dbh->rollBack();
            throw $e;
        }
    }
}
