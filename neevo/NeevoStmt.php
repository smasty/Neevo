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
 * Class for non-retriveing statements
 * @package Neevo
 * @method NeevoStmt and() and( ) Sets AND glue for WHERE conditions, provides fluent interface
 * @method NeevoStmt or() or( ) Sets OR glue for WHERE conditions, provides fluent interface
 */
class NeevoStmt extends NeevoStmtBase {

  /** @var int */
  protected $affectedRows;

  /** @var array */
  protected $values = array();


  /**
   * Creates non-retrieving statement
   * @param array $object Reference to instance of Neevo class which initialized statement
   * @return void
   */
  public function  __construct(Neevo $object){
    $this->neevo = $object;
  }


  /**
   * Creates UPDATE statement
   * @param string $table Table name
   * @param array $data Data to update
   * @return NeevoStmt fluent interface
   */
  public function update($table, array $data){
    $this->reinit();
    $this->type = Neevo::STMT_UPDATE;
    $this->tableName = $table;
    $this->values = $data;
    return $this;
  }


  /**
   * Creates INSERT statement
   * @param string $table Table name
   * @param array $values Values to insert
   * @return NeevoStmt fluent interface
   */
  public function insert($table, array $values){
    $this->reinit();
    $this->type = Neevo::STMT_INSERT;
    $this->tableName = $table;
    $this->values = $values;
    return $this;
  }


  /**
   * Alias for NeevoStmt::insert()
   * @return NeevoStmt fluent interface
   */
  public function insertInto($table, array $values){
    return $this->insert($table, $values);
  }


  /**
   * Creates DELETE statement
   * @param string $table Table name
   * @return NeevoStmt fluent interface
   */
  public function delete($table){
    $this->reinit();
    $this->type = Neevo::STMT_DELETE;
    $this->tableName = $table;
    return $this;
  }


  /**
   * Get the ID generated in the INSERT statement
   * @return int|FALSE
   */
  public function insertId(){
    if(!$this->isPerformed()) $this->run();
    return $this->neevo->driver()->insertId();
  }


  /**
   * Number of rows affected by statement
   * @return int
   */
  public function affectedRows(){
    if(!$this->isPerformed()) $this->run();
    return $this->affectedRows = $this->neevo->driver()->affectedRows();
  }


  /*  ******  Setters & Getters  ******  */


  /** @internal */
  public function reinit(){
    $this->performed = false;
    $this->affectedRows = null;
  }

  /**
   * Statement values fraction for INSERT/UPDATE statements
   *
   * [INSERT INTO tbl] (col1, col2, ...) VALUES (val1, val2, ...) or
   * [UPDATE tbl] SET col1 = val1,  col2 = val2, ...
   * @return array
   */
  public function getValues(){
    return $this->values;
  }
  
}
