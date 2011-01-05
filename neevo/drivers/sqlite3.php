<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */

/**
 * Neevo SQLite 3 driver (PHP extension 'sqlite3')
 *
 * Driver configuration:
 *  - database (or file)
 *  - charset => Character encoding to set (defaults to utf-8)
 *  - dbcharset => Database character encoding (will be converted to 'charset')
 * 
 *  - update_limit (bool) => Set TRUE if SQLite driver was compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT
 *  - resource (instance of SQLite3) => Existing SQLite 3 link
 *  - lazy, table_prefix => see NeevoConnection
 *
 * Since SQLite 3 only allows unbuffered queries, number of result rows and seeking
 * is not supported for this driver.
 * 
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverSQLite3 extends NeevoStmtBuilder implements INeevoDriver{

  private $dbCharset, $charset, $update_limit, $resource, $_joinTbl, $affectedRows;

  /**
   * Check for required PHP extension.
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo = null){
    if(!extension_loaded("sqlite3")){
      throw new NeevoException("PHP extension 'sqlite3' not loaded.");
    }
    $this->neevo = $neevo;
  }


  /**
   * Create connection to database.
   * @param array $config Configuration options
   * @throws NeevoException
   * @return void
   */
  public function connect(array $config){
    NeevoConnection::alias($config, 'database', 'file');

    $defaults = array(
      'resource' => null,
      'update_limit' => false,
      'charset' => 'UTF-8',
      'dbcharset' => 'UTF-8'
    );

    $config += $defaults;

    // Connect
    if($config['resource'] instanceof SQLite3){
      $connection = $config['resource'];
    }
    else{
      try{
        $connection = new SQLite3($config['database']);
      } catch(Exception $e){
          throw new NeevoException($e->getMessage(), $e->getCode(), $e);
      }
    }

    if(!($connection instanceof SQLite3)){
      throw new NeevoException("Opening database file '$config[database]' failed.");
    }
    
    $this->resource = $connection;
    $this->update_limit = (bool) $config['update_limit'];

    // Set charset
    $this->dbCharset = $config['dbcharset'];
    $this->charset = $config['charset'];
    if(strcasecmp($this->dbCharset, $this->charset) === 0){
      $this->dbCharset = $this->charset = null;
    }
  }


  /**
   * Close the connection.
   * @return void
   */
  public function close(){
    $this->resource->close();
  }


  /**
   * Free memory used by given result.
   *
   * NeevoResult automatically NULLs the resource, so this is not necessary.
   * @param SQLite3Result $resultSet
   * @return bool
   */
  public function free($resultSet){
    return true;
  }


  /**
   * Execute given SQL statement.
   * @param string $queryString Query-string.
   * @throws NeevoException
   * @return SQLite3Result|bool
   */
  public function query($queryString){

    $this->affectedRows = false;
    if($this->dbCharset !== null){
      $queryString = iconv($this->charset, $this->dbCharset . '//IGNORE', $queryString);
    }

    $result = $this->resource->query($queryString);

    if($result === false){
      throw new NeevoException($this->resource->lastErrorMsg(), $this->resource->lastErrorCode());
    }

    $this->affectedRows = $this->resource->changes();
    return $result;
  }


  /**
   * Begin a transaction if supported.
   * @param string $savepoint
   * @return void
   */
  public function begin($savepoint = null){
    $this->query($savepoint ? "SAVEPOINT $savepoint" : 'BEGIN');
  }


  /**
   * Commit statements in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function commit($savepoint = null){
    $this->query($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
  }


  /**
   * Rollback changes in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function rollback($savepoint = null){
    $this->query($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
  }


  /**
   * Fetch row from given result set as an associative array.
   * @param SQLite3Result $resultSet Result set
   * @return array
   */
  public function fetch($resultSet){
    $row = $resultSet->fetchArray(SQLITE3_ASSOC);
    $charset = $this->charset === null ? null : $this->charset.'//TRANSLIT';

    if($row){
      $fields = array();
      foreach($row as $key=>$val){
        if($charset !== null && is_string($val)){
          $val = iconv($this->dbcharset, $charset, $val);
        }
        $fields[str_replace(array('[', ']'), '', $key)] = $val;
      }
      return $fields;
    }
    return $row;
  }


  /**
   * Move internal result pointer.
   *
   * Not supported because of unbuffered queries.
   * @param SQLite3Result $resultSet
   * @param int $offset
   * @return bool
   * @throws NotSupportedException
   */
  public function seek($resultSet, $offset){
    throw new NotSupportedException('Cannot seek on unbuffered result.');
  }


  /**
   * Get the ID generated in the INSERT statement.
   * @return int
   */
  public function insertId(){
    return $this->resource->lastInsertRowID();
  }


  /**
   * Randomize result order.
   * @param NeevoStmtBase $statement
   */
  public function rand(NeevoStmtBase $statement){
    $statement->order('RANDOM()');
  }


  /**
   * Get the number of rows in the given result set.
   *
   * Not supported because of unbuffered queries.
   * @param SQLite3Result $resultSet
   * @return int|FALSE
   * @throws NotSupportedException
   */
  public function rows($resultSet){
    throw new NotSupportedException('Cannot count rows on unbuffered result.');
  }


  /**
   * Get the umber of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return $this->affectedRows;
  }


  /**
   * Escape given value.
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @throws InvalidArgumentException
   * @return mixed
   */
  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
        return "'". $this->resource->escapeString($value) ."'";
      
      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
        
      default:
        throw new InvalidArgumentException('Unsupported data type');
        break;
    }
  }


  /**
   * Get the PRIMARY KEY column for given table.
   * @param $table string
   * @return string
   */
  public function getPrimaryKey($table){
    $key = '';
    $pos = strpos($table, '.');
    if($pos !== false){
      $table = substr($table, $pos + 1);
    }
    $q = $this->query("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
    $r = $this->fetch($q);
    if($r === false){
      return '';
    }

    $sql = $r['sql'];
    $sql = explode("\n", $sql);
    foreach($sql as $field){
      $field = trim($field);
      if(stripos($field, 'PRIMARY KEY') !== false && $key === ''){
        $key = preg_replace('~^"(\w+)".*$~i', '$1', $field);
      }
    }
    return $key;
  }


  /**
   * Build the SQL statement from the instance.
   * @param NeevoStmtBase $statement
   * @return string The SQL statement
   */
  public function build(NeevoStmtBase $statement){

    $where = '';
    $order = '';
    $group = '';
    $limit = '';
    $q = '';

    $table = $statement->getTable();

    // JOIN - Workaround for RIGHT JOIN
    if($statement instanceof NeevoResult && $statement->getJoin()){
      $j = $statement->getJoin();
      if($j['type'] === Neevo::JOIN_RIGHT){
        $this->_joinTbl = $table;
        $table = $j['table'];
      }
      $table = $table .' '. $this->buildJoin($statement);
    }

    // WHERE
    if($statement->getConditions()){
      $where = $this->buildWhere($statement);
    }

    // ORDER BY
    if($statement->getOrdering()){
      $order = $this->buildOrdering($statement);
    }

    // GROUP BY
    if($statement instanceof NeevoResult && $statement->getGrouping()){
      $group = $this->buildGrouping($statement);
    }

    // LIMIT, OFFSET
    if($statement->getLimit()){
      $limit = ' LIMIT ' .$statement->getLimit();
    }
    if($statement->getOffset()){
      $limit .= ' OFFSET ' .$statement->getOffset();
    }

    if($statement->getType() == Neevo::STMT_SELECT){
      $cols = $this->buildSelectCols($statement);
      $q .= "SELECT $cols FROM " .$table.$where.$group.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
      $insert_data = $this->buildInsertData($statement);
      $q .= 'INSERT INTO ' .$table.$insert_data;
    }
    elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
      $update_data = $this->buildUpdateData($statement);
      $q .= 'UPDATE ' .$table.$update_data.$where;
      if($this->update_limit === true){
        $q .= $order.$limit;
      }
    }
    elseif($statement->getType() == Neevo::STMT_DELETE){
      $q .= 'DELETE FROM ' .$table.$where;
      if($this->update_limit === true){
        $q .= $order.$limit;
      }
    }

    return $q.';';
  }


  /**
   * Build JOIN part for SELECT statement.
   * @param NeevoResult $statement
   * @throws NeevoException
   * @return string
   */
  protected function buildJoin(NeevoResult $statement){
    $join = $statement->getJoin();
    if(isset($this->_joinTbl) && $join['type'] === Neevo::JOIN_RIGHT){
      $join['table'] = $this->_joinTbl;
      $join['type'] = Neevo::JOIN_LEFT;
      unset($this->_joinTbl);
    }
    $prefix = $this->neevo->connection->prefix();
    $join['expr'] = preg_replace('~(\w+)\.(\w+)~i', "$1.$prefix$2", $join['expr']);
    $type = strtoupper(substr($join['type'], 5));

    if($type !== ''){
      $type .= ' ';
    }
    if($join['operator'] === 'ON'){
      $expr = ' ON '.$join['expr'];
    }
    elseif($join['operator'] === 'USING'){
      $expr = " USING($join[expr])";
    }
    else{
      throw new NeevoException('JOIN operator not specified.');
    }

    return $type.'JOIN '.$join['table'].$expr;
  }

}