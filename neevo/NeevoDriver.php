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
 * Neevo driver class
 * @package Neevo
 */
class NeevoDriver{
  
  
  /**
   * Builds table-name for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_tablename(NeevoQuery $query){
    $pieces = explode(".", $query->get_table());
    $prefix = $query->neevo()->connection()->prefix();
    if(isset($pieces[1]))
      return $this->col_quotes[0] .$pieces[0] .$this->col_quotes[1] ."." .
             $this->col_quotes[0] .$prefix .$pieces[1] .$this->col_quotes[1];

    else return $this->col_quotes[0] .$prefix .$pieces[0] .$this->col_quotes[1];
  }


  /**
   * Builds WHERE statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_where(NeevoQuery $query){
    $prefix = $query->neevo()->connection()->prefix();
    $in_construct = false;

    foreach ($query->get_where() as $where) {
      if(is_array($where[2])){ // WHERE col IN(...)
        $where[2] = "(" .join(", ", $this->_escape_array($where[2])) .")";
        $in_construct = true;
      }
      $wheres[] = $where;
    }
    unset($wheres[count($wheres)-1][3]); // Unset last glue

    foreach ($wheres as $in_where) { // For each condition...
      if($this->_is_sql_func($in_where[0]))
        $in_where[0] = $this->_quote_sql_func($in_where[0]);

      if(strstr($in_where[0], ".")) // If format is table.column
        $in_where[0] = preg_replace("#([0-9A-Za-z_]{1,256})(\.)([0-9A-Za-z_]+)#",
          $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] ."." .
          $this->col_quotes[0] ."$3" .$this->col_quotes[1], $in_where[0]);
      else
        $in_where[0] = $this->col_quotes[0] .$in_where[0] .$this->col_quotes[1];

      if(!$in_construct) // If not col IN(...), escape value
        $in_where[2] = $this->_escape_string($in_where[2]);

      $wheres2[] = join(' ', $in_where); // Join each condition to string
    }
    return " WHERE ".join(' ', $wheres2); // And finally, join to one string
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_insert_data(NeevoQuery $query){
    foreach($this->_escape_array($query->get_data()) as $col => $value){
      $cols[] = $this->col_quotes[0] .$col .$this->col_quotes[1];
      $values[] = $value;
    }
    return " (".join(', ',$cols).") VALUES (".join(', ',$values).")";
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_update_data(NeevoQuery $query){
    foreach($this->_escape_array($query->get_data()) as $col => $value){
      $update[] = $this->col_quotes[0] .$col .$this->col_quotes[1] ."=" .$value;
    }
    return " SET " .join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_order(NeevoQuery $query){
    foreach ($query->get_order() as $in_order) {
      $in_order[0] = ($this->_is_sql_func($in_order[0]))
        ? $in_order[0] : $this->col_quotes[0] .$in_order[0] .$this->col_quotes[1];
      $orders[] = join(' ', $in_order);
    }
    return " ORDER BY ".join(', ', $orders);
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  protected function build_select_cols(NeevoQuery $query){
    $prefix = $query->neevo()->connection()->prefix();
    foreach ($query->get_cols() as $col) { // For each col
      $col = trim($col);
      if($col != '*'){
        if(strstr($col, ".*")){ // If format is table.*
          $col = preg_replace("#([0-9A-Za-z_]+)(\.)(\*)#",
            $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] .".*", $col);
        }
        else{
          if(strstr($col, ".")) // If format is table.col
            $col = preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#",
              $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] ."." .
              $this->col_quotes[0] ."$3" .$this->col_quotes[1], $col);
          if($this->_is_as_constr($col))
            $col = $this->_quote_as_constr($col);
          elseif($this->_is_sql_func($col))
            $col = $this->_quote_sql_func($col);
          elseif(!strstr($col, ".")) // If normal format
            $col = $this->col_quotes[0] .$col .$this->col_quotes[1];
        }
      }
      $cols[] = $col;
    }
    return join(', ', $cols);
  }
  
  
  /*  ******  Internal methods  ******  */


  /**
   * Escapes whole array for use in SQL
   * @param array $array
   * @return array
   */
  protected function _escape_array(array $array){
    foreach($array as &$value){
       $value = is_numeric($value)
         ? $value : ( is_string($value)
           ? $this->_escape_string($value) : ( is_array($value) ? $this->_escape_array($value)
           : $value ) );
    }
    return $array;
  }

  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   */
  protected function _escape_string($string){
    if(get_magic_quotes_gpc()) $string = stripslashes($string);
    $string = $this->escape_string($string);
    return $this->_is_sql_func($string) ? $this->_quote_sql_func($string) : "'$string'";
  }

  /**
   * Checks whether a given string is a SQL function or not
   * @param string $string Query fragmet
   * @return bool
   */
  protected function _is_sql_func($string){
    if(is_string($string)){
      $var = strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/', '', $string));
      return in_array( preg_replace('/\(.*\)/', '', $var), NeevoQuery::$sql_functions);
    }
    else return false;

  }

  /**
   * Quotes given SQL function
   * @param string $sql_func SQL function fragment
   * @return string
   */
  protected function _quote_sql_func($sql_func){
    return str_replace(array('("', '")'), array('(\'', '\')'), $sql_func);
  }

  /**
   * Checks whether a given string is a SQL 'AS construction' ([SELECT] fruit AS vegetable)
   * @param string $string Query fragment
   * @return bool
   */
  protected function _is_as_constr($string){
    return (bool) preg_match('/(.*) as \w*/i', $string);
  }

  /**
   * Quotes given 'AS construction'
   * @param string $as_constr
   * @return string
   */
  protected function _quote_as_constr($as_constr){
    $col_quote = $this->get_quotes();
    $construction = explode(' ', $as_constr);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0] = $col_quote[0] .$construction[0] .$col_quote[1];
    }
    $as_constr = join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS ' .$col_quote[0] .'$3' .$col_quote[1], $as_constr);
  }

}
?>
