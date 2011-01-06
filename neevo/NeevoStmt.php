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
 * Class for non-retriveing statements.
 * @package Neevo
 */
class NeevoStmt extends NeevoStmtBase {

  protected $affectedRows, $values = array();

  /**
   * Create non-retrieving statement.
   * @param array $neevo
   * @return void
   */
  public function  __construct(Neevo $neevo){
    $this->neevo = $neevo;
  }


  /**
   * Create UPDATE statement.
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
   * Create INSERT statement.
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
   * Create DELETE statement.
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
   * Get the ID generated in the last INSERT statement.
   * @return int|FALSE
   */
  public function insertId(){
    $this->isPerformed() || $this->run();
    return $this->neevo->driver()->insertId();
  }


  /**
   * Get the number of rows affected by the statement.
   * @return int
   */
  public function affectedRows(){
    $this->isPerformed() || $this->run();
    return $this->affectedRows = $this->neevo->driver()->affectedRows();
  }


  /*  ******  Setters & Getters  ******  */


  /** @internal */
  private function reinit(){
    $this->performed = false;
    $this->affectedRows = null;
  }

  /**
   * Statement values fraction for INSERT/UPDATE statements.
   *
   * [INSERT INTO tbl] (col1, col2, ...) VALUES (val1, val2, ...) or
   * [UPDATE tbl] SET col1 = val1,  col2 = val2, ...
   * @return array
   */
  public function getValues(){
    return $this->values;
  }
  
}
