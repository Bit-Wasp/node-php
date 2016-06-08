<?php

namespace BitWasp\Bitcoin\Node\Db;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockHeaderInterface;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Collection\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionOutputCollection;
use BitWasp\Bitcoin\Collection\Transaction\TransactionWitnessCollection;
use BitWasp\Bitcoin\Node\Chain\BlockIndex;
use BitWasp\Bitcoin\Node\Chain\BlockIndexInterface;
use BitWasp\Bitcoin\Node\Chain\ChainSegment;
use BitWasp\Bitcoin\Node\Chain\DbUtxo;
use BitWasp\Bitcoin\Node\HashStorage;
use BitWasp\Bitcoin\Node\Index\Validation\HeadersBatch;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Serializer\Block\BlockSerializerInterface;
use BitWasp\Bitcoin\Serializer\Transaction\OutPointSerializer;
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
    private $loadSegmentBestBlockStmt;
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
        $this->selectUtxosByOutpointsStmt = $this->dbh->prepare("SELECT u.* FROM outpoints o join utxo u on (o.hashKey = u.hashKey)");
        $this->fetchIndexStmt = $this->dbh->prepare('SELECT h.* FROM headerIndex h WHERE h.hash = :hash');
        $this->fetchLftStmt = $this->dbh->prepare('SELECT i.lft FROM iindex i JOIN headerIndex h ON h.id = i.header_id WHERE h.hash = :prevBlock');
        $this->fetchLftRgtByHash = $this->dbh->prepare('SELECT i.lft,i.rgt FROM headerIndex h, iindex i WHERE h.hash = :hash AND i.header_id = h.id');
        $this->fetchSuperMajorityVersions = $this->dbh->prepare('SELECT h.version FROM   iindex i, headerIndex h WHERE  h.id = i.header_id AND    i.lft < :lft AND i.rgt > :rgt ORDER BY i.rgt ASC LIMIT 1000');

        $this->updateIndicesStmt = $this->dbh->prepare('
                UPDATE iindex  SET rgt = rgt + :nTimes2 WHERE rgt > :myLeft ;
                UPDATE iindex  SET lft = lft + :nTimes2 WHERE lft > :myLeft ;
            ');
        $this->deleteUtxoStmt = $this->dbh->prepare('DELETE FROM utxo WHERE hashKey = ?');
        $this->deleteUtxoByIdStmt = $this->dbh->prepare('DELETE FROM utxo WHERE id = :id');
        $this->deleteUtxosInView = $this->dbh->prepare('DELETE u FROM outpoints o join utxo u on (o.hashKey = u.hashKey)');

        $this->dropDatabaseStmt = $this->dbh->prepare('DROP DATABASE ' . $this->database);
        $this->insertToBlockIndexStmt = $this->dbh->prepare('INSERT INTO blockIndex ( hash ) SELECT id FROM headerIndex WHERE hash = :refHash ');
        $this->insertBlockStmt = $this->dbh->prepare('INSERT INTO blockIndex ( hash , block ) SELECT h.id, :block FROM headerIndex h WHERE h.hash = :hash');
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
        $this->loadLastBlockStmt = $this->dbh->prepare('
            SELECT h.* FROM iindex AS node,
                             iindex AS parent
            INNER JOIN blockIndex b ON b.hash = parent.header_id
            JOIN headerIndex h ON h.id = parent.header_id
            WHERE node.header_id = :id AND node.lft BETWEEN parent.lft AND parent.rgt
            ORDER BY parent.rgt
            LIMIT 1');
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
        //$stmtIndex = $this->dbh->prepare('INSERT INTO iindex (header_id, lft, rgt) VALUES (:headerId, :lft, :rgt)');
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
            'nBits' => $header->getBits()->getInt(),
            'nTimestamp' => $header->getTimestamp(),
            'nNonce' => $header->getNonce()
        ))
        ) {

            //if ($stmtIndex->execute([
            //    'headerId' => $this->dbh->lastInsertId(),
            //    'lft' => 1,
             //   'rgt' => 2
            //])
            //) {
                return true;
            //}
        }

        throw new \RuntimeException('Failed to update insert Genesis block index!');
    }

    /**
     * @param BufferInterface $blockHash
     * @param BlockInterface $block
     * @param BlockSerializerInterface $blockSerializer
     * @return int
     */
    public function insertBlock(BufferInterface $blockHash, BlockInterface $block, BlockSerializerInterface $blockSerializer)
    {
        // Insert the block header ID
        $this->insertBlockStmt->execute(['hash' => $blockHash->getBinary(), 'block' => $blockSerializer->serialize($block)->getBinary()]);
        return $this->dbh->lastInsertId();
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
     * @param OutPointSerializer $serializer
     * @param array $deleteOutPoints
     * @param array $newUtxos
     * @param array $specificDeletes
     */
    public function updateUtxoSet(OutPointSerializer $serializer, array $deleteOutPoints, array $newUtxos, array $specificDeletes = [])
    {
        $deleteUtxos = false;
        $useAppendList = false;
        if (true === $useAppendList) {
            if (false === empty($specificDeletes)) {
                $deleteUtxos = true;
                $this->appendUtxoViewKeys($specificDeletes);
            }
        }

        if (!$deleteUtxos && count($deleteOutPoints) > 0) {
            $deleteUtxos = true;
        }

        if (true === $deleteUtxos) {
            $this->deleteUtxosInView->execute();
        }

        if (false === $useAppendList) {
            if (count($specificDeletes) > 0) {
                foreach ($specificDeletes as $delete) {
                    $this->deleteUtxoStmt->execute([$delete]);
                }
            }
        }

        if (count($newUtxos) > 0) {
            $utxoQuery = [];
            $utxoValues = [];
            foreach ($newUtxos as $c => $utxo) {
                $utxoQuery[] = "(:hash$c, :v$c, :s$c)";
                $utxoValues["hash$c"] = $serializer->serialize($utxo->getOutPoint())->getBinary();
                $utxoValues["v$c"] = $utxo->getOutput()->getValue();
                $utxoValues["s$c"] = $utxo->getOutput()->getScript()->getBinary();
            }

            $insertUtxos = $this->dbh->prepare('INSERT INTO utxo (hashKey, value, scriptPubKey) VALUES ' . implode(', ', $utxoQuery));
            $insertUtxos->execute($utxoValues);
        }
    }

    /**
     * @param ChainSegment $history
     * @return BlockIndexInterface
     */
    public function findSegmentBestBlock(array $history)
    {
        $queryValues = [];
        $queryBind = [];
        foreach ($history as $c => $segment) {
            $queryValues[] = "h.segment = :seg$c";
            $queryBind['seg' . $c] = $segment->getId();
        }

        $tail = implode(" OR ", $queryValues);
//        $query = "SELECT MAX(b.id) as maxid, h.* FROM blockIndex b JOIN headerIndex h on (b.hash = h.id) WHERE " . $tail . " GROUP BY b.id";
        $query = "SELECT * from headerIndex where id = (SELECT MAX(b.hash) FROM blockIndex b JOIN headerIndex h on (b.hash = h.id) WHERE " . $tail . " LIMIT 1)";
        echo "Generated: $query\n";
        $sql = $this->dbh->prepare($query);
        $sql->execute($queryBind);
        $result = $sql->fetch(\PDO::FETCH_ASSOC);

        $index = new BlockIndex(
            new Buffer($result['hash'], 32),
            $result['height'],
            $result['work'],
            new BlockHeader(
                $result['version'],
                new Buffer($result['prevBlock'], 32),
                new Buffer($result['merkleRoot'], 32),
                $result['nTimestamp'],
                Buffer::int($result['nBits'], 4),
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
        //$fetchParent = $this->fetchLftStmt;
        //$resizeIndex = $this->updateIndicesStmt;

        //$fetchParent->bindValue(':prevBlock', $batch->getTip()->getIndex()->getHash()->getBinary());
        //if ($fetchParent->execute()) {
//            foreach ($fetchParent->fetchAll() as $record) {
//                $myLeft = $record['lft'];
//            }
//        }

//        $fetchParent->closeCursor();
//        if (!isset($myLeft)) {
//            throw new \RuntimeException('Failed to extract header position');
//        }

        $index = $batch->getIndices();
        $segment = $batch->getTip()->getSegment()->getId();
        //$totalN = count($index);
        //$nTimesTwo = 2 * $totalN;
        //$leftOffset = $myLeft;
        //$rightOffset = $myLeft + $nTimesTwo;

        $this->transaction(function () use ($index, $segment) {
            //$resizeIndex->execute(['nTimes2' => $nTimesTwo, 'myLeft' => $myLeft]);
            //$resizeIndex->closeCursor();

            $headerValues = [];
            $headerQuery = [];

            //$indexValues = [];
            //$indexQuery = [];

            $c = 0;
            foreach ($index as $i) {
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
                $headerValues['nBits' . $c] = $header->getBits()->getInt();
                $headerValues['nTimestamp' . $c] = $header->getTimestamp();
                $headerValues['nNonce' . $c] = $header->getNonce();
                
                $c++;
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
        });

        echo "done\n";
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
     * @param OutPointInterface[] $outpoints
     * @param array $queryValues
     * @return mixed
     */
    private function createOutpointsJoinSql(array $outpoints, array & $queryValues)
    {
        $joinList = [];
        foreach ($outpoints as $i => $outpoint) {
            $queryValues['hashParent' . $i] = $outpoint->getTxId()->getBinary();
            $queryValues['noutparent' . $i] = $outpoint->getVout();

            if (0 === $i) {
                $joinList[] = 'SELECT :hashParent' . $i . ' as hashPrevOut, :noutparent' . $i . ' as nOutput';
            } else {
                $joinList[] = '  SELECT :hashParent' . $i . ', :noutparent' . $i;
            }
        }

        return implode(PHP_EOL . "   UNION ALL " . PHP_EOL, $joinList);
    }

    /**
     * @param OutPointSerializer $serializer
     * @param array $outpoints
     * @param array $queryValues
     * @return string
     */
    private function createInsertJoinSql(OutPointSerializer $serializer, array $outpoints, array & $queryValues)
    {
        $joinList = [];
        foreach ($outpoints as $i => $outpoint) {
            $queryValues['hashParent' . $i] = $serializer->serialize($outpoint)->getBinary();
            $joinList[] = "(:hashParent$i)";
        }

        return "INSERT INTO outpoints (hashKey) VALUES " . implode(", ", $joinList);
    }

    /**
     * @param OutPointSerializer $outpointSerializer
     * @param OutPointInterface[] $outpoints
     * @return \BitWasp\Bitcoin\Utxo\Utxo[]
     */
    public function fetchUtxoDbList(OutPointSerializer $outpointSerializer, array $outpoints)
    {
        $requiredCount = count($outpoints);
        if (0 === count($outpoints)) {
            return [];
        }

        $this->truncateOutpointsStmt->execute();

        $t1 = microtime(true);

        $iv = [];
        $i = $this->dbh->prepare($this->createInsertJoinSql($outpointSerializer, $outpoints, $iv));
        $i->execute($iv);

        $this->selectUtxosByOutpointsStmt->execute();
        $rows = $this->selectUtxosByOutpointsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $outputSet = [];
        foreach ($rows as $utxo) {
            $outpoint = $outpointSerializer->parse(new Buffer($utxo['hashKey']));
            $outputSet[] = new DbUtxo($utxo['id'], $outpoint, new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey']))));
        }

        if (count($outputSet) < $requiredCount) {
            throw new \RuntimeException('Less than (' . count($outputSet) . ') required amount (' . $requiredCount . ')returned');
        }

        echo "utxos took " . (microtime(true) - $t1) . " seconds\n";
        return $outputSet;
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
