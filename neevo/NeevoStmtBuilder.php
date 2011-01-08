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
 * Building SQL string from NeevoResult instance.
 * @package Neevo
 */
class NeevoStmtBuilder {

  /** @var NeevoStmtBase */
  protected $statement;

  /**
   * Build the SQL statement from the instance.
   * @param NeevoStmtBase $statement
   * @return string The SQL statement
   */
  public function build(NeevoStmtBase $statement){

    $this->statement = $statement;

    $where = '';
    $order = '';
    $group = '';
    $limit = '';
    $q = '';

    $table = $statement->getTable();

    // JOIN
    if($statement instanceof NeevoResult && $statement->getJoin()){
      $table = $table .' '. $this->buildJoin();
    }

    // WHERE
    if($statement->getConditions()){
      $where = $this->buildWhere();
    }

    // ORDER BY
    if($statement->getOrdering()){
      $order = $this->buildOrdering();
    }

    // GROUP BY
    if($statement instanceof NeevoResult && $statement->getGrouping()){
      $group = $this->buildGrouping();
    }

    // LIMIT, OFFSET
    if($statement->getLimit()){
      $limit = ' LIMIT ' .$statement->getLimit();
    }
    if($statement->getOffset()){
      $limit .= ' OFFSET ' .$statement->getOffset();
    }

    if($statement->getType() == Neevo::STMT_SELECT){
      $cols = $this->buildSelectCols();
      $q .= "SELECT $cols FROM " .$table.$where.$group.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
      $insert_data = $this->buildInsertData();
      $q .= 'INSERT INTO ' .$table.$insert_data;
    }
    elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
      $update_data = $this->buildUpdateData();
      $q .= 'UPDATE ' .$table.$update_data.$where.$order.$limit;
    }
    elseif($statement->getType() == Neevo::STMT_DELETE)
      $q .= 'DELETE FROM ' .$table.$where.$order.$limit;

    return $q.';';
  }

  /**
   * Build JOIN part for SELECT statement.
   * @throws NeevoException
   * @return string
   */
  protected function buildJoin(){
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
   * Build WHERE condition for statement.
   * @return string
   */
  protected function buildWhere(){
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
        $value = $value->value;
      } elseif($value instanceof DateTime){ // field = DateTime
        $operator = ' = ';
        $value = $this->statement->driver()->escape($value, Neevo::DATETIME);
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
   * Build data part for INSERT statements ([INSERT INTO] (...) VALUES (...) ).
   * @return string
   */
  protected function buildInsertData(){
    foreach($this->_escapeArray($this->statement->getValues()) as $col => $value){
      $cols[] = $this->buildColName($col);
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Build data part for UPDATE statements ([UPDATE ...] SET ...).
   * @return string
   */
  protected function buildUpdateData(){
    foreach($this->_escapeArray($this->statement->getValues()) as $col => $value){
      $update[] = $this->buildColName($col) . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }

  /**
   * Build ORDER BY statement.
   * @return string
   */
  protected function buildOrdering(){
    return ' ORDER BY ' . join(', ', $this->statement->getOrdering());
  }

  /**
   * Build GROUP BY statement.
   * @return string
   */
  protected function buildGrouping(){
    $having = $this->statement->getHaving() ? ' HAVING ' . (string) $this->statement->getHaving() : '';
    return ' GROUP BY ' . $this->statement->getGrouping() . $having;
  }

  /**
   * Build columns part for SELECT statements.
   * @return string
   */
  protected function buildSelectCols(){
    foreach ($this->statement->getColumns() as $col) { // For each col
      $cols[] = $this->buildColName($col);
    }
    return join(', ', $cols);
  }

  protected function buildColName($col){
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
        $value = $this->_escapeString($value);
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
        $value = $this->_escapeString((string) $value);
      }
    }
    return $array;
  }

  /**
   * Escape given string for use in SQL.
   * @param string $string
   * @return string
   * @internal
   * @todo
   */
  protected function _escapeString($string){
    if(get_magic_quotes_gpc()){
      $string = stripslashes($string);
    }
    return $this->statement->driver()->escape($string, Neevo::TEXT);
  }
  
}
