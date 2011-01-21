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
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */

/**
 * Parse NeevoStmt to SQL command
 * @author Martin Srank
 * @package Neevo
 */
class NeevoStmtParser {

  /** @var NeevoStmtBase */
  protected $statement;

  /**
   * Parse the instance.
   * @param NeevoStmtBase $statement
   * @return string The SQL statement
   */
  public function parse(NeevoStmtBase $statement){

    $this->statement = $statement;

    $where = '';
    $order = '';
    $group = '';
    $limit = '';
    $q = '';

    $table = $statement->getTable();

    // JOIN
    if($statement instanceof NeevoResult && $statement->getJoin()){
      $table = $table .' '. $this->parseJoin();
    }

    // WHERE
    if($statement->getConditions()){
      $where = $this->parseWhere();
    }

    // ORDER BY
    if($statement->getOrdering()){
      $order = $this->parseOrdering();
    }

    // GROUP BY
    if($statement instanceof NeevoResult && $statement->getGrouping()){
      $group = $this->parseGrouping();
    }

    // LIMIT, OFFSET
    if($statement->getLimit()){
      $limit = ' LIMIT ' .$statement->getLimit();
    }
    if($statement->getOffset()){
      $limit .= ' OFFSET ' .$statement->getOffset();
    }

    if($statement->getType() == Neevo::STMT_SELECT){
      $cols = $this->parseSelectCols();
      $q .= "SELECT $cols FROM " .$table.$where.$group.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
      $insert_data = $this->parseInsertData();
      $q .= 'INSERT INTO ' .$table.$insert_data;
    }
    elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
      $update_data = $this->parseUpdateData();
      $q .= 'UPDATE ' .$table.$update_data.$where.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_DELETE)
      $q .= 'DELETE FROM ' .$table.$where.$order.$limit;

    return $q.';';
  }

  /**
   * Parse JOIN part for SELECT statement.
   * @throws NeevoException
   * @return string
   */
  protected function parseJoin(){
    $join = $this->statement->getJoin();
    $prefix = $this->statement->connection()->prefix();
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
   * Parse WHERE condition for statement.
   * @return string
   */
  protected function parseWhere(){
    $conds = $this->statement->getConditions();

    // Unset glue on last condition
    unset($conds[count($conds)-1]['glue']);

    $conditions = array();
    foreach($conds as $cond){

      // Conditions with placeholders
      if($cond['simple'] === false){
        $expr = str_replace('::', $this->statement->connection()->prefix(),$cond['expr']);
        $s = '('.str_replace($cond['placeholders'], $this->_escapeArray($cond['values']), $expr).')';
        if(isset($cond['glue'])){
          $s .= ' '.$cond['glue'];
        }
        $conditions[] = $s;
        continue;
      }

      // Simple conditions
      $field = $this->parseColName($cond['field']);
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
        $value = $value->value;
      } elseif($value instanceof DateTime){ // field = DateTime
        $operator = ' = ';
        $value = $this->statement->driver()->escape($value, Neevo::DATETIME);
      } else{ // field = value
        $operator = ' = ';
        $value = (is_numeric($value) && !is_string($value))
          ? $value : $this->statement->driver()->escape($value, Neevo::TEXT);
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
   * Parse data part for INSERT statements ([INSERT INTO] (...) VALUES (...) ).
   * @return string
   */
  protected function parseInsertData(){
    foreach($this->_escapeArray($this->statement->getValues()) as $col => $value){
      $cols[] = $this->parseColName($col);
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Parse data part for UPDATE statements ([UPDATE ...] SET ...).
   * @return string
   */
  protected function parseUpdateData(){
    foreach($this->_escapeArray($this->statement->getValues()) as $col => $value){
      $update[] = $this->parseColName($col) . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }

  /**
   * Parse ORDER BY statement.
   * @return string
   */
  protected function parseOrdering(){
    return ' ORDER BY ' . join(', ', $this->statement->getOrdering());
  }

  /**
   * Parse GROUP BY statement.
   * @return string
   */
  protected function parseGrouping(){
    $having = $this->statement->getHaving() ? ' HAVING ' . (string) $this->statement->getHaving() : '';
    return ' GROUP BY ' . $this->statement->getGrouping() . $having;
  }

  /**
   * Parse columns part for SELECT statements.
   * @return string
   */
  protected function parseSelectCols(){
    foreach ($this->statement->getColumns() as $col) { // For each col
      $cols[] = $this->parseColName($col);
    }
    return join(', ', $cols);
  }

  protected function parseColName($col){
    if($col instanceof NeevoLiteral){
      return $col->value;
    }
    $col = trim(str_replace('::', '', $col));
    $col = preg_replace('#(\S+)\s+(as)\s+(\S+)#i', '$1 AS $3',  $col);

    if(preg_match('#[^.]+\.[^.]+#', $col)){
      return $this->statement->connection()->prefix() . $col;
    }
    return $col;
  }


  /*  ************  Internal methods  ************  */
  

  /**
   * Escape whole array for use in SQL.
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
        $value = $this->statement->driver()->escape($value, Neevo::BOOL);
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
        $value = $this->statement->driver()->escape($value, Neevo::TEXT);
      }
      elseif($value instanceof DateTime){
        $value = $this->statement->driver()->escape($value, Neevo::DATETIME);
      }
      elseif($value instanceof NeevoLiteral){
        $value = $value->value;
      }
      elseif(is_array($value)){
        $value = 'IN(' . join(', ', $this->_escapeArray($value)) . ')';
      }
      else{
        $value = $this->statement->driver()->escape((string) $value, Neevo::TEXT);
      }
    }
    return $array;
  }
  
}
