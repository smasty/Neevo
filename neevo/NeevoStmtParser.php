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
 * NeevoStmt to SQL command parser.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoStmtParser {

  /** @var NeevoStmtBase */
  protected $stmt;

  /** @var array */
  protected $clauses = array();

  /**
   * Parse the instance.
   * @param NeevoStmtBase $statement
   * @return string The SQL statement
   */
  public function parse(NeevoStmtBase $statement){
    $this->stmt = $statement;
    $where = $order = $group = $limit = $q = '';
    $table = $this->escapeValue($statement->getTable(), Neevo::IDENTIFIER);

    if($this->stmt instanceof NeevoResult && $this->stmt->getJoin()){
      $table = $table . ' ' . $this->parseJoin();
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

    $this->clauses = array($table, $where, $group, $order);

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
    $cols = $this->stmt->getColumns();
    list($table, $where, $group, $order) = $this->clauses;
    foreach($cols as $key => $col){
      $cols[$key] = $this->tryDelimite($col);
    }
    $cols = implode(', ', $cols);

    return $this->applyLimit("SELECT $cols FROM " . $table . $where . $group . $order);
  }

  /**
   * Parse INSERT statement.
   * @return string
   */
  protected function parseInsertStmt(){
    $cols = array();
    foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
      $cols[] = $this->parseFieldName($col);
      $values[] = $value;
    }
    $data = ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $values). ')';

    return 'INSERT INTO ' . $this->clauses[0] . $data;
  }

  /**
   * Parse UPDATE statement.
   * @return string
   */
  protected function parseUpdateStmt(){
    $values = array();
    list($table, $where) = $this->clauses;
    foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
      $values[] = $this->parseFieldName($col) . ' = ' . $value;
    }
    $data = ' SET ' . implode(', ', $values);

    return 'UPDATE ' . $table . $data . $where;
  }

  /**
   * Parse DELETE statement.
   * @return string
   */
  protected function parseDeleteStmt(){
    list($table, $where) = $this->clauses;
    
    return 'DELETE FROM ' . $table . $where;
  }

  /**
   * Parse JOIN clause.
   * @throws NeevoException
   * @return string
   */
  protected function parseJoin(){
    $join = $this->stmt->getJoin();
    $join['expr'] = $this->tryDelimite($join['expr']);
    $join['table'] = $this->tryDelimite($join['table']);
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
    
    return $type . 'JOIN ' . $join['table'] . $expr;
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

      // Conditions with modifiers
      if($cond['simple'] === false){
        $values = $this->escapeValue($cond['values'], $cond['types']);
        $s = '(' . $this->applyModifiers($cond['expr'], $cond['modifiers'], $values) . ')';
        if(isset($cond['glue'])){
          $s .= ' ' . $cond['glue'];
        }

        $conditions[] = $s;
        continue;
      }

      // Simple conditions
      $field = $this->parseFieldName($cond['field']);
      $operator = '';
      $value = $cond['value'];
      if($value === null){ // field IS NULL
          $value = ' IS NULL';
      } elseif($value === true){  // field
          $value = '';
      } elseif($value === false){ // NOT field
          $value = $field;
          $field = 'NOT ';
      } elseif(is_array($value)){ // field IN (array)
          $value = ' IN ' . $this->escapeValue($value, Neevo::ARR);
      } elseif($value instanceof NeevoLiteral){ // field = SQL literal
          $operator = ' = ';
          $value = $this->escapeValue($value, Neevo::LITERAL);
      } elseif($value instanceof DateTime){ // field = DateTime
          $operator = ' = ';
          $value = $this->escapeValue($value, Neevo::DATETIME);
      } else{ // field = value
          $operator = ' = ';
          $value = $this->escapeValue($value);
      }
      $s = '(' . $field . $operator . $value . ')';
      if(isset($cond['glue'])){
        $s .= ' '.$cond['glue'];
      }

      $conditions[] = $s;

    }

    return ' WHERE ' . implode(' ', $conditions);
  }

  /**
   * Parse ORDER BY clause.
   * @return string
   */
  protected function parseOrdering(){
    $order = array();
    foreach($this->stmt->getOrdering() as $rule){
      list($field, $type) = $rule;
      $order[] = $this->tryDelimite($field) . ($type !== null ? ' ' . $type : '');
    }
    return ' ORDER BY ' . implode(', ', $order);
  }

  /**
   * Parse GROUP BY clause.
   * @return string
   */
  protected function parseGrouping(){
    $having = $this->stmt->getHaving()
      ? ' HAVING ' . (string) $this->stmt->getHaving() : '';
    return $this->tryDelimite(' GROUP BY ' . $this->stmt->getGrouping() . $having);
  }

  /**
   * Parse column name.
   * @param string|array|NeevoLiteral $field
   * @return string
   */
  protected function parseFieldName($field){
    // preg_replace callback behaviour
    if(is_array($field)){
      $field = $field[0];
    }
    if($field instanceof NeevoLiteral){
      return $field->value;
    }

    $field = trim($field);

    if($field == '*'){
      return $field;
    }

    if(strpos($field, ' ')){
      return $field;
    }

    $field = str_replace(':', '', $field);

    if(strpos($field, '.') !== false){
      $prefix = $this->stmt->connection()->prefix();
      $field = $prefix . $field;
    }

    return $this->stmt->driver()->escape($field, Neevo::IDENTIFIER);
  }

  /**
   * Apply LIMIT/OFFSET to SQL command.
   * @param string $sql SQL command
   * @return string
   */
  protected function applyLimit($sql){
    $limit = (int) $this->stmt->getLimit();
    $offset = (int) $this->stmt->getOffset();

    if($limit > 0){
      $sql .= " LIMIT $limit";
      if($offset > 0){
        $sql .= " OFFSET $offset";
      }
    }
    return $sql;
  }

  /**
   * Escape given value.
   * @param mixed|array $value
   * @param string|array|null $type
   * @return mixed|array
   */
  protected function escapeValue($value, $type = null){
    if($value === null){
      return 'NULL';
    }
    if(is_array($type)){
      $values = array();
      foreach($value as $key => $val){
        $values[$key] = $this->escapeValue($val, $type[$key]);
      }
      return $values;
    }

    if(is_array($value) && $type === null){
      $values = array();
      foreach($value as $key => $val){
        $values[$key] = $this->escapeValue($val);
      }
      return $values;
    }

    if($type === Neevo::INT){
        return (int) $value;
    } elseif($type === Neevo::FLOAT){
        return (float) $value;
    } elseif($type === Neevo::ARR){
        $array = ($value instanceof Traversable) ? iterator_to_array($value) : (array) $value;
        return '(' . implode(', ', $this->escapeValue($array)) . ')';
    } elseif($type === Neevo::LITERAL){
        return $value instanceof NeevoLiteral ? $value->value : $value;
    } elseif($type === null || $type === ''){

        if($value instanceof DateTime){
            return $this->escapeValue($value, Neevo::DATETIME);
        } elseif($value instanceof NeevoLiteral){
            return $value->value;
        } else{
            return is_numeric($value) ? $value : $this->stmt->driver()->escape($value, Neevo::TEXT);
        }

    } else{
        return $this->stmt->driver()->escape($value, $type);
    }
  }

  /**
   * Apply modifiers to expression.
   * @param string $expr
   * @param array $modifiers
   * @param array $values
   * @return string
   */
  protected function applyModifiers($expr, array $modifiers, array $values){
    foreach($modifiers as &$mod){
      $mod = "/$mod/";
    }
    $expr = $this->tryDelimite($expr);
    return preg_replace($modifiers, $values, $expr, 1);
  }

  protected function tryDelimite($expr){
    if($expr instanceof NeevoLiteral){
      return $expr->value;
    }
    return preg_replace_callback('~:([a-z_\*][a-z0-9._\*]*)~', array($this, 'parseFieldName'), $expr);
  }

}
