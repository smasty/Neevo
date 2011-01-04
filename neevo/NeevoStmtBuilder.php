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
 * Building SQL string from NeevoResult instance.
 * @package Neevo
 */
class NeevoStmtBuilder extends NeevoAbstract{

  /** @var Neevo */
  protected $neevo;

  /**
   * Instantiate StatementBuilder
   * @param Neevo $neevo
   */
  public function  __construct(Neevo $neevo = null){
    $this->neevo = $neevo;
  }


  /**
   * Builds statement from NeevoResult instance
   * @param NeevoStmtBase $statement
   * @return string the statement
   */
  public function build(NeevoStmtBase $statement){

    $where = '';
    $order = '';
    $group = '';
    $limit = '';
    $q = '';

    $table = $statement->getTable();

    // JOIN
    if($statement instanceof NeevoResult && $statement->getJoin()){
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
      $q .= 'UPDATE ' .$table.$update_data.$where.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_DELETE)
      $q .= 'DELETE FROM ' .$table.$where.$order.$limit;

    return $q.';';
  }


  /**
   * Builds JOIN part for SELECT statement
   * @param NeevoResult $statement
   * @throws NeevoException
   * @return string
   */
  protected function buildJoin(NeevoResult $statement){
    $join = $statement->getJoin();
    $prefix = $this->neevo->connection->prefix();
    $join['expr'] = preg_replace('~(\w+)\.(\w+)~i', "$1.$prefix$2", $join['expr']);
    $type = strtoupper(substr($join['type'], 5));

    if($type !== ''){
      $type .= ' ';
    }
    if($join['operator'] === 'ON'){
      $expr = " ON $join[expr]";
    }
    elseif($join['operator'] === 'USING'){
      $expr = " USING($join[expr])";
    }
    else{
      throw new NeevoException('JOIN operator not specified.');
    }
    
    return $type.'JOIN '.$join['table'].$expr;
  }


  /**
   * Builds WHERE condition statement
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildWhere(NeevoStmtBase $statement){
    $conds = $statement->getConditions();

    // Unset glue on last condition
    unset($conds[count($conds)-1]['glue']);

    $conditions = array();
    foreach($conds as $cond){

      // Conditions with placeholders
      if($cond['simple'] === false){
        $expr = str_replace('::', $this->neevo->connection->prefix(),$cond['expr']);
        $s = '('.str_replace($cond['placeholders'], $this->_escapeArray($cond['values']), $expr).')';
        if(isset($cond['glue'])){
          $s .= ' '.$cond['glue'];
        }
        $conditions[] = $s;
        continue;
      }

      // Simple conditions
      $field = $this->buildColName($cond['field']);
      $operator = '';
      $value = $cond['value'];
      if($value === null){ // field IS NULL
        $operator = ' IS';
        $value = ' NULL';
      } elseif($value === true){  // field
        $value = '';
      } elseif($value === false){ // NOT field
        $value = $field;
        $field = 'NOT ';
      } elseif(is_array($value)){ // field IN (array)
        $operator = ' IN';
        $value = '(' . join(', ', $this->_escapeArray($value)) . ')';
      } elseif($value instanceof NeevoLiteral){ // field = SQL literal
        $operator = ' = ';
        $value = $value->val;
      } elseif($value instanceof DateTime){ // field = DateTime
        $operator = ' = ';
        $value = $this->neevo->driver()->escape($value, Neevo::DATETIME);
      } else{ // field = value
        $operator = ' = ';
        $value = (is_numeric($value) && !is_string($value))
          ? $value : $this->_escapeString($value);
      }
      $s = "($field$operator$value)";
      if(isset($cond['glue'])){
        $s .= ' '.$cond['glue'];
      }

      $conditions[] = $s;

    }

    return ' WHERE ' . join(' ', $conditions);
  }


  /**
   * Builds data part for INSERT statements ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildInsertData(NeevoStmtBase $statement){
    foreach($this->_escapeArray($statement->getValues()) as $col => $value){
      $cols[] = $this->buildColName($col);
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Builds data part for UPDATE statements ([UPDATE ...] SET ...)
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildUpdateData(NeevoStmtBase $statement){
    foreach($this->_escapeArray($statement->getValues()) as $col => $value){
      $update[] = $this->buildColName($col) . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }


  /**
   * Builds ORDER BY statement
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildOrdering(NeevoStmtBase $statement){
    return ' ORDER BY ' . join(', ', $statement->getOrdering());
  }


  /**
   * Builds GROUP BY statement
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildGrouping(NeevoStmtBase $statement){
    $having = $statement->getHaving() ? ' HAVING ' . (string) $statement->getHaving() : '';
    return ' GROUP BY ' . $statement->getGrouping() . $having;
  }


  /**
   * Builds columns part for SELECT statements
   * @param NeevoStmtBase $statement
   * @return string
   */
  protected function buildSelectCols(NeevoStmtBase $statement){
    foreach ($statement->getColumns() as $col) { // For each col
      $cols[] = $this->buildColName($col);
    }
    return join(', ', $cols);
  }
  
  
  /*  ******  Internal methods  ******  */


  protected function buildColName($col){
    if($col instanceof NeevoLiteral){
      return $col->value;
    }
    $col = trim(str_replace('::', '', $col));
    $col = preg_replace('#(\S+)\s+(as)\s+(\S+)#i', '$1 AS $3',  $col);

    if(preg_match('#[^.]+\.[^.]+#', $col)){
      return $this->neevo->connection->prefix() . $col;
    }
    return $col;
  }


  /**
   * Escapes whole array for use in SQL
   * @param array $array
   * @return array
   * @internal
   */
  protected function _escapeArray(array $array){
    foreach($array as &$value){
      if(is_null($value)){
        $value = 'NULL';
      }
      elseif(is_bool($value)){
        $value = $this->neevo->driver()->escape($value, Neevo::BOOL);
      }
      elseif(is_numeric($value)){
        if(is_int($value)){
          $value = intval($value);
        }
        elseif(is_float($value)){
          $value = floatval($value);
        }
        else{
          $value = intval($value);
        }
      }
      elseif(is_string($value)){
        $value = $this->_escapeString($value);
      }
      elseif($value instanceof DateTime){
        $value = $this->neevo->driver()->escape($value, Neevo::DATETIME);
      }
      elseif($value instanceof NeevoLiteral){
        $value = $value->value;
      }
      elseif(is_array($value)){
        $value = 'IN(' . join(', ', $this->_escapeArray($value)) . ')';
      }
      else{
        $value = $this->_escapeString((string) $value);
      }
    }
    return $array;
  }

  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   * @internal
   * @todo
   */
  protected function _escapeString($string){
    if(get_magic_quotes_gpc()){
      $string = stripslashes($string);
    }
    return $this->neevo->driver()->escape($string, Neevo::TEXT);
  }
  
}
