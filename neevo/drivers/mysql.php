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
class NeevoDriverMySQL extends NeevoDriver implements INeevoDriver{

  private $neevo, $resource;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("mysql")) throw new NeevoException("PHP extension 'mysql' not loaded.");
    $this->neevo = $neevo;
  }


  public function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    if(!is_resource($connection)) $this->neevo()->error("Connection to host '".$opts['host']."' failed");
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->neevo()->error("Could not select database '{$opts['database']}'");
    }

    if($opts['encoding'] && is_resource($connection)){
      if (function_exists('mysql_set_charset'))
				$ok = @mysql_set_charset($opts['encoding'], $connection);
			if (!$ok)
				$this->neevo()->sql("SET NAMES ".$opts['encoding'])->run();
    }
    $this->resource = $connection;
  }


  public function close(){
    @mysql_close($this->resource);
  }


  public function free($resultSet){
    return @mysql_free_result($resultSet);
  }


  public function query($query_string){
    return @mysql_query($query_string, $this->resource);
  }


  public function error($neevo_msg){
    $mysql_msg = mysql_error($this->resource);
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $neevo_msg.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return array($msg, mysql_errno($this->resource));
  }


  public function fetch($resultSet){
    return @mysql_fetch_assoc($resultSet);
  }


  public function seek($resultSet, $row_number){
    return @mysql_data_seek($resultSet, $row_number);
  }


  public function insertId(){
    return mysql_insert_id($this->resource);
  }


  public function rand(NeevoQuery $query){
    $query->order('RAND()');
  }


  public function rows($resultSet){
    return mysql_num_rows($resultSet);
  }


  public function affectedRows(){
    return mysql_affected_rows($this->resource);
  }


  public function build(NeevoQuery $query){

    $where = '';
    $order = '';
    $limit = '';
    $q = '';

    if($query->getSql())
      $q = $query->getSql();

    else{
      $table = $query->getTable();

      if($query->getWhere())
        $where = $this->buildWhere($query);

      if($query->getOrder())
        $order = $this->buildOrder($query);

      if($query->getLimit()) $limit = " LIMIT " .$query->getLimit();
      if($query->getOffset()) $limit .= " OFFSET " .$query->getOffset();

      if($query->getType() == 'select'){
        $cols = $this->buildSelectCols($query);
        $q .= "SELECT $cols FROM $table$where$order$limit";
      }

      elseif($query->getType() == 'insert' && $query->getData()){
        $insert_data = $this->buildInsertData($query);
        $q .= "INSERT INTO $table$insert_data";
      }

      elseif($query->getType() == 'update' && $query->getData()){
        $update_data = $this->buildUpdateData($query);
        $q .= "UPDATE $table$update_data$where$order$limit";
      }

      elseif($query->getType() == 'delete')
        $q .= "DELETE FROM $table$where$order$limit";
    }
    return "$q;";
  }


  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
        return "'". mysql_real_escape_string($value) ."'";
        break;

      case Neevo::BINARY:
        return "_binary'". mysql_real_escape_string($value) ."'";

      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

      case Neevo::DATE:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d'") : date("'Y-m-d'", $value);
        
      default:
        $this->neevo()->error('Unsupported data type');
        break;
    }
  }


  public function neevo(){
    return $this->neevo;
  }

}