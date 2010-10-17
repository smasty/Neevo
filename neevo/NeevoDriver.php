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
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */

/**
 * Class with predefined methods for driver classes.
 * @package Neevo
 */
class NeevoDriver{


  /**
   * Builds WHERE statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function buildWhere(NeevoQuery $query){
    $conds = $query->getWhere();

    unset($conds[count($conds)-1][3]);

    foreach($conds as &$cond){
      $cond[0] = $this->buildColName($cond[0]);

      if($cond[2] === true){
        unset($cond[1], $cond[2]);
      }
      elseif($cond[2] === false){
        $x = $cond[0];
        $cond[0] = 'NOT';
        $cond[1] = $cond[0];
        unset($cond[2]);
      }
      elseif(is_array($cond[2]))
        $cond[2] = '(' . join(', ', $this->_escapeArray($cond[2])) . ')';
      elseif($cond[2] !== 'NULL')
        $cond[2] = $this->_escapeString($cond[2]);

      $cond = join(' ', $cond);
    }

    return ' WHERE ' . join(' ', $conds);
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function buildInsertData(NeevoQuery $query){
    foreach($this->_escapeArray($query->getData()) as $col => $value){
      $cols[] = $col;
      $values[] = $value;
    }
    return ' (' . join(', ',$cols) . ') VALUES (' . join(', ',$values). ')';
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function buildUpdateData(NeevoQuery $query){
    foreach($this->_escapeArray($query->getData()) as $col => $value){
      $update[] = $col . ' = ' . $value;
    }
    return ' SET ' . join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function buildOrder(NeevoQuery $query){
    return ' ORDER BY ' . join(', ', $query->getOrder());
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function buildSelectCols(NeevoQuery $query){
    foreach ($query->getCols() as $col) { // For each col
      $cols[] = $this->buildColName($col);
    }
    return join(', ', $cols);
  }
  
  
  /*  ******  Internal methods  ******  */


  protected function buildColName($col){
    $col = trim($col);
    $prefix = $this->neevo()->connection()->prefix();
    if(preg_match('#([^.]+)(\.)([^.]+)#', $col))
      return $prefix.$col;
    return $col;
  }


  /**
   * Escapes whole array for use in SQL
   * @param array $array
   * @param bool $sql_funcs Consider SQL functions
   * @return array
   * @internal
   */
  protected function _escapeArray(array $array, $sql_funcs = false){
    foreach($array as &$value){
      if(is_bool($value))
        $value = $this->escape($value, Neevo::BOOL);

      elseif(is_numeric($value)){
        if(is_int($value))
          $value = intval($value);

        elseif(is_float($value))
          $value = floatval($value);

        else $value = $this->_escapeString($value, $sql_funcs);
      }
      elseif(is_string($value))
        $value = $this->_escapeString($value);

      elseif($value instanceof DateTime)
        $value = $this->escape($value, Neevo::DATETIME);

      elseif($value instanceof NeevoLiteral)
        $value = (string) $value;

      else $value = $this->_escapeString((string) $value);
    }
    return $array;
  }

  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @param bool $sql_funcs Consider SQL functions
   * @return string
   * @internal
   */
  protected function _escapeString($string, $sql_funcs = false){
    if(get_magic_quotes_gpc()) $string = stripslashes($string);
    return $this->escape($string, Neevo::TEXT);
  }
  
}
