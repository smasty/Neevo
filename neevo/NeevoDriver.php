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
  public function build_tablename(NeevoQuery $query){
    $pieces = explode(".", $query->table);
    $prefix = $query->neevo->connection()->prefix();
    if(isset($pieces[1]))
      return $this->col_quotes[0] .$pieces[0] .$this->col_quotes[1] ."." .$this->col_quotes[0] .$prefix .$pieces[1] .$this->col_quotes[1];
    else return $this->col_quotes[0] .$prefix .$pieces[0] .$this->col_quotes[1];
  }


  /**
   * Builds WHERE statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  public function build_where(NeevoQuery $query){
    $prefix = $query->neevo->connection()->prefix();
    $in_construct = false;

    foreach ($query->where as $where) {
      if(is_array($where[2])){ // WHERE col IN(...)
        $where[2] = "(" .join(", ", NeevoStatic::escape_array($where[2], $this)) .")";
        $in_construct = true;
      }
      $wheres[] = $where;
    }
    unset($wheres[count($wheres)-1][3]); // Unset last glue

    foreach ($wheres as $in_where) { // Fre each cndition...
      if(NeevoStatic::is_sql_func($in_where[0]))
        $in_where[0] = NeevoStatic::quote_sql_func($in_where[0]);

      if(strstr($in_where[0], ".")) // If format is table.column
        $in_where[0] = preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#", $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] ."." .$this->col_quotes[0] ."$3" .$this->col_quotes[1], $in_where[0]);
      else
        $in_where[0] = $this->col_quotes[0] .$in_where[0] .$this->col_quotes[1];

      if(!$in_construct) // If not col IN(...), escape value
        $in_where[2] = NeevoStatic::escape_string($in_where[2], $this);

      $wheres2[] = join(' ', $in_where); // Join each condition to string
    }
    return " WHERE ".join(' ', $wheres2); // And finally, join t one string
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  public function build_insert_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this) as $col => $value){
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
  public function build_update_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this) as $col => $value){
      $update[] = $this->col_quotes[0] .$col .$this->col_quotes[1] ."=" .$value;
    }
    return " SET " .join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  public function build_order(NeevoQuery $query){
    foreach ($query->order as $in_order) {
      $in_order[0] = (NeevoStatic::is_sql_func($in_order[0])) ? $in_order[0] : $this->col_quotes[0] .$in_order[0] .$this->col_quotes[1];
      $orders[] = join(' ', $in_order);
    }
    return " ORDER BY ".join(', ', $orders);
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  public function build_select_cols(NeevoQuery $query){
    $prefix = $query->neevo->connection()->prefix();
    foreach ($query->columns as $col) { // For each col
      $col = trim($col);
      if($col != '*'){
        if(strstr($col, ".*")){ // If format is table.*
          $col = preg_replace("#([0-9A-Za-z_]+)(\.)(\*)#", $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] .".*", $col);
        }
        else{
          if(strstr($col, ".")) // If format is table.col
            $col = preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#", $this->col_quotes[0] ."$prefix$1" .$this->col_quotes[1] ."." .$this->col_quotes[0] ."$3" .$this->col_quotes[1], $col);
          if(NeevoStatic::is_as_constr($col))
            $col = NeevoStatic::quote_as_constr($col, $this->get_quotes());
          elseif(NeevoStatic::is_sql_func($col))
            $col = NeevoStatic::quote_sql_func($col);
          elseif(!strstr($col, ".")) // If normal format
            $col = $this->col_quotes[0] .$col .$this->col_quotes[1];
        }
      }
      $cols[] = $col;
    }
    return join(', ', $cols);
  }

}
?>
