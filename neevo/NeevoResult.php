<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license MIT license
 * @link     http://neevo.smasty.net/
 *
 */


/**
 * Represents a result. Can be iterated, counted and provides fluent interface.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoResult extends NeevoStmtBase implements IteratorAggregate, Countable {


    /** @var resource */
    protected $resultSet;

    /** @var int */
    protected $numRows;

    /** @var string */
    protected $grouping;

    /** @var string */
    protected $having = null;

    /** @var array */
    protected $columns = array();

    /** @var array */
    private $joins;

    /** @var string */
    private $rowClass = 'NeevoRow';

    /** @var array */
    private $columnTypes = array();

    /** @var bool */
    private $detectTypes;

    /** @var array */
    private $referenced;


    /**
     * Create SELECT statement.
     * @param NeevoConnection $connection
     * @param string|array $columns
     * @param string $table
     * @return void
     * @throws InvalidArgumentException
     */
    public function __construct(NeevoConnection $connection, $columns = null, $table = null){
        parent::__construct($connection);

        if($columns == null && $table == null){
            throw new InvalidArgumentException('Select table missing.');
        }
        if(func_get_arg(2) == null){
            $columns = '*';
            $table = func_get_arg(1);
        }
        $this->reinit();
        $this->type = Neevo::STMT_SELECT;
        $this->columns = is_string($columns) ? explode(',', $columns) : $columns;
        $this->tableName = $table;
        $this->detectTypes = (bool) $this->getConfig('detectTypes');

        $this->setRowClass($this->getConfig('rowClass'));
    }


    /**
     * Define grouping rules.
     * @param string $rule
     * @param string $having Optional
     * @return NeevoResult fluent interface
     */
    public function group($rule, $having = null){
        if($this->checkCond()){
            return $this;
        }
        $this->reinit();
        $this->grouping = array($rule, $having);
        return $this;
    }


    /**
     * @deprecated
     * @internal
     */
    public function groupBy(){
        return call_user_func_array(array($this, 'group'), func_get_args());
    }


    /**
     * Perform JOIN on tables.
     * @param string $table
     * @param string $condition
     * @return NeevoResult fluent interface
     */
    public function join($table, $condition){
        if($this->checkCond()){
            return $this;
        }
        $this->reinit();
        $type = (func_num_args() > 2) ? func_get_arg(2) : '';

        $this->joins[] = array($table, $condition, $type);

        return $this;
    }


    /**
     * Perform LEFT JOIN on tables.
     * @param string $table
     * @param string $condition
     * @return NeevoResult fluent interface
     */
    public function leftJoin($table, $condition){
        return $this->join($table, $condition, Neevo::JOIN_LEFT);
    }


    /**
     * Perform INNER JOIN on tables.
     * @param string $table
     * @param string $condition
     * @return NeevoResult fluent interface
     */
    public function innerJoin($table, $condition){
        return $this->join($table, $condition, Neevo::JOIN_INNER);
    }


    /**
     * Fetch the row on current position.
     * @return NeevoRow|FALSE
     */
    public function fetch(){
        $this->performed || $this->run();

        $row = $this->driver()->fetch($this->resultSet);
        if(!is_array($row)){
            return false;
        }

        // Type converting
        if($this->detectTypes){
            $this->detectTypes();
        }
        if(!empty($this->columnTypes)){
            foreach($this->columnTypes as $col => $type){
                if(isset($row[$col])){
                    $row[$col] = $this->convertType($row[$col], $type);
                }
            }
        }
        return new $this->rowClass($row, $this);
    }


    /**
     * @deprecated
     * @internal
     */
    public function fetchRow(){
        return $this->fetch();
    }


    /**
     * Fetch all rows in result set.
     * @param int $limit Limit number of returned rows
     * @param int $offset Seek to offset (fails on unbuffered results)
     * @return array
     * @throws NeevoException
     */
    public function fetchAll($limit = null, $offset = null){
        $limit = ($limit === null) ? -1 : (int) $limit;
        if($offset !== null){
            $this->seek((int) $offset);
        }

        $row = $this->fetch();
        if(!$row){
            return array();
        }

        $rows = array();
        do{
            if($limit === 0){
                break;
            }
            $rows[] = $row;
            $limit--;
        } while($row = $this->fetch());

        return $rows;
    }


    /**
     * Fetch the first value from current row.
     * @return mixed
     */
    public function fetchSingle(){
        $this->performed || $this->run();
        $row = $this->driver()->fetch($this->resultSet);

        if(!$row) return false;
        $value = reset($row);

        // Type converting
        if($this->detectTypes){
            $this->detectTypes();
        }
        if(!empty($this->columnTypes)){
            $key = key($row);
            if(isset($this->columnTypes[$key])){
                $value = $this->convertType($value, $this->columnTypes[$key]);
            }
        }

        return $value;
    }


    /**
     * Fetch rows as $key=>$value pairs.
     * @param string $key Key column
     * @param string $value Value column. NULL for all specified columns.
     * @return array
     */
    public function fetchPairs($key, $value = null){
        $clone = clone $this;

        // If executed w/o needed cols, force exec w/ them.
        if(!in_array('*', $clone->columns)){
            if(!in_array($key, $clone->columns)){
                $clone->columns[] = $key;
            }
            if($value !== null && !in_array($value, $clone->columns)){
                $clone->columns[] = $value;
            }
        }

        $rows = array();
        while($row = $clone->fetch()){
            if(!$row) return array();
            $rows[$row[$key]] = $value === null ? $row : $row->$value;
        }

        return $rows;
    }


    /**
     * Free result set resource.
     * @return void
     */
    private function free(){
        try{
            $this->driver()->free($this->resultSet);
        } catch(NeevoImplemenationException $e){}
        $this->resultSet = null;
    }


    /**
     * Move internal result pointer.
     * @param int $offset
     * @return bool
     * @throws NeevoException
     */
    public function seek($offset){
        $this->performed || $this->run();
        $seek = $this->driver()->seek($this->resultSet, $offset);
        if($seek){
            return $seek;
        }
        throw new NeevoException("Cannot seek to offset $offset.");
    }


    /**
     * Get the number of rows in the result set.
     * @return int
     * @throws NeevoDriverException
     */
    public function rows(){
        $this->performed || $this->run();

        $this->numRows = (int) $this->driver()->rows($this->resultSet);
        return $this->numRows;
    }


    /**
     * Count number of rows.
     * @param string $column
     * @return int
     * @throws NeevoDriverException
     */
    public function count($column = null){
        if($column === null)
            return $this->rows();
        return $this->aggregation("COUNT(:$column)");
    }


    /**
     * Execute aggregation function.
     * @param string $function
     * @return mixed
     */
    public function aggregation($function){
        $clone = clone $this;
        $clone->columns = (array) $function;
        return $clone->fetchSingle();
    }


    /**
     * Get the sum of column a values.
     * @param string $column
     * @return mixed
     */
    public function sum($column){
        return $this->aggregation("SUM(:$column)");
    }


    /**
     * Get the minimum value of column.
     * @param string $column
     * @return mixed
     */
    public function min($column){
        return $this->aggregation("MIN(:$column)");
    }


    /**
     * Get the maximum value of column.
     * @param string $column
     * @return mixed
     */
    public function max($column){
        return $this->aggregation("MAX(:$column)");
    }


    /**
     * Set class to use as a row class.
     * @param string $className
     * @return NeevoResult fluent interface
     * @throws NeevoException
     */
    public function setRowClass($className){
        if(!class_exists($className)){
            throw new NeevoException("Cannot set row class '$className'.");
        }
        $this->rowClass = $className;
        return $this;
    }


    /**
     * Set column type.
     * @param string $column
     * @param string $type
     * @return NeevoResult fluent interface
     */
    public function setType($column, $type){
        $this->columnTypes[$column] = $type;
        return $this;
    }


    /**
     * Set multiple column types at once.
     * @param array $types
     * @return NeevoResult fluent interface
     */
    public function setTypes(array $types){
        foreach($types as $column => $type){
            $this->setType($column, $type);
        }
        return $this;
    }


    /**
     * Detect column types.
     * @return NeevoResult fluent interface
     */
    public function detectTypes(){
        $table = $this->getTable();
        $this->performed || $this->run();

        // Try fetch from cache
        $types = (array) $this->connection->cache()->fetch($table . '_detectedTypes');

        if(empty($types)){
            try{
                $types = $this->driver()->getColumnTypes($this->resultSet, $table);
            } catch(NeevoException $e){}
        }

        foreach((array) $types as $col => $type){
            $this->columnTypes[$col] = $this->resolveType($type);
        }

        $this->connection->cache()->store($table . '_detectedTypes', $this->columnTypes);
        return $this;
    }


    /**
     * Resolve vendor column type.
     * @param string $type
     * @return string
     */
    private function resolveType($type){
        static $patterns = array(
            'bool|bit' => Neevo::BOOL,
            'bin|blob|bytea' => Neevo::BINARY,
            'string|char|text|bigint|longlong' => Neevo::TEXT,
            'int|long|byte|serial|counter' => Neevo::INT,
            'float|real|double|numeric|number|decimal|money|currency' => Neevo::FLOAT,
            'time|date|year' => Neevo::DATETIME
        );

        foreach($patterns as $vendor => $universal){
            if(preg_match("~$vendor~i", $type)){
                return $universal;
            }
        }
        return Neevo::TEXT;
    }


    /**
     * Convert value to a specified type.
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function convertType($value, $type){
        $dateFormat = $this->getConfig('formatDateTime');
        if($value === null || $value === false){
            return null;
        }
        switch($type){
            case Neevo::TEXT:
                return (string) $value;

            case Neevo::INT:
                return (int) $value;

            case Neevo::FLOAT:
                return (float) $value;

            case Neevo::BOOL:
                return ((bool) $value) && $value !== 'f' && $value !== 'F';

            case Neevo::BINARY:
                return $this->driver()->unescape($value, $type);

            case Neevo::DATETIME:
                if((int) $value === 0){
                    return null;
                }
                elseif(!$dateFormat){
                    return new DateTime(is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value);
                }
                elseif($dateFormat == 'U'){
                    return is_numeric($value) ? (int) $value : strtotime($value);
                }
                elseif(is_numeric($value)){
                    return date($dateFormat, $value);
                }
                else{
                    $d = new DateTime($value);
                    return $d->format($value);
                }

            default:
                return $value;
        }
    }


    /**
     * Get referenced row from given table.
     * @param string $table
     * @param NeevoResult $row
     * @param string $foreign
     * @return NeevoRow|null
     */
    public function getReferencedRow($table, & $row, $foreign = null){
        $foreign = $foreign === null ? $this->getForeignKey($table) : $foreign;
        $rowID = $row->$foreign;
        $referenced = & $this->referenced[$table];

        if(empty($referenced)){
            $clone = clone $this;
            $clone->columns = array($foreign);
            $keys = $clone->fetchPairs($foreign, $foreign);
            $result = new NeevoResult($this->connection, '*', $table);
            $primary = $result->getPrimaryKey();
            $referenced = $result->where($primary, $keys)->fetchPairs($primary);
        }
        if(isset($referenced[$rowID])){
            return $referenced[$rowID];
        }
        return null;
    }


    /*    ************    Getters    ************    */


    /**
     * Get the result iterator.
     * @return NeevoResultIterator
     */
    public function    getIterator(){
        return new NeevoResultIterator($this);
    }


    public function getGrouping(){
        return $this->grouping;
    }


    public function getColumns(){
        return $this->columns;
    }


    public function getJoins(){
        if(!empty($this->joins)){
            return $this->joins;
        }
        return false;
    }


    /*    ************    Internal methods    ************    */


    /** @internal */
    public function reinit(){
        parent::reinit();
        $this->resultSet = null;
        $this->numRows = null;
    }

    
    public function    __destruct(){
        $this->free();
    }


}
