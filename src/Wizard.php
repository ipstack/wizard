<?php

namespace Ipstack\Wizard;

use Ipstack\Wizard\Entity\Network;
use Ipstack\Wizard\Entity\Register;
use Ddrv\Extra\Pack;
use Ipstack\Wizard\Field\FieldAbstract;
use PDO;
use PDOException;

/**
 * Class Wizard
 *
 * @const int FORMAT_VERSION
 * @property string $tmpDir
 * @property string $author
 * @property string $license
 * @property int    $time
 * @property array  $networks
 * @property Register[]  $registers
 * @property array  $relations
 * @property PDO    $pdo
 * @property string $prefix
 * @property array $meta
 */
class Wizard
{
    /**
     * @const int
     */
    const FORMAT_VERSION = 2;

    /**
     * @var string
     */
    protected $tmpDir;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var string
     */
    protected $license;

    /**
     * @var integer
     */
    protected $time;

    /**
     * @var array
     */
    protected $networks = array();

    /**
     * @var Register[]
     */
    protected $registers = array();

    /**
     * @var array
     */
    protected $relations = array();

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var array $meta
     */
    protected $meta = array(
        'index' => array(),
        'registers' => array(),
        'relations' => array(
            'format' => '',
            'len' => 3,
            'items' => 0,
        ),
        'networks' => array(
            'format' => '',
            's' => 4,
            'n' => 0,
            'fields' => array(),
        ),
        'maxItemLen' => 0,
    );

    /**
     * Wizard constructor.
     *
     * @param string $tmpDir
     * @throws \InvalidArgumentException
     */
    public function __construct($tmpDir)
    {
        if (!is_string($tmpDir)) {
            throw new \InvalidArgumentException('incorrect tmpDir');
        }
        if (!is_dir($tmpDir)) {
            throw new \InvalidArgumentException('tmpDir is not a directory');
        }
        if (!is_writable($tmpDir)) {
            throw new \InvalidArgumentException('tmpDir is not a writable');
        }
        $this->tmpDir = $tmpDir;
        $this->prefix = $this->tmpDir.DIRECTORY_SEPARATOR.'iptool.wizard.'.uniqid();
    }

    /**
     * Set author.
     *
     * @param string $author
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAuthor($author)
    {
        if (!is_string($author)) {
            throw new \InvalidArgumentException('incorrect author');
        }
        if (mb_strlen($author) > 64) $author = mb_substr($author,0,64);
        $this->author = $author;
        return $this;
    }

    /**
     * Set license.
     *
     * @param string $license
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setLicense($license)
    {
        if (!is_string($license)) {
            throw new \InvalidArgumentException('incorrect license');
        }
        $this->license = $license;
        return $this;
    }

    /**
     * Set time.
     *
     * @param integer $time
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTime($time)
    {
        if (!is_int($time) || $time < 0) {
            throw new \InvalidArgumentException('incorrect time');
        }
        $this->time = $time;
        return $this;
    }

    /**
     * Add register.
     *
     * @param string $name
     * @param Register $register
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addRegister($name, $register)
    {
        if (!Register::checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!($register instanceof Register)) {
            throw new \InvalidArgumentException('incorrect register');
        }
        $fields = $register->getFields();
        if (empty($fields)) {
            throw new \InvalidArgumentException('fields of register can not be empty');
        }
        $this->registers[$name] = $register;
        return $this;
    }

    /**
     * @param Network $network
     * @param int $column
     * @param string $name
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addField($network, $column, $name)
    {
        if (!($network instanceof Network)) {
            throw new \InvalidArgumentException('incorrect network');
        }
        if (!is_int($column) || $column < 1) {
            throw new \InvalidArgumentException('column must be positive integer');
        }
        if (!Register::checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!isset($this->registers[$name])) {
            throw new \InvalidArgumentException('register '.$name.' not added');
        }
        $this->networks[] = array(
            'network' => $network,
            'map' => array(
                $column => $name
            ),
        );
        return $this;
    }

    /**
     * Add relation
     *
     * @param string $parent
     * @param string $field
     * @param string $child
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addRelation($parent, $field, $child)
    {
        if (!isset($this->registers[$parent])) {
            throw new \InvalidArgumentException('parent register not exists');
        }
        if (!isset($this->registers[$child])) {
            throw new \InvalidArgumentException('child register not exists');
        }
        if (!is_string($field)) {
            throw new \InvalidArgumentException('incorrect field');
        }
        $this->relations[$parent][$field] = $child;
        return $this;
    }

    /**
     * Compile database.
     *
     * @param string $filename
     * @void
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \ErrorException
     */
    public function compile($filename)
    {
        if (!is_string($filename)) {
            throw new \InvalidArgumentException('incorrect filename');
        }
        if (file_exists($filename) && !is_writable($filename)) {
            throw new \InvalidArgumentException('file not writable');
        }
        if (!file_exists($filename) && !is_writable(dirname($filename))) {
            throw new \InvalidArgumentException('directory not writable');
        }
        if (empty($this->time)) $this->time = time();

        $this->checkRelations();

        $tmpDb = $this->prefix.'.db.sqlite';
        try {
            $this->pdo = new PDO('sqlite:' . $tmpDb);
            $this->pdo->exec('PRAGMA foreign_keys = 1;PRAGMA integrity_check = 1;PRAGMA encoding = \'UTF-8\';');
        } catch (PDOException $e) {
            throw  $e;
        }
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->sortRegisters();

        $this->createTmpDb();

        foreach ($this->registers as $table=>$register) {
            $this->addRegisterInTmpDb($register, $table);
        }

        foreach ($this->networks as $network) {
            $this->addNetworkInTmpDb($network);
        }

        $this->cleanTmpDb();

        $this->definePackFormat();

        foreach ($this->registers as $table=>$register) {
            $this->compileRegister($table);
        }

        $this->compileNetwork();

        $this->compileHeader();

        $this->makeFile($filename);

        $this->pdo = null;

        unlink($tmpDb);

    }

