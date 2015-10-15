<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Block\Block;
use BitWasp\Bitcoin\Block\BlockHeader;
use BitWasp\Bitcoin\Block\BlockInterface;
use BitWasp\Bitcoin\Chain\BlockLocator;
use BitWasp\Bitcoin\Node\Index\Headers;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionCollection;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInputCollection;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Bitcoin\Transaction\TransactionOutputCollection;
use BitWasp\Bitcoin\Utxo\Utxo;
use BitWasp\Buffertools\Buffer;
use Packaged\Config\ConfigProviderInterface;

class MySqlDb
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
    private $haveHeaderStmt;

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
     * @param BlockIndex $index
     * @return bool
     */
    public function insertIndexGenesis(BlockIndex $index)
    {
        if ($this->debug) {
            echo "db: called insertIndexGenesis\n";
        }

        $stmt = $this->dbh->prepare("
          INSERT INTO headerIndex (
            hash, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce, lft, rgt
          ) VALUES (
            :hash, :height, :work, :version, :prevBlock, :merkleRoot, :nBits, :nTimestamp, :nNonce, :lft, :rgt
          )
        ");

        $header = $index->getHeader();
        if ($stmt->execute(array(
            'hash' => $index->getHash(),
            'height' => $index->getHeight(),
            'work' => $index->getWork(),
            'version' => $header->getVersion(),
            'prevBlock' => $header->getPrevBlock(),
            'merkleRoot' => $header->getMerkleRoot(),
            'nBits' => $header->getBits()->getInt(),
            'nTimestamp' => $header->getTimestamp(),
            'nNonce' => $header->getNonce(),
            'lft' => 1,
            'rgt' => 2
        ))) {
            return true;
        }

        throw new \RuntimeException('Failed to update insert Genesis block!');
    }

    /**
     * @param BlockInterface $block
     */
    public function insertBlockGenesis(BlockInterface $block)
    {
        if ($this->debug) {
            echo "db: called insertBlockGenesis\n";
        }

        $stmt = $this->dbh->prepare("INSERT INTO blockIndex (hash) VALUES (:hash)");

        $stmt->bindValue(':hash', $block->getHeader()->getBlockHash());
        $stmt->execute();
    }

    /**
     * @param BlockInterface $block
     * @return bool
     * @throws \Exception
     */
    public function insertBlock(BlockInterface $block)
    {
        if ($this->debug) {
            echo "db: called insertBlock \n";
        }

        $blockHash = $block->getHeader()->getBlockHash();

        try {
            $this->dbh->beginTransaction();

            $txListBind = [];
            $txListData = ['blockHash' => $blockHash];

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
            $nTx = count($transactions);
            for ($i = 0; $i < $nTx; $i++) {
                $tx = $transactions->getTransaction($i);
                $hash = $tx->getTransactionId();
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
                    $inData["hashPrevOut$i$j"] = $input->getTransactionId();
                    $inData["nPrevOut$i$j"] = $input->getVout();
                    $inData["scriptSig$i$j"] = $input->getScript()->getBinary();
                    $inData["nSequence$i$j"] = $input->getSequence();
                }

                $outData["parenthash$i"] = $hash;
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
            $blockInsert = $this->dbh->prepare("INSERT INTO blockIndex ( hash ) VALUES ( :hash )");
            $blockInsert->bindValue(':hash', $blockHash);

            $insertTx = $this->dbh->prepare("INSERT INTO transactions (hash, version, nLockTime, transaction, nOut, valueOut, valueFee, isCoinbase ) VALUES " . implode(", ", $txBind));
            unset($txBind);

            $insertTxList = $this->dbh->prepare("INSERT INTO block_transactions (block_hash, transaction_hash) VALUES " . implode(", ", $txListBind));
            unset($txListBind);

            $insertInputs = $this->dbh->prepare("INSERT INTO transaction_input (parent_tx, nInput, hashPrevOut, nPrevOut, scriptSig, nSequence) VALUES " . implode(", ", $inBind));
            unset($inBind);

            $insertOutputs = $this->dbh->prepare("INSERT INTO transaction_output (parent_tx, nOutput, value, scriptPubKey) VALUES " . implode(", ", $outBind));
            unset($outBind);

            $blockInsert->execute();
            $insertTxList->execute($txListData);
            $insertTx->execute($txData);
            $insertInputs->execute($inData);
            $insertOutputs->execute($outData);

            $this->dbh->commit();
            return true;

        } catch (\Exception $e) {
            echo "INSERT FAIL!\n";
            echo $e->getMessage() . "\n";
            die();
            $this->dbh->rollBack();
            return;
        }
    }

    /**
     * @param BlockIndex $startIndex
     * @param BlockIndex[] $index
     * @return bool
     * @throws \Exception
     */
    public function insertIndexBatch(BlockIndex $startIndex, array $index)
    {
        if ($this->debug) {
            echo "db: called insertIndexBATCH\n";
        }

        if (null == $this->fetchLftStmt) {
            $this->fetchLftStmt = $this->dbh->prepare('SELECT lft FROM headerIndex WHERE hash = :prevBlock');
            $this->updateIndicesStmt = $this->dbh->prepare('
                UPDATE headerIndex SET rgt = rgt + :nTimes2 WHERE rgt > :myLeft ;
                UPDATE headerIndex SET lft = lft + :nTimes2 WHERE lft > :myLeft ;
            ');
        }

        $fetchParent = $this->fetchLftStmt;
        $updateIndices = $this->updateIndicesStmt;

        $fetchParent->bindParam(':prevBlock', $startIndex->getHash());
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
            if ($updateIndices->execute(['nTimes2' => $nTimesTwo, 'myLeft' => $myLeft])) {
                $updateIndices->closeCursor();

                $values = [];
                $query = [];
                foreach ($index as $c => $i) {
                    $query[] = "(:hash$c , :height$c , :work$c ,
                    :version$c , :prevBlock$c , :merkleRoot$c ,
                    :nBits$c , :nTimestamp$c , :nNonce$c ,
                    :lft$c , :rgt$c )";

                    $values['hash' . $c] = $i->getHash();
                    $values['height' . $c] = $i->getHeight();
                    $values['work' . $c] = $i->getWork();

                    $header = $i->getHeader();
                    $values['version' . $c] = $header->getVersion();
                    $values['prevBlock' . $c] = $header->getPrevBlock();
                    $values['merkleRoot' . $c] = $header->getMerkleRoot();
                    $values['nBits' . $c] = $header->getBits()->getInt();
                    $values['nTimestamp' . $c] = $header->getTimestamp();
                    $values['nNonce' . $c] = $header->getNonce();

                    $values['lft' . $c] = $leftOffset + 1 + $c;
                    $values['rgt' . $c] = $rightOffset - $c;
                }

                $stmt = $this->dbh->prepare("
                  INSERT INTO headerIndex (hash, height, work, version, prevBlock, merkleRoot, nBits, nTimestamp, nNonce, lft, rgt )
                  VALUES " . implode(', ', $query));

                $count = $stmt->execute($values);
                $this->dbh->commit();
                if ($count == $totalN) {
                    return true;
                } else {
                    throw new \RuntimeException('Strange: Failed to update chain!');
                }
            }
        } catch (\Exception $e) {
            $this->dbh->rollBack();
            throw $e;
        }

        throw new \RuntimeException('Failed to update chain!');
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function haveHeader($hash)
    {
        if ($this->debug) {
            echo "db: called haveHeader ($hash)\n";
        }

        if (null == $this->haveHeaderStmt) {
            $this->haveHeaderStmt = $this->dbh->prepare('
              SELECT    COUNT(*) as count
              FROM      headerIndex
              WHERE     hash = :hash
            ');
        }

        $stmt = $this->haveHeaderStmt;
        $stmt->bindParam(':hash', $hash);

        if ($stmt->execute()) {
            $fetch = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($fetch[0]['count'] == 1) {
                return true;
            }

            return false;
        }

        throw new \RuntimeException('Failed to execute haveHeader query');
    }

    /**
     * @param string $hash
     * @return BlockIndex
     */
    public function fetchIndex($hash)
    {
        if ($this->debug) {
            echo "db: called fetchIndex\n";
        }

        if (null == $this->fetchIndexStmt) {
            $this->fetchIndexStmt = $this->dbh->prepare('
               SELECT     i.*
               FROM       headerIndex i
               WHERE      i.hash = :hash
            ');
        }

        $stmt = $this->fetchIndexStmt;
        $stmt->bindParam(':hash', $hash);

        if ($stmt->execute()) {
            $row = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($row) == 1) {
                $row = $row[0];
                return new BlockIndex(
                    $row['hash'],
                    $row['height'],
                    $row['work'],
                    new BlockHeader(
                        $row['version'],
                        $row['prevBlock'],
                        $row['merkleRoot'],
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
     * @param string $blockHash
     * @return TransactionCollection
     */
    public function fetchBlockTransactions($blockHash)
    {
        if ($this->debug) {
            echo "db: called fetchBlockTransactions ($blockHash)\n";
        }

        if (null === $this->txsStmt) {
            $this->txsStmt = $this->dbh->prepare('
                SELECT t.hash, t.version, t.nLockTime
                FROM transactions t
                JOIN block_transactions bt ON bt.transaction_hash = t.hash
                WHERE bt.block_hash = :hash
            ');

            $this->txInStmt = $this->dbh->prepare('
                SELECT txIn.parent_tx, txIn.hashPrevOut, txIn.nPrevOut, txIn.scriptSig, txIn.nSequence
                FROM transaction_input txIn
                JOIN block_transactions bt ON bt.transaction_hash = txIn.parent_tx
                WHERE bt.block_hash = :hash
                GROUP BY txIn.parent_tx
                ORDER BY txIn.nInput
            ');

            $this->txOutStmt = $this->dbh->prepare('
              SELECT    txOut.parent_tx, txOut.value, txOut.scriptPubKey
              FROM      transaction_output txOut
              JOIN      block_transactions bt ON bt.transaction_hash = txOut.parent_tx
              WHERE     bt.block_hash = :hash
              GROUP BY  txOut.parent_tx
              ORDER BY  txOut.nOutput
            ');
        }

        // We pass a callback instead of looping
        $this->txsStmt->bindValue(':hash', $blockHash);
        $this->txsStmt->execute();
        $txs = [];
        $this->txsStmt->fetchAll(\PDO::FETCH_FUNC, function ($hash, $version, $locktime) use (&$txs) {
            $txs[$hash] = [
                'hash' => $hash,
                'version' => $version,
                'nLockTime' => $locktime,
                'inputs' => new TransactionInputCollection(),
                'outputs' => new TransactionOutputCollection()
            ];
        });

        $this->txInStmt->bindParam(':hash', $blockHash);
        $this->txInStmt->execute();
        $this->txInStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $hashPrevOut, $nPrevOut, $scriptSig, $nSequence) use (&$txs) {
            $txs[$parent_tx]['inputs']->addInput(new TransactionInput($hashPrevOut, $nPrevOut, new Script(new Buffer($scriptSig)), $nSequence));
        });

        $this->txOutStmt->bindParam(':hash', $blockHash);
        $this->txOutStmt->execute();
        $this->txOutStmt->fetchAll(\PDO::FETCH_FUNC, function ($parent_tx, $value, $scriptPubKey) use (&$txs) {
            $txs[$parent_tx]['outputs']->addOutput(new TransactionOutput($value, new Script(new Buffer($scriptPubKey))));
        });

        $collection = new TransactionCollection();
        foreach (array_keys($txs) as $hash) {
            $collection->addTransaction(new Transaction(
                $txs[$hash]['version'],
                $txs[$hash]['inputs'],
                $txs[$hash]['outputs'],
                $txs[$hash]['nLockTime']
            ));
        }

        unset($txs);
        return $collection;
    }

    /**
     * @param string $hash
     * @return BlockInterface
     */
    public function fetchBlock($hash)
    {
        if ($this->debug) {
            echo "db: called fetchBlock ($hash)\n";
        }

        $stmt = $this->dbh->prepare('
           SELECT     h.hash, h.version, h.prevBlock, h.merkleRoot, h.nBits, h.nNonce, h.nTimestamp
           FROM       blockIndex b
           JOIN       headerIndex h ON b.hash = h.hash
           WHERE      b.hash = :hash
        ');

        $stmt->bindValue(':hash', $hash);
        if ($stmt->execute()) {
            $r = $stmt->fetch();
            $stmt->closeCursor();
            if ($r) {
                return new Block(
                    Bitcoin::getMath(),
                    new BlockHeader(
                        $r['version'],
                        $r['prevBlock'],
                        $r['merkleRoot'],
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
     * Query for headers/blocks chain state - populates Chains.
     *
     * @param Headers $headers
     * @return ChainState[]
     */
    public function fetchChainState(Headers $headers)
    {
        if ($this->debug) {
            echo "db: called fetchChainState \n";
        }

        $stmt = $this->dbh->prepare('
SELECT * FROM (
            SELECT
                parent.hash as last_hash, parent.work as last_work, parent.height as last_height
              , parent.version as last_version, parent.prevBlock as last_prevBlock, parent.merkleRoot as last_merkleRoot
              , parent.nBits as last_nBits, parent.nTimestamp as last_nTimestamp, parent.nNonce as last_nNonce

              , tip.hash as tip_hash, tip.height as tip_height, tip.work as tip_work
              , tip.version as tip_version, tip.prevBlock tip_prevBlock, tip.merkleRoot as tip_merkleRoot
              , tip.nBits as tip_nBits, tip.nTimestamp as tip_nTimestamp, tip.nNonce as tip_nNonce

FROM headerIndex AS tip, headerIndex AS parent
LEFT JOIN headerIndex AS next ON next.prevBlock = parent.hash
LEFT JOIN blockIndex AS b ON b.hash = next.hash
WHERE tip.rgt = tip.lft + 1 and b.hash IS NULL
) as r
GROUP BY r.tip_hash;');

        if ($stmt->execute()) {
            $chainPathStmt = $this->dbh->prepare("
               SELECT   node.hash, parent.hash
               FROM     headerIndex AS node,
                        headerIndex AS parent
               WHERE    node.rgt = node.lft + 1
               AND      node.lft BETWEEN parent.lft AND parent.rgt
            ");
            $chainPathStmt->execute();
            $fetch = $chainPathStmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);

            $states = [];
            $math = Bitcoin::getMath();

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $map = $fetch[$row['tip_hash']];
                $states[] =
                    new ChainState(
                        $math,
                        new Chain(
                            $map,
                            new BlockIndex(
                                $row['tip_hash'],
                                $row['tip_height'],
                                $row['tip_work'],
                                new BlockHeader(
                                    $row['tip_version'],
                                    $row['tip_prevBlock'],
                                    $row['tip_merkleRoot'],
                                    $row['tip_nTimestamp'],
                                    Buffer::int($row['tip_nBits'], 4),
                                    $row['tip_nNonce']
                                )
                            ),
                            $headers,
                            $math
                        ),
                        new BlockIndex(
                            $row['last_hash'],
                            $row['last_height'],
                            $row['last_work'],
                            new BlockHeader(
                                $row['last_version'],
                                $row['last_prevBlock'],
                                $row['last_merkleRoot'],
                                $row['last_nTimestamp'],
                                Buffer::int($row['last_nBits'], 4),
                                $row['last_nNonce']
                            )
                        )
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
        if ($this->debug) {
            echo "db: called findFork\n";
        }
        $hashes = [$activeChain->getIndex()->getHash()];
        foreach ($locator->getHashes() as $hash) {
            $hashes[] = $hash->getHex();
        }

        $placeholders = rtrim(str_repeat('?, ', count($hashes) - 1), ', ') ;
        $stmt = $this->dbh->prepare("
            SELECT    node.hash
            FROM      headerIndex AS node,
                      headerIndex AS parent
            WHERE     parent.hash = ? AND node.hash in ($placeholders)
            ORDER BY  node.rgt LIMIT 1
        ");

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
        if ($this->debug) {
            echo "db: called fetchNextHeaders ($hash)\n";
        }
        $stmt = $this->dbh->prepare("
            SELECT    child.version, child.prevBlock, child.merkleRoot,
                      child.nTimestamp, child.nBits, child.nNonce, child.height
            FROM      headerIndex AS child, headerIndex AS parent
            WHERE     child.rgt < parent.rgt
            AND       parent.hash = :hash
            LIMIT     2000
        ");

        $stmt->bindParam(':hash', $hash);
        if ($stmt->execute()) {
            $results = array();
            foreach ($stmt->fetchAll() as $row) {
                $results[] = new BlockHeader(
                    $row['version'],
                    $row['prevBlock'],
                    $row['merkleRoot'],
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
     * @param Chain $activeChain
     * @return UtxoView
     * @throws \Exception
     */
    public function fetchUtxoView(BlockInterface $block, Chain $activeChain)
    {
        $txs = $block->getTransactions();
        $txCount = count($txs);
        if (1 == $txCount) {
            return new UtxoView([]);
        }

        $joinList = '';
        $queryValues = ['hash' => $block->getHeader()->getBlockHash()];
        $last = $txCount - 1;
        $c = 0;

        for ($i = 1; $i < $txCount; $i++) {
            $utxo_txid = $txs->getTransaction($i);
            $inputs = $utxo_txid->getInputs();
            $lastIn = count($inputs) - 1;

            foreach ($inputs->getInputs() as $vin => $in) {
                if (0 == $c) {
                    $joinList .= "SELECT :hparent$c as hashParent, :noutparent$c as nOut, :c$c as txidx\n";
                } else {
                    $joinList .= "  SELECT :hparent$c, :noutparent$c, :c$c \n";
                }

                if ($vin < $lastIn || $i < $last ) {
                    $joinList .= "  UNION ALL\n";
                }

                $queryValues["hparent$c"] = $in->getTransactionId();
                $queryValues["c$c"] = $i;
                $queryValues["noutparent$c"] = $in->getVout();
                $c++;
            }
        }

        $stmt = $this->dbh->prepare("
              SELECT    listed.hashParent as txid, listed.nOut as vout,
                        o.value, o.scriptPubKey,
                        allowed_block.height, listed.txidx
              FROM      transaction_output o
              RIGHT JOIN (
                $joinList
              ) as listed ON (listed.hashParent = o.parent_tx AND listed.nOut = o.nOutput)
              LEFT JOIN (
                    SELECT    b.transaction_hash, parent.height
                    FROM      headerIndex AS tip,
                              headerIndex AS parent
                    JOIN      block_transactions b on (b.block_hash = parent.hash)
                    WHERE     tip.hash = :hash AND tip.lft BETWEEN parent.lft AND parent.rgt
              ) as allowed_block on o.parent_tx = allowed_block.transaction_hash
              WHERE listed.hashParent IS NOT NULL");

        try {
            $stmt->execute($queryValues);

            $cache = [];
            $utxos = [];

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $utxo) {
                $tx = $txs->getTransaction($utxo['txidx']);
                $newtx = $tx->getTransactionId();

                // We will always have these
                $txid = $utxo['txid'];
                $vout = $utxo['vout'];

                // Populate $value and $script, relying on transactions
                // coming in sequence in a block
                if (!is_null($utxo['scriptPubKey'])) {
                    $output = new TransactionOutput($utxo['value'], new Script(new Buffer($utxo['scriptPubKey'])));
                } else {
                    if (!isset($cache[$txid]) || !isset($cache[$txid][$vout])) {
                        throw new \RuntimeException('Utxo not found in storage or block!');
                    }
                    $output = $cache[$txid][$vout];
                }

                // To allow parsing chains of transactions, keep an
                // indexed temporary store of all utxos, because its
                if (!isset($cache[$newtx])) {
                    $cache[$newtx] = [];
                    foreach ($tx->getOutputs()->getOutputs() as $v => $tmp) {
                        $cache[$newtx][$v] = $tmp;
                    }
                }

                $utxos[] = new Utxo($txid, $vout, $output);
            }

            if (count($utxos) !== $c) {
                throw new \RuntimeException('Either invalid block, or chain of dependent transactions');
            }

            return new UtxoView($utxos);

        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            throw $e;
        }
    }

}