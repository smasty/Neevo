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
  protected $stmt;
  protected $clauses = array();

  /**
   * Parse the instance.
   * @param NeevoStmtBase $statement
   * @return string The SQL statement
   */
  public function parse(NeevoStmtBase $statement){
    $this->stmt = $statement;
    $where = $order = $group = $limit = $q = '';
    $table = $statement->getTable();

    if($this->stmt instanceof NeevoResult && $this->stmt->getJoin()){
      $table = $table .' '. $this->parseJoin();
    }
    if($this->stmt->getConditions()){
      $where = $this->parseWhere();
    }
    if($this->stmt instanceof NeevoResult && $this->stmt->getGrouping()){
      $group = $this->parseGrouping();
    }
    if($this->stmt->getOrdering()){
      $order = $this->parseOrdering();
    }
    if($this->stmt->getLimit() || $this->stmt->getOffset()){
      $limit = $this->parseLimit();
    }

    $this->clauses = array($table, $where, $group, $order, $limit);

    if($this->stmt->getType() == Neevo::STMT_SELECT){
      $q = $this->parseSelectStmt();
    }
    elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
      $q = $this->parseInsertStmt();
    }
    elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
      $q = $this->parseUpdateStmt();
    }
    elseif($statement->getType() == Neevo::STMT_DELETE){
      $q = $this->parseDeleteStmt();
    }

    $this->stmt = null;
    $this->clauses = array();
    return $q . ';';
  }

  /**
   * Parse SELECT statement.
   * @return string
   */
  protected function parseSelectStmt(){
    $cols = array();
    list($table, $where, $group, $order, $limit) = $this->clauses;
    foreach ($this->stmt->getColumns() as $col) {
      $cols[] = $this->parseColName($col);
    }
    $cols = join(', ', $cols);

    return "SELECT $cols FROM " .$table.$where.$group.$order.$limit;
  }

  /**
   * Parse INSERT statement.
   * @return string
   */
  protected function parseInsertStmt(){
    $cols = array();
    foreach($this->_escapeArray($this->stmt->getValues()) as $col => $value){
      $cols[] = $this->parseColName($col);
      $values[] = $value;
    }
    $data = ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';

    return 'INSERT INTO '.$this->clauses[0].$data;
  }

  /**
   * Parse UPDATE statement.
   * @return string
   */
  protected function parseUpdateStmt(){
    $values = array();
    list($table, $where, , $order, $limit) = $this->clauses;
    foreach($this->_escapeArray($this->stmt->getValues()) as $col => $value){
      $values[] = $this->parseColName($col) . ' = ' . $value;
    }
    $data = ' SET ' . join(', ', $values);

    return 'UPDATE ' .$table.$data.$where.$order.$limit;
  }

  /**
   * Parse DELETE statement.
   * @return string
   */
  protected function parseDeleteStmt(){
    list($table, $where, , $order, $limit) = $this->clauses;
    
    return 'DELETE FROM ' .$table.$where.$order.$limit;
  }

  /**
   * Parse JOIN clause.
   * @throws NeevoException
   * @return string
   */
  protected function parseJoin(){
    $join = $this->stmt->getJoin();
    $prefix = $this->stmt->connection()->prefix();
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
   * Parse WHERE clause.
   * @return string
   */
  protected function parseWhere(){
    $conds = $this->stmt->getConditions();

    // Unset glue on last condition
    unset($conds[count($conds)-1]['glue']);

    $conditions = array();
    foreach($conds as $cond){

      // Conditions with placeholders
      if($cond['simple'] === false){
        $expr = str_replace('::', $this->stmt->connection()->prefix(),$cond['expr']);
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
        $value = $this->stmt->driver()->escape($value, Neevo::DATETIME);
      } else{ // field = value
        $operator = ' = ';
        $value = (is_numeric($value) && !is_string($value))
          ? $value : $this->stmt->driver()->escape($value, Neevo::TEXT);
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
   * Parse ORDER BY clause.
   * @return string
   */
  protected function parseOrdering(){
    return ' ORDER BY ' . join(', ', $this->stmt->getOrdering());
  }

  /**
   * Parse GROUP BY clause.
   * @return string
   */
  protected function parseGrouping(){
    $having = $this->stmt->getHaving() ? ' HAVING ' . (string) $this->stmt->getHaving() : '';
    return ' GROUP BY ' . $this->stmt->getGrouping() . $having;
  }

  protected function parseColName($col){
    if($col instanceof NeevoLiteral){
      return $col->value;
    }
    $col = trim(str_replace('::', '', $col));
    $col = preg_replace('#(\S+)\s+(as)\s+(\S+)#i', '$1 AS $3',  $col);

    if(preg_match('#[^.]+\.[^.]+#', $col)){
      return $this->stmt->connection()->prefix() . $col;
    }
    return $col;
  }

  /**
   * Parse LIMIT/OFFSET clause.
   * @return string
   */
  protected function parseLimit(){
    $limit = ' LIMIT '. (int) $this->stmt->getLimit();
    if($o = $this->stmt->getOffset()){
      $limit .= ' OFFSET '. (int) $o;
    }
    return $limit;
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
        $value = $this->stmt->driver()->escape($value, Neevo::BOOL);
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
        $value = $this->stmt->driver()->escape($value, Neevo::TEXT);
      }
      elseif($value instanceof DateTime){
        $value = $this->stmt->driver()->escape($value, Neevo::DATETIME);
      }
      elseif($value instanceof NeevoLiteral){
        $value = $value->value;
      }
      elseif(is_array($value)){
        $value = 'IN(' . join(', ', $this->_escapeArray($value)) . ')';
      }
      else{
        $value = $this->stmt->driver()->escape((string) $value, Neevo::TEXT);
      }
    }
    return $array;
  }
  
}