    /**
     * Check for recursive relations.
     *
     * @void
     * @throws \ErrorException
     */
    protected function checkRelations()
    {
        foreach ($this->relations as $parent=>$relation) {
            foreach ($relation as $field=>$child) {
                if (isset($this->relations[$child]) && in_array($parent, $this->relations[$child])) {
                    throw new \ErrorException('relations can not be recursive');
                }
            }
        }
        $maxLenParent = 1;
        $maxLenField = 1;
        $maxLenChild = 1;
        foreach ($this->relations as $parent => $networkRelation) {
            if (strlen($parent) > $maxLenParent) $maxLenParent = strlen($parent);
            foreach ($networkRelation as $field => $child) {
                if (strlen($field) > $maxLenField) $maxLenField = strlen($field);
                if (strlen($child) > $maxLenChild) $maxLenChild = strlen($child);
                $this->meta['relations']['items'] ++;
                $this->meta['relations']['data'][] = array(
                    'p' => $parent,
                    'f' => $field,
                    'c' => $child
                );
            }
        }

        $this->meta['relations']['format'] = 'A'.$maxLenParent.'p'
            .'/A'.$maxLenField.'f'
            .'/A'.$maxLenChild.'c';
        $empty = array('p'=>null, 'f'=>null,'c'=>null);
        $this->meta['relations']['len'] = strlen(Pack::pack($this->meta['relations']['format'], $empty));
    }

    /**
     * Sort registers.
     *
     * @void
     */
    protected function sortRegisters()
    {
        $sortedRegisters = array();
        $deletedRegisters = $registers = array_keys($this->registers);
        foreach ($this->networks as $network) {
            foreach ($network['map'] as $register) {
                $nd = array_search($register, $deletedRegisters);
                if (false !== $nd) {
                    unset($deletedRegisters[$nd]);
                }
            }
        }
        foreach ($registers as $register) {
            $nd = array_search($register, $deletedRegisters);
            if (false !== $nd) {
                unset($deletedRegisters[$nd]);
            }
            if (!empty($this->relations[$register])) {
                foreach ($this->relations[$register] as $child) {
                    $nd = array_search($child, $deletedRegisters);
                    if (false !== $nd) {
                        unset($deletedRegisters[$nd]);
                    }
                    $nc = array_search($child, $sortedRegisters);
                    if (false === $nc) {
                        array_unshift($sortedRegisters, $child);
                        unset($registers[$child]);
                        $nc = 0;
                    }
                    $np = array_search($register, $sortedRegisters);
                    if (false === $np) {
                        array_splice($sortedRegisters, $nc+1, 0, $register);
                        unset($registers[$register]);
                    } elseif ( $np < $nc ) {
                        array_splice($sortedRegisters, $np+1, 1);
                        array_splice($sortedRegisters, $nc+1, 0, $register);
                    }
                }
            }
        }
        foreach ($sortedRegisters as $register) {
            if (in_array($register, $deletedRegisters)) {
                unset($this->registers[$register]);
            } else {
                $data = $this->registers[$register];
                unset($this->registers[$register]);
                $this->registers[$register] = $data;
            }
        }
    }

