<?php

namespace BitWasp\Bitcoin\Node;


use BitWasp\Bitcoin\Block\BlockHeader;
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
    private $fetchTipsStmt;

    /**
     * @var \PDOStatement
     */
    private $chainPathStmt;

    /**
     * @var \PDOStatement
     */
    private $fetchLftStmt;

    /**
     * @var \PDOStatement
     */
    private $updateIndicesStmt;

    /**
     * @var bool
     */
    private $debug;

    /**
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
     * @param string $hash
     * @return bool
     */
    public function haveHeader($hash)
    {
        if ($this->debug) {
            echo "db: called haveHeader ($hash)\n";
        }

        if (null == $this->haveHeaderStmt) {
            $this->haveHeaderStmt = $this->dbh->prepare('SELECT COUNT(*) as count FROM headerIndex WHERE hash = :hash');
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
              SELECT
                i.*
              FROM headerIndex i
              WHERE i.hash = :hash
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
            hash, height, work, version, prevBlock, merkleRoot,
            nBits, nTimestamp, nNonce, lft, rgt
          ) VALUES (
            :hash, :height, :work, :version, :prevBlock, :merkleRoot,
            :nBits, :nTimestamp, :nNonce, :lft, :rgt
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
     * @param Index\Headers $headers
     * @return array
     */
    public function fetchTips(Index\Headers $headers)
    {
        if ($this->debug) {
            echo "db: called fetchTips\n";
        }

        if (null == $this->fetchTipsStmt) {
            $this->fetchTipsStmt = $this->dbh->prepare("SELECT h.* FROM headerIndex h WHERE rgt = lft + 1;");
            $this->chainPathStmt = $this->dbh->prepare("
                SELECT parent.hash
                FROM headerIndex AS node,
                     headerIndex AS parent
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                                AND node.hash = :hash
                ORDER BY node.lft, parent.height;
            ");
        }

        $getTips = $this->fetchTipsStmt;
        $getChainPath = $this->chainPathStmt;

        if ($getTips->execute()) {
            $tips = array();
            while ($tip = $getTips->fetch()) {
                $getChainPath->execute(['hash' => $tip['hash']]);
                $mapArr = $getChainPath->fetchAll(\PDO::FETCH_COLUMN);

                $tips[$tip['hash']] = $headers->newTip(
                    array_flip(array_flip($mapArr)),
                    new BlockIndex(
                        $tip['hash'],
                        $tip['height'],
                        $tip['work'],
                        new BlockHeader(
                            $tip['version'],
                            $tip['prevBlock'],
                            $tip['merkleRoot'],
                            $tip['nTimestamp'],
                            Buffer::int($tip['nBits']),
                            $tip['nNonce']
                        )
                    )
                );
            }

            $getTips->closeCursor();

            return $tips;
        }

        throw new \RuntimeException('Failed to query tips');
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

                $sql = sprintf("
                INSERT INTO headerIndex (
                    hash, height, work,
                    version, prevBlock, merkleRoot,
                    nBits, nTimestamp, nNonce,
                    lft, rgt
                ) VALUES %s
            ", implode(', ', $query));

                $stmt = $this->dbh->prepare($sql);
                $count = $stmt->execute($values);
                $this->dbh->commit();
                if ($count == $totalN) {
                    return true;
                } else {
                    throw new \RuntimeException('Strange: Failed to update chain!');
                }
            }
        } catch (\Exception $e) {
            echo "ROLLBACK!\n";
            $this->dbh->rollBack();
            throw $e;
        }

        throw new \RuntimeException('Failed to update chain!');
    }
}