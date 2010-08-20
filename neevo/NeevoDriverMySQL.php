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
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Neevo MySQL driver class
 * @package NeevoDrivers
 */
class NeevoDriverMySQL implements INeevoDriver{

  /** Character used as column quote, e.g `column` in MySQL */
  const COL_QUOTE = '`';

  /** @var Neevo $neevo Reference to main Neevo object */
  private $neevo;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct($neevo){
    if(!extension_loaded("mysql")) throw new NeevoException("PHP extension 'mysql' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8
   * );</pre>
   * @return bool
   */
  public function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    if(!is_resource($connection)) $this->neevo->error("Connection to host '".$opts['host']."' failed");
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->neevo->error("Could not select database '{$opts['database']}'");
    }
    $this->neevo->set_resource($connection);
    $this->neevo->set_options($opts);

    if($opts['encoding']){
      if (function_exists('mysql_set_charset'))
				$ok = @mysql_set_charset($opts['encoding'], $this->neevo->resource());
			if (!$ok)
				$this->neevo->sql("SET NAMES ".$opts['encoding'])->run();
    }
    return (bool) $connection;
  }


  /**
   * Closes given resource
   * @param resource $resource
   * @return void
   */
  public function close($resource){
    @mysql_close($resource);
  }


  /**
   * Frees memory used by result
   * @param resource $result
   * @return bool
   */
  public function free($result){
    return @mysql_free_result($result);
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @param resource Connection resource
   * @return resource
   */
  public function query($query_string, $resource){
    return @mysql_query($query_string, $resource);
  }


 /**
   * If error-reporting is turned on, handle errors following current error mode:
   * <ul><li>E_NONE: does nothing.</li>
   * <li>E_CATCH: handles the error by defined error-handler.</li>
   * <li>E_WARNING: handles the error if $catch==true, otherwise throws new NeevoException.</li>
   * <li>E_STRICT throws new NeevoException.</li></ul>
   * @param string $neevo_msg Error message
   * @param bool $warning This error is warning only
   * @throws NeevoException
   * @return false
   */
  public function error($neevo_msg, $warning = false){
    $mysql_msg = mysql_error();
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);
    $msg = "$neevo_msg. $mysql_msg";
    $mode = $this->neevo->error_reporting();
    if($mode != Neevo::E_NONE){
      if(($mode != Neevo::E_STRICT && $catch) || $mode == Neevo::E_CATCH){
        call_user_func($this->neevo->error_handler(), $msg);
      }
      else throw new NeevoException($msg);
    } return false;
  }


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resource Query resource
   * @return array
   */
  public function fetch($resource){
    return @mysql_fetch_assoc($resource);
  }


  /**
   * Move internal result pointer
   * @param resource $resource Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resource, $row_number){
    return @mysql_data_seek($resource, $row_number);
  }


  /**
   * Get the ID generated in the INSERT query
   * @param resource $resource Query resource
   * @return int
   */
  public function insert_id($resource){
    return mysql_insert_id($resource);
  }


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query){
    $query->order('RAND()');
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number
   * of rows in result for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return int|FALSE Number of rows (int) or FALSE
   */
  public function rows(NeevoQuery $query){
    if($query->type != 'select') $aff_rows = $query->time() ? @mysql_affected_rows($query->neevo->resource()) : false;
    else $num_rows = @mysql_num_rows($query->resource);

    if($num_rows || $aff_rows)
      return $num_rows ? $num_rows : $aff_rows;
    else return false;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string The Query
   */
  public function build(NeevoQuery $query){

    $where = "";
    $order = "";
    $limit = "";
    $q = "";

    if($query->sql)
      $q = $query->sql;

    else{
      $table = $this->build_tablename($query);

      if($query->where)
        $where = $this->build_where($query);

      if($query->order)
        $order = $this->build_order($query);

      if($query->limit) $limit = " LIMIT " .$query->limit;
      if($query->offset) $limit .= " OFFSET " .$query->offset;

      if($query->type == 'select'){
        $cols = $this->build_select_cols($query);
        $q .= "SELECT $cols FROM $table$where$order$limit";
      }

      if($query->type == 'insert' && $query->data){
        $insert_data = $this->build_insert_data($query);
        $q .= "INSERT INTO $table$insert_data";
      }

      if($query->type == 'update' && $query->data){
        $update_data = $this->build_update_data($query);
        $q .= "UPDATE $table$update_data$where$order$limit";
      }

      if($query->type == 'delete')
        $q .= "DELETE FROM $table$where$order$limit";
    }
    return "$q;";
  }


  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   */
  public function escape_string($string){
    return mysql_real_escape_string($string);
  }


  /**
   * Builds table-name for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_tablename(NeevoQuery $query){
    $pieces = explode(".", $query->table);
    $prefix = $query->neevo->prefix();
    if(isset($pieces[1]))
      return self::COL_QUOTE .$pieces[0] .self::COL_QUOTE ."." .self::COL_QUOTE .$prefix .$pieces[1] .self::COL_QUOTE;
    else return self::COL_QUOTE .$prefix .$pieces[0] .self::COL_QUOTE;
  }


  /**
   * Builds WHERE statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_where(NeevoQuery $query){
    $prefix = $query->neevo->prefix();
    $in_construct = false;

    foreach ($query->where as $where) {
      if(is_array($where[2])){ // WHERE col IN(...)
        $where[2] = "(" .join(", ", NeevoStatic::escape_array($where[2], $this->neevo)) .")";
        $in_construct = true;
      }
      $wheres[] = $where;
    }
    unset($wheres[count($wheres)-1][3]); // Unset last glue
    
    foreach ($wheres as $in_where) { // Fre each cndition...
      if(NeevoStatic::is_sql_func($in_where[0]))
        $in_where[0] = NeevoStatic::quote_sql_func($in_where[0]);
      
      if(strstr($in_where[0], ".")) // If format is table.column
        $in_where[0] = preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#", self::COL_QUOTE ."$prefix$1" .self::COL_QUOTE ."." .self::COL_QUOTE ."$3" .self::COL_QUOTE, $in_where[0]);
      else
        $in_where[0] = self::COL_QUOTE .$in_where[0] .self::COL_QUOTE;
      
      if(!$in_construct) // If not col IN(...), escape value
        $in_where[2] = NeevoStatic::escape_string($in_where[2], $this->neevo);

      $wheres2[] = join(' ', $in_where); // Join each condition to string
    }
    foreach ($wheres2 as &$rplc_where){ // Trim some whitespce
      $rplc_where = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
    }
    return " WHERE ".join(' ', $wheres2); // And finally, join t one string
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_insert_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this->neevo) as $col => $value){
      $cols[] = self::COL_QUOTE .$col .self::COL_QUOTE;
      $values[] = $value;
    }
    return " (".join(', ',$cols).") VALUES (".join(', ',$values).")";
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_update_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this->neevo) as $col => $value){
      $update[] = self::COL_QUOTE .$col .self::COL_QUOTE ."=" .$value;
    }
    return " SET " .join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_order(NeevoQuery $query){
    foreach ($query->order as $in_order) {
      $in_order[0] = (NeevoStatic::is_sql_func($in_order[0])) ? $in_order[0] : self::COL_QUOTE .$in_order[0] .self::COL_QUOTE;
      $orders[] = join(' ', $in_order);
    }
    return " ORDER BY ".join(', ', $orders);
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   * @access private
   */
  private function build_select_cols(NeevoQuery $query){
    $prefix = $query->neevo->prefix();
    foreach ($query->columns as $col) { // For each col
      $col = trim($col);
      if($col != '*'){
        if(strstr($col, ".*")){ // If format is table.*
          $col = preg_replace("#([0-9A-Za-z_]+)(\.)(\*)#", self::COL_QUOTE ."$prefix$1" .self::COL_QUOTE .".*", $col);
        }
        else{
          if(strstr($col, ".")) // If format is table.col
            $col = preg_replace("#([0-9A-Za-z_]{1,64})(\.)([0-9A-Za-z_]+)#", self::COL_QUOTE ."$prefix$1" .self::COL_QUOTE ."." .self::COL_QUOTE ."$3" .self::COL_QUOTE, $col);
          if(NeevoStatic::is_as_constr($col))
            $col = NeevoStatic::quote_as_constr($col, self::COL_QUOTE);
          elseif(NeevoStatic::is_sql_func($col))
            $col = NeevoStatic::quote_sql_func($col);
          elseif(!strstr($col, ".")) // If normal format
            $col = self::COL_QUOTE .$col .self::COL_QUOTE;
        }
      }
      $cols[] = $col;
    }
    return join(', ', $cols);
  }
}

?>