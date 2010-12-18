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
class NeevoStatementBuilder{

  /** @var Neevo */
  protected $neevo;

  /**
   * Instantiate StatementBuilder
   * @param Neevo $neevo
   */
  public function  __construct(Neevo $neevo){
    $this->neevo = $neevo;
  }


  /**
   * Builds statement from NeevoResult instance
   * @param NeevoResult $statement NeevoResult instance
   * @return string the statement
   */
  public function build(NeevoResult $statement){

    $where = '';
    $order = '';
    $group = '';
    $limit = '';
    $q = '';

    $table = $statement->getTable();

    if($statement->getConditions())
      $where = $this->buildWhere($statement);

    if($statement->getOrdering())
      $order = $this->buildOrdering($statement);

    if($statement->getGrouping())
      $group = $this->buildGrouping($statement);

    if($statement->getLimit()) $limit = ' LIMIT ' .$statement->getLimit();
    if($statement->getOffset()) $limit .= ' OFFSET ' .$statement->getOffset();

    if($statement->getType() == Neevo::STMT_SELECT){
      $cols = $this->buildSelectCols($statement);
      $q .= "SELECT $cols FROM $table$where$group$order$limit";
    }

    elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
      $insert_data = $this->buildInsertData($statement);
      $q .= "INSERT INTO $table$insert_data";
    }

    elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
      $update_data = $this->buildUpdateData($statement);
      $q .= "UPDATE $table$update_data$where$order$limit";
    }

    elseif($statement->getType() == Neevo::STMT_DELETE)
      $q .= "DELETE FROM $table$where$order$limit";

    return $q.';';
  }
  

  /**
   * Builds WHERE condition statement
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildWhere(NeevoResult $statement){
    $conds = $statement->getConditions();

    unset($conds[count($conds)-1][3]);

    foreach($conds as &$cond){
      $cond[0] = $this->buildColName($cond[0]);
      // col = true
      if($cond[2] === true){
        unset($cond[1], $cond[2]);
      }
      // col = false
      elseif($cond[2] === false){
        $x = $cond[0];
        $cond[1] = $cond[0];
        $cond[0] = 'NOT';
        unset($cond[2]);
      }
      // col IN(...)
      elseif(is_array($cond[2]))
        $cond[2] = '(' . join(', ', $this->_escapeArray($cond[2])) . ')';
      // col = sql literal
      elseif($cond[2] instanceof NeevoLiteral)
        $cond[2] = $cond[2]->value;
      // col IS NULL
      elseif($cond[2] !== 'NULL')
        $cond[2] = $this->_escapeString($cond[2]);

      $cond = join(' ', $cond);
    }

    return ' WHERE ' . join(' ', $conds);
  }


  /**
   * Builds data part for INSERT statements ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildInsertData(NeevoResult $statement){
    foreach($this->_escapeArray($statement->getValues()) as $col => $value){
      $cols[] = $this->buildColName($col);
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Builds data part for UPDATE statements ([UPDATE ...] SET ...)
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildUpdateData(NeevoResult $statement){
    foreach($this->_escapeArray($statement->getValues()) as $col => $value){
      $update[] = $this->buildColName($col) . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }


  /**
   * Builds ORDER BY statement
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildOrdering(NeevoResult $statement){
    return ' ORDER BY ' . join(', ', $statement->getOrdering());
  }


  /**
   * Builds GROUP BY statement
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildGrouping(NeevoResult $statement){
    $having = $statement->getHaving() ? ' HAVING ' . (string) $statement->getHaving() : '';
    return ' GROUP BY ' . $statement->getGrouping() . $having;
  }


  /**
   * Builds columns part for SELECT statements
   * @param NeevoResult $statement NeevoResult instance
   * @return string
   */
  protected function buildSelectCols(NeevoResult $statement){
    foreach ($statement->getColumns() as $col) { // For each col
      $cols[] = $this->buildColName($col);
    }
    return join(', ', $cols);
  }
  
  
  /*  ******  Internal methods  ******  */


  protected function buildColName($col){
    if($col instanceof NeevoLiteral)
      return $col->value;
    $col = trim($col);
    $col = preg_replace('#(\S+)\s+(as)\s+(\S+)#i', '$1 AS $3',  $col);

    if(preg_match('#([^.]+)(\.)([^.]+)#', $col))
      return $this->neevo->connection()->prefix() . $col;
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
      if(is_null($value))
        $value = 'NULL';

      elseif(is_bool($value))
        $value = $this->neevo->driver()->escape($value, Neevo::BOOL);

      elseif(is_numeric($value)){
        if(is_int($value))
          $value = intval($value);

        elseif(is_float($value))
          $value = floatval($value);

        else $value = intval($value);
      }
      elseif(is_string($value))
        $value = $this->_escapeString($value);

      elseif($value instanceof DateTime)
        $value = $this->neevo->driver()->escape($value, Neevo::DATETIME);

      elseif($value instanceof NeevoLiteral)
        $value = $value->value;

      else $value = $this->_escapeString((string) $value);
    }
    return $array;
  }

  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   * @internal
   */
  protected function _escapeString($string){
    if(get_magic_quotes_gpc()) $string = stripslashes($string);
    return $this->neevo->driver()->escape($string, Neevo::TEXT);
  }
  
}
