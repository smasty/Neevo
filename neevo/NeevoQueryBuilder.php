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
class NeevoQueryBuilder{


  /**
   * Builds WHERE statement for queries
   * @param NeevoResult $query NeevoResult instance
   * @return string
   */
  protected function buildWhere(NeevoResult $query){
    $conds = $query->getConditions();

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
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoResult $query NeevoResult instance
   * @return string
   */
  protected function buildInsertData(NeevoResult $query){
    foreach($this->_escapeArray($query->getValues()) as $col => $value){
      $cols[] = $this->buildColName($col);
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @param NeevoResult $query NeevoResult instance
   * @return string
   */
  protected function buildUpdateData(NeevoResult $query){
    foreach($this->_escapeArray($query->getValues()) as $col => $value){
      $update[] = $this->buildColName($col) . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoResult $query NeevoResult instance
   * @return string
   */
  protected function buildOrder(NeevoResult $query){
    return ' ORDER BY ' . join(', ', $query->getOrdering());
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoResult $query NeevoResult instance
   * @return string
   */
  protected function buildSelectCols(NeevoResult $query){
    foreach ($query->getColumns() as $col) { // For each col
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
      if(is_bool($value))
        $value = $this->escape($value, Neevo::BOOL);

      elseif(is_numeric($value)){
        if(is_int($value))
          $value = intval($value);

        elseif(is_float($value))
          $value = floatval($value);

        else $value = $value;
      }
      elseif(is_string($value))
        $value = $this->_escapeString($value);

      elseif($value instanceof DateTime)
        $value = $this->escape($value, Neevo::DATETIME);

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
    return is_numeric($string) ? $string : $this->escape($string, Neevo::TEXT);
  }
  
}