    /**
     * Create tmp sqlite database.
     *
     * @void
     */
    protected function createTmpDb()
    {
        $sql = '
            CREATE TABLE `_ips` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `ip` INTEGER,
                `action` TEXT,
                `parameter` TEXT,
                `value` TEXT,
                `offset` TEXT
            );
            CREATE INDEX `ip` ON `_ips` (`ip`);
            CREATE INDEX `parameter` ON `_ips` (`parameter`);
            CREATE INDEX `value` ON `_ips` (`value`);
        ';
        $this->pdo->exec($sql);

        foreach ($this->registers as $table=>$register) {
            $columns = $register->getFields();
            $fields = array('`_pk` TEXT', '`_offset` INTEGER');
            $index = array();
            foreach ($columns as $field=>$data) {
                $fields[] = '`' . $field . '`';
                $fields[] = '`_len_' . $field . '` INTEGER';
                $index[] = '`_len_' . $field . '`';
            }
            $constraints = array(
                'CONSTRAINT `_pk` PRIMARY KEY (`_pk`) ON CONFLICT IGNORE',
            );
            if (!empty($this->relations[$table])) {
                foreach ($this->relations[$table] as $field => $child) {
                    $line = 'CONSTRAINT `'.$table.'_'.$field.'_'.$child.'` FOREIGN KEY (`' . $field . '`)';
                    $line .= ' REFERENCES `' . $child . '` (`_pk`)';
                    $line .= ' ON DELETE CASCADE ON UPDATE CASCADE';
                    $constraints[] = $line;
                }
            }
            $sql = 'CREATE TABLE `'.$table.'` ('.implode(', ', $fields).', '.implode(', ', $constraints).');'.PHP_EOL;
            foreach ($index as $i) {
                $sql .= 'CREATE INDEX '.$i.' ON `'.$table.'` ('.$i.');'.PHP_EOL;
            }
            $this->pdo->exec($sql);
        }
    }

    /**
     * Create temporary register.
     *
     * @param Register $register
     * @param string $table
     * @void
     */
    protected function addRegisterInTmpDb($register, $table)
    {
        /**
         * @var FieldAbstract[] $validators
         */
        $validators = array();
        $source = $register->getCsv();
        $columns = $register->getFields();
        $fields = array('`_pk`');
        $params = array(':_pk');
        foreach ($columns as $field=>$data) {
            $fieldClassName = __NAMESPACE__.'\\Field\\'.mb_convert_case($data['type'], \MB_CASE_TITLE).'Field';
            $validators[$field] = new $fieldClassName($field, $data);
            $fields[] = '`' . $field . '`';
            $fields[] = '`_len_' . $field . '`';
            $params[] = ':' . $field;
            $params[] = ':_len_' . $field;
        }
        $sql = 'INSERT INTO `'.$table.'` (' . implode(',', $fields) . ') VALUES (' . implode(',', $params) . ');';
        $insertStatement = $this->pdo->prepare($sql);

        $firstRow = $register->getFirstRow()-1;
        $csv = fopen($source['file'], 'r');
        for($ignore=0; $ignore < $firstRow; $ignore++) {
            $row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape']);
            unset($row);
        }
        $rowIterator = 0;
        $idColumn = $register->getId()-1;
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        while ($row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape'])) {
            $rowIterator++;
            $rowId = $rowIterator;
            if ($idColumn >= 0 && isset($row[$idColumn])) {
                $rowId = $row[$idColumn];
            }
            $values = array(
                '_pk' => $rowId,
            );
            foreach ($columns as $field=>$data) {
                $column = $data['column']-1;
                $value = isset($row[$column])?$row[$column]:null;
                $value = $validators[$field]->validValue($value);
                $values[':'.$field] = $value;
                $values[':_len_'.$field] = strlen($value);
            };


            $insertStatement->execute($values);

            $transactionIterator++;
            if ($transactionIterator > 100000) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
                $transactionIterator = 0;
            }
        }
        $this->pdo->commit();
    }

    /**
     * Create temporary network.
     *
     * @param array $data
     * @void
     * @throws \ErrorException
     */
    protected function addNetworkInTmpDb($data)
    {
        /**
         * @var Network $network
         */
        $network = $data['network'];
        $source = $network->getCsv();

        $firstRow = $network->getFirstRow()-1;
        $csv = fopen($source['file'], 'r');
        for($ignore=0; $ignore < $firstRow; $ignore++) {
            $row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape']);
            unset($row);
        }
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        $sql = 'INSERT INTO `_ips` (`ip`,`action`,`parameter`,`value`) VALUES (:ip,:action,:parameter,:value);';
        $insert = $this->pdo->prepare($sql);
        while ($row = fgetcsv($csv, 4096, $source['delimiter'], $source['enclosure'], $source['escape'])) {
            $firstIpColumn = $network->getFistIpColumn()-1;
            $lastIpColumn = $network->getLastIpColumn()-1;
            if (!isset($row[$firstIpColumn])) {
                $this->pdo->rollBack();
                throw new \ErrorException('have not column with first ip address');
            }
            if (!isset($row[$lastIpColumn])) {
                $this->pdo->rollBack();
                throw new \ErrorException('have not column with last ip address');
            }
            $firstIp = $network->getLongIp($row[$firstIpColumn], false);
            $lastIp = $network->getLongIp($row[$lastIpColumn], true);
            foreach ($data['map'] as $column=>$register) {
                $column--;
                $value = isset($row[$column]) ? $row[$column] : null;
                $insert->execute(array(
                    'ip' => $firstIp,
                    'action' => 'add',
                    'parameter' => $register,
                    'value' => $value,
                ));
                $insert->execute(array(
                    'ip' => $lastIp + 1,
                    'action' => 'remove',
                    'parameter' => $register,
                    'value' => $value,
                ));
                $transactionIterator += 2;
                if ($transactionIterator > 100000) {
                    $transactionIterator = 0;
                    $this->pdo->commit();
                    $this->pdo->beginTransaction();
                }
                if (isset($row[$column])) {
                    $this->meta['networks']['fields'][$register] = null;
                }
            }
        }
        $this->pdo->commit();
    }

    /**
     * Clean unused data from temporary database.
     *
     * @void
     */
    protected function cleanTmpDb()
    {
        $registers = array_reverse(array_keys($this->registers));
        foreach ($registers as $parent) {
            if (!empty($this->relations[$parent])) {
                foreach ($this->relations[$parent] as $field => $child) {
                    $innerSql = 'SELECT `' . $field . '` FROM `' . $parent . '` GROUP BY `' . $field . '`';
                    $sql = 'DELETE FROM `' . $child . '` WHERE `_pk` NOT IN (' . $innerSql . ');';
                    $this->pdo->exec($sql);
                }
            }
        }
        foreach ($this->networks as $network) {
            foreach ($network['map'] as $register) {
                $innerSql = 'SELECT `_pk` FROM `'.$register.'` GROUP BY `_pk`';
                $sql = 'DELETE FROM `_ips` WHERE  WHERE `parameter` = "'.$register.'" AND `value` NOT IN ('
                    .$innerSql.');';
                $this->pdo->exec($sql);
            }
        }
    }

    /**
     * Define pack format.
     *
     * @void
     */
    protected function definePackFormat() {
        foreach ($this->registers as $table=>$register) {
            $fields = $register->getFields();
            $format = array();
            $empty = array();
            foreach ($fields as $field=>$data) {
                if (isset($this->relations[$table][$field])) {
                    $sql = 'SELECT COUNT(*) AS `max`, "0" AS `min` FROM `' . $this->relations[$table][$field] . '`;';
                    $res = $this->pdo->query($sql);
                    $row = $res->fetch();
                    $format[$field] = Pack::getOptimalFormat(0, $row['max'], $field);
                } else {
                    switch ($data['type']) {
                        case 'string':
                            $sql = 'SELECT MAX(`_len_' . $field . '`) AS `max`, "0" AS `min` FROM `'.$table.'`;';
                            $res = $this->pdo->query($sql);
                            $row = $res->fetch();
                            $format[$field] = 'A'.$row['max'].$field;
                            if (array_key_exists('maxLength', $data) && $data['maxLength'] === '~') {
                                $format[$field] = '~'.$field;
                            }
                            break;
                        default:
                            $fieldClassName = __NAMESPACE__.'\\Field\\'
                                .mb_convert_case($data['type'], \MB_CASE_TITLE).'Field';
                            /**
                             * @var FieldAbstract $validator
                             */
                            $validator = new $fieldClassName($field, $data);
                            $sql = 'SELECT MAX(`' . $field . '`) AS `max`, MIN(`' . $field . '`) AS `min` FROM `'
                                . $table . '`;';
                            $res = $this->pdo->query($sql);
                            $row = $res->fetch();
                            $validator->update($row['min']);
                            $validator->update($row['max']);
                            $format[$field] = $validator->getFormat();
                            break;
                    }
                }
                $empty[$field] = null;
            }
            $pack = implode('/', $format);
            $bin = Pack::pack($pack, $empty);

            $this->meta['registers'][$table]['format'] = $pack;
            $this->meta['registers'][$table]['s'] = strlen($bin);
            $this->meta['registers'][$table]['n'] = 0;
            $this->meta['registers'][$table]['fields'] = $empty;
        }
    }

    /**
     * Compile register from temporary db
     *
     * @param $register
     */
    protected function compileRegister($register)
    {
        $file = fopen($this->prefix.'.reg.'.$register.'.dat', 'w');
        $format = $this->meta['registers'][$register]['format'];
        $empty =  $this->meta['registers'][$register]['fields'];
        $bin = Pack::pack($format, $empty);
        fwrite($file, $bin);
        $offset = strlen($bin);
        $select = array(
            '*' => '`'.$register.'`.*',
        );
        $join = array();
        if (isset($this->relations[$register])) {
            foreach ($this->relations[$register] as $fk=>$fTable) {
                $as = $fTable.'_'.$fk;
                $join[$as] = ' INNER JOIN `'.$fTable.'` `'.$as.'` ON (`'.$register.'`.`'.$fk.'` = `'.$as.'`.`_pk`)';
                $select[$as] = '`'.$as.'`.`_offset` AS `'.$fk.'`';
            }
        }
        $sql = 'SELECT '.implode(', ', $select).' FROM `'.$register.'`'.implode(',', $join).';';
        $data = $this->pdo->query($sql);
        $this->pdo->beginTransaction();
        $transactionIterator = 0;
        while($row = $data->fetch()) {
            $rowId = $row['_pk'];
            unset($row['_pk']);
            unset($row['_offset']);
            foreach ($empty as $field=>$null) {
                unset($row['_len_'.$field]);
            }
            $check = 0;
            foreach ($row as $cell=>$cellValue) {
                if (!empty($cellValue)) $check = 1;
            }
            $bin = Pack::pack($format, $row);
            $binLen = strlen($bin);
            if ($check) {
                if ($binLen > $this->meta['maxItemLen']) {
                    $this->meta['maxItemLen'] = $binLen;
                }
                $offset += $binLen;
                fwrite($file,$bin);
            }
            $sql = 'UPDATE `'.$register.'` SET `_offset` =\''.($check?$offset:0).'\' WHERE `_pk` = \''.$rowId.'\';';
            $this->pdo->exec($sql);
            $sql = 'UPDATE `_ips` SET `offset` =\''.($check?$offset:0).'\' WHERE `parameter` = \''
                .$register.'\' AND `value`=\''.$rowId.'\';';
            $this->pdo->exec($sql);

            $transactionIterator += 2;
            if ($transactionIterator > 100000) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
                $transactionIterator = 0;
            }
        }
        $this->meta['registers'][$register]['n'] = $offset;
        $this->pdo->commit();
        fclose($file);
    }

    /**
     * Compile network from temporary db
     */
    protected function compileNetwork()
    {
        $sql = 'INSERT INTO `_ips` (`ip`,`action`,`parameter`,`value`) VALUES (:ip,:action,:parameter,:value);';
        $insertIps = $this->pdo->prepare($sql);
        $insertIps->execute(array(
            ':ip' => 0,
            ':action' => 'add',
            ':parameter' => NULL,
            ':value' => NULL,
        ));
        $ip = -1;
        $fields = $this->meta['networks']['fields'];
        $values = array();
        $format = array();
        foreach ($fields as $register=>$null) {
            $values[$register] = array();
            $format[$register] = Pack::getOptimalFormat(
                0,
                $this->meta['registers'][$register]['n'],
                $register
            );
        }


        $this->meta['networks']['format'] = implode('/', $format);

        $pack = $this->meta['networks']['format'];
        $binaryPrevData = Pack::pack($pack, $fields);
        $this->meta['networks']['s'] += strlen($binaryPrevData);
        $offset = 0;
        $this->meta['index'][0] = 0;
        $file = fopen($this->prefix.'.networks.dat','w');
        $ipInfo = $this->pdo->query('SELECT * FROM `_ips` ORDER BY `ip` ASC, `action` DESC, `id` ASC;');
        while ($row = $ipInfo->fetch()) {
            if ($row['ip'] !== $ip) {
                foreach ($values as $param=>$v) {
                    if (!empty($param)) $fields[$param] = array_pop($v);
                }
                $binaryData = Pack::pack($pack, $fields);
                if ($binaryData !== $binaryPrevData || empty($ip)) {
                    fwrite($file, pack('N', $ip) . $binaryData);
                    $octet = (int)long2ip($ip);
                    if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                    $offset++;
                    $binaryPrevData = $binaryData;
                }
                $ip = $row['ip'];
            }
            if ($row['action'] == 'remove') {
                $key = array_search($row['offset'],$values[$row['parameter']]);
                if ($key !== false) {
                    unset($values[$row['parameter']][$key]);
                }
            } else {
                $values[$row['parameter']][] = $row['offset'];
            }
        }
        if ($ip < ip2long('255.255.255.255')) {
            foreach ($values as $param => $v) {
                if (!empty($param)) $fields[$param] = array_pop($v);
            }
            $binaryData = Pack::pack($pack, $fields);
            if ($binaryData !== $binaryPrevData) {
                $octet = (int)long2ip($ip);
                if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                $offset++;
                fwrite($file, pack('N', $ip) . $binaryData);
            }
        }
        $this->meta['networks']['n'] = $offset;
        for($i=1;$i<=255;$i++) {
            if (!isset($this->meta['index'][$i])) $this->meta['index'][$i] = $this->meta['index'][$i-1];
        }
        ksort($this->meta['index']);
        fclose($file);
        unset($ip);
    }

    /**
     * Compile header.
     *
     * @void
     */
    protected function compileHeader()
    {
        /*
         * Ipstack format version.
         */
        $header = pack('C', self::FORMAT_VERSION);

        /*
         * Maximal length of register item
         */
        $header .= pack('I', $this->meta['maxItemLen']);

        /*
         * Registers count.
         */
        $header .= pack('C', count($this->meta['registers']));

        $rnmLen = 1;
        $pckLen = strlen($this->meta['networks']['format']);
        $numMax = $this->meta['networks']['n'];
        $registerOffset = filesize($this->prefix.'.networks.dat');;
        foreach ($this->meta['registers'] as $registerName => $register) {
            $this->meta['registers'][$registerName]['s'] = $registerOffset;
            $file = $this->prefix.'.reg.'.$registerName.'.dat';
            $registerOffset += filesize($file);
            if (strlen($registerName) > $rnmLen) $rnmLen = strlen($registerName);
            if (strlen($register['format']) > $pckLen) $pckLen = strlen($register['format']);
            if ($register['n'] > $numMax) $numMax = $register['n'];
        }
        $rnm = 'A'.$rnmLen.'name';
        $pck = 'A'.$pckLen.'format';
        $sizeMax = ($this->meta['networks']['s'] > $registerOffset)?$this->meta['networks']['s']:$registerOffset;
        $size = Pack::getOptimalFormat(0, $sizeMax, 's');
        $itm = Pack::getOptimalFormat(0, $numMax, 'n');
        $format = $rnm.'/'.$pck.'/'.$size.'/'.$itm;

        /*
         * Size of registers definition unpack format.
         */
        $header .= pack('S',strlen($format));

        /*
         * Size of registers definition row.
         */
        $empty = array(
            'name' => '',
            'format' => '',
            's' => 0,
            'n' => 0,
        );
        $header .= pack('S',strlen(Pack::pack($format, $empty)));

        /*
         * Relations count.
         */
        $header .= pack('C', $this->meta['relations']['items']);

        /*
         * Size of relations definition unpack format.
         */
        $lenRelationsFormat = strlen($this->meta['relations']['format']);
        $header .= pack('C', $lenRelationsFormat);

        /*
         * Size of relation definition row.
         */
        $header .= pack('S', $this->meta['relations']['len']);

        /*
         * Relation unpack format (parent, column, child).
         */
        $header .= $this->meta['relations']['format'];

        /*
         * Registers metadata unpack format.
         */
        $header .= pack('A*', $format);

        /*
         * Relations.
         */
        if (!empty($this->meta['relations']['data'])) {
            foreach ($this->meta['relations']['data'] as $relation) {
                $header .= Pack::pack(
                    $this->meta['relations']['format'],
                    $relation
                );
            }
        }

        /**
         * Registers metadata.
         */
        foreach ($this->meta['registers'] as $registerName => $register) {
            $header .= Pack::pack(
                $format,
                array(
                    'name' => $registerName,
                    'format' => $register['format'],
                    's' => $register['s'],
                    'n' => $register['n']
                )
            );
        }

        /*
         * Networks metadata.
         */
        $header .= Pack::pack(
            $format,
            array(
                'name' => 'n',
                'format' => $this->meta['networks']['format'],
                's' => $this->meta['networks']['s'],
                'n' => $this->meta['networks']['n']
            )
        );

        /*
         * Index of first octets.
         */
        $packParams = array_values($this->meta['index']);
        array_unshift($packParams, 'I*');
        $header .= call_user_func_array('pack',$packParams);

        /*
         * Control word and header size.
         */
        $headerLength = strlen($header);
        $header = 'ISD'.pack('S', $headerLength).$header;

        $file = fopen($this->prefix.'.header', 'w');
        fwrite($file, $header);
        fclose($file);
    }

    /**
     * Make file.
     *
     * @param string $fileName
     * @void
     */
    protected function makeFile($fileName)
    {
        /*
         * Create binary database.
         */
        $tmp = $this->prefix.'.database.dat';
        $database = fopen($tmp,'w');

        /*
         * Write header to database.
         */
        $file = $this->prefix.'.header';
        $stream = fopen($file, 'rb');
        stream_copy_to_stream($stream, $database);
        fclose($stream);
        if (is_writable($file)) unlink($file);

        /*
         * Write networks to database.
         */
        $file = $this->prefix.'.networks.dat';
        $stream = fopen($file, 'rb');
        stream_copy_to_stream($stream, $database);
        fclose($stream);
        if (is_writable($file)) unlink($file);

        foreach ($this->meta['registers'] as $register=>$data) {
            $file = $this->prefix.'.reg.'.$register.'.dat';
            $stream = fopen($file, 'rb');
            stream_copy_to_stream($stream, $database);
            fclose($stream);
            if (is_writable($file)) unlink($file);
        }
        $data = array(
            'time' => empty($this->time)?time():$this->time,
            'author' => $this->author,
            'license' => $this->license,
        );
        fwrite($database,pack::pack('Itime/~author/A*license', $data));
        fclose($database);
        rename($tmp, $fileName);
    }
}
