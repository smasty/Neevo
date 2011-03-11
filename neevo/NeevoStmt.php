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
 * @license  http://neevo.smasty.net/license MIT license
 * @link     http://neevo.smasty.net/
 *
 */


/**
 * Class for data manipulation statements (INSERT, UPDATE, DELETE)
 * @author Martin Srank
 * @package Neevo
 */
class NeevoStmt extends NeevoStmtBase {


    /** @var int */
    protected $affectedRows;

    /** @var array */
    protected $values = array();


    /**
     * Create UPDATE statement.
     * @param string $table
     * @param array $data
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
     * @param string $table
     * @param array $values
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
     * @param string $table
     * @return NeevoStmt fluent interface
     */
    public function delete($table){
        $this->reinit();
        $this->type = Neevo::STMT_DELETE;
        $this->tableName = $table;
        return $this;
    }


    public function run(){
        $result = parent::run();

        try{
            $this->affectedRows = $this->driver()->affectedRows();
        } catch(NeevoException $e){
                $this->affectedRows = false;
        }

        return $result;
    }


    /**
     * Get the ID generated in the last INSERT statement.
     * @return int|FALSE
     * @throws NeevoException on non-INSERT statements.
     */
    public function insertId(){
        if($this->type !== Neevo::STMT_INSERT){
            throw new NeevoException(__METHOD__.' can be called only on INSERT statements.');
        }
        $this->performed || $this->run();
        try{
            return $this->driver()->insertId();
        } catch(NeevoImplemenationException $e){
            return false;
        }
    }


    /**
     * Get the number of rows affected by the statement.
     * @return int
     */
    public function affectedRows(){
        $this->performed || $this->run();
        if($this->affectedRows === false){
            throw new NeevoException('Affected rows not supported by this driver');
        }
        return $this->affectedRows;
    }


    /** @return array */
    public function getValues(){
        return $this->values;
    }


    /** @internal */
    public function reinit(){
        parent::reinit();
        $this->affectedRows = null;
    }


}
