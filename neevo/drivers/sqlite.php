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
 * Neevo SQLite driver class
 * @package NeevoDrivers
 */
class NeevoDriverSQLite extends NeevoDriver implements INeevoDriver{

  private $neevo, $resource, $last_error;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("sqlite")) throw new NeevoException("PHP extension 'sqlite' not loaded.");
    $this->neevo = $neevo;
  }


  public function connect(array $opts){
    $connection = sqlite_open($opts['database'], 0666, $error);
    if(!is_resource($connection))
      $this->neevo()->error("Connection to database '".$opts['database']." failed");
    $this->resource = $connection;
  }


  public function close(){
    @sqlite_close($this->resource);
  }


  public function free($resultSet){
    return true;
  }


  public function query($query_string){
    $this->last_error = '';
    $q = sqlite_query($this->resource, $query_string, null, $error);
    $this->last_error = $error;
    return $q;
  }


  public function error($neevo_msg){
    $no = sqlite_last_error($this->resource);
    $msg = $neevo_msg. '. ' . ucfirst($this->last_error);
    return array($msg, $no);
  }


  public function fetch($resultSet){
    return @sqlite_fetch_array($resultSet, SQLITE_ASSOC);
  }


  public function seek($resultSet, $row_number){
    return @sqlite_seek($resultSet, $row_number);
  }


  public function insertId(){
    return @sqlite_last_insert_rowid($this->resource);
  }


  public function rand(NeevoQuery $query){
    $query->order('RANDOM()');
  }


  public function rows($resultSet){
    return @sqlite_num_rows($resultSet);
  }


  public function affectedRows(){
    return @sqlite_changes($this->resource);
  }

  public function getPrimaryKey($table){
    return null;
  }


  public function build(NeevoQuery $query){

    $where = '';
    $order = '';
    $limit = '';
    $q = '';

    if($query->getSql())
      return $query->getSql().';';

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
      $q .= "UPDATE $table$update_data$where";
    }

    elseif($query->getType() == 'delete')
      $q .= "DELETE FROM $table$where";

    return $q.';';
  }


  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
      case Neevo::BINARY:
        return "'". sqlite_escape_string($value) ."'";
      
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